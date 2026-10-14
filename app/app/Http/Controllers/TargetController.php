<?php

namespace App\Http\Controllers;

use App\Enums\GithubTargetType;
use App\Http\Requests\StoreTargetRequest;
use App\Enums\GithubSyncOperationStatus;
use App\Services\RequestGithubTargetSync;
use App\Models\GithubRepository;
use App\Models\GithubTarget;
use App\Repositories\GithubTargetRepository;
use App\Services\AddGithubTargetForUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TargetController extends Controller
{
    public function __construct(
        private readonly GithubTargetRepository $targets,
        private readonly AddGithubTargetForUser $addGithubTargetForUser,
        private readonly RequestGithubTargetSync $requestGithubTargetSync,
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('targets/Index', [
            'users' => $this->targetPaginator(
                $this->targets->paginatedForUserByType($request->user(), GithubTargetType::User, 'users_page'),
            ),
            'syncPollInterval' => config('services.github.sync_poll_interval'),
            'hasActiveSyncOperations' => $request->user()->githubSyncOperations()
                ->whereIn('status', [GithubSyncOperationStatus::Queued, GithubSyncOperationStatus::Running])
                ->whereHas('githubTarget.users', fn ($query) => $query->whereKey($request->user()->id))
                ->exists(),
            'orgs' => $this->targetPaginator(
                $this->targets->paginatedForUserByType($request->user(), GithubTargetType::Organization, 'orgs_page'),
            ),
        ]);
    }

    public function store(StoreTargetRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $result = $this->addGithubTargetForUser->add(
            $request->user(),
            $validated['name'],
            GithubTargetType::from($validated['type']),
        );

        return redirect()
            ->route('targets.index')
            ->with('target_result', trans($result->translationKey(), [
                'target' => $result->targetName(),
            ]));
    }

    public function sync(GithubTarget $target, Request $request): RedirectResponse
    {
        abort_unless($this->targets->belongsToUser($request->user(), $target), 404);

        $operation = $this->requestGithubTargetSync->request($request->user(), $target);
        $message = $operation->status === GithubSyncOperationStatus::Failed
            ? $operation->error
            : ($operation->wasRecentlyCreated ? 'targets.sync_queued' : 'targets.sync_already_queued');

        return to_route('targets.index')->with('target_result', trans($message, ['target' => $target->name]));
    }

    public function destroy(GithubTarget $target, Request $request): RedirectResponse
    {
        abort_unless($this->targets->belongsToUser($request->user(), $target), 404);

        $this->targets->detachFromUser($request->user(), $target);

        return redirect()->route('targets.index');
    }

    private function targetPaginator(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(function (GithubTarget $target): array {
            $operation = $target->githubSyncOperations->first();

            return [
                'id' => $target->id,
                'name' => $target->name,
                'type' => $target->type->value,
                'status' => $target->sync_status->value,
                'last_synced_at' => $target->last_synced_at?->toISOString(),
                'last_error' => $operation?->error === null ? null : trans(
                    $operation->error, ['target' => $target->name],
                ),
                'sync_operation' => $operation === null ? null : [
                    'id' => $operation->id,
                    'status' => $operation->status->value,
                    'started_at' => $operation->started_at?->toISOString(),
                    'finished_at' => $operation->finished_at?->toISOString(),
                ],
                'repositories_count' => $target->repositories_count,
                'repositories' => $target->repositories
                    ->map(fn (GithubRepository $repository): array => [
                        'id' => $repository->id,
                        'name' => $repository->name,
                        'full_name' => $repository->full_name,
                        'description' => $repository->description,
                        'url' => $repository->html_url,
                        'language' => $repository->language,
                        'stars' => $repository->stargazers_count,
                        'open_issues' => $repository->open_issues_count,
                        'archived' => $repository->archived,
                        'github_updated_at' => $repository->external_updated_at?->toISOString(),
                    ])
                    ->values(),
            ];
        });
    }
}
