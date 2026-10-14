<?php

namespace App\Repositories;

use App\Enums\GithubTargetSyncStatus;
use App\Enums\GithubTargetType;
use App\Models\GithubTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GithubTargetRepository
{
    /**
     * @return Collection<int, GithubTarget>
     */
    public function latestForUser(User $user): Collection
    {
        return $user
            ->githubTargets()
            ->withCount('repositories')
            ->orderByDesc('github_target_user.created_at')
            ->orderByDesc('github_targets.id')
            ->get();
    }

    public function paginatedForUserByType(
        User $user,
        GithubTargetType $type,
        string $pageName,
        ?int $perPage = null,
    ): LengthAwarePaginator {
        return $user
            ->githubTargets()
            ->with(['repositories' => fn ($query) => $query
                ->orderByDesc('stargazers_count')
                ->orderBy('name'),
                'githubSyncOperations' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->latest('id')
                    ->limit(1),
            ])
            ->withCount('repositories')
            ->where('github_targets.type', $type->value)
            ->orderByDesc('github_target_user.created_at')
            ->orderByDesc('github_targets.id')
            ->paginate($perPage ?? config('services.github.targets_per_page'), ['github_targets.*'], $pageName)
            ->withQueryString();
    }

    public function findOrCreateByTypeAndName(GithubTargetType $type, string $name): GithubTarget
    {
        return GithubTarget::query()->firstOrCreate(
            [
                'type' => $type->value,
                'name' => $name,
            ],
            [
                'sync_status' => GithubTargetSyncStatus::Pending->value,
            ],
        );
    }

    public function attachToUser(User $user, GithubTarget $target): void
    {
        $user->githubTargets()->syncWithoutDetaching([$target->id]);
    }

    public function detachFromUser(User $user, GithubTarget $target): void
    {
        $user->githubTargets()->detach($target->id);
    }

    public function belongsToUser(User $user, GithubTarget $target): bool
    {
        return $user->githubTargets()->whereKey($target->id)->exists();
    }

    public function save(GithubTarget $target): void
    {
        $target->save();
    }
}
