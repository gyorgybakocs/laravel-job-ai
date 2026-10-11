<?php

namespace App\Http\Controllers;

use App\Enums\GithubTargetType;
use App\Http\Requests\StoreTargetRequest;
use App\Models\GithubTarget;
use App\Repositories\GithubTargetRepository;
use App\Services\AddGithubTargetForUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TargetController extends Controller
{
    public function __construct(
        private readonly GithubTargetRepository $targets,
        private readonly AddGithubTargetForUser $addGithubTargetForUser,
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('targets/Index', [
            'targets' => $this->targets
                ->latestForUser($request->user())
                ->map(fn (GithubTarget $target): array => [
                    'id' => $target->id,
                    'name' => $target->name,
                    'type' => $target->type->value,
                    'sync_status' => $target->sync_status->value,
                    'repositories_count' => $target->repositories_count,
                    'created_at' => $target->created_at?->toISOString(),
                ])
                ->values(),
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
}
