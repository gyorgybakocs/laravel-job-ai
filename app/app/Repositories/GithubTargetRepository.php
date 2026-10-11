<?php

namespace App\Repositories;

use App\Enums\GithubTargetSyncStatus;
use App\Enums\GithubTargetType;
use App\Models\GithubTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

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

    public function save(GithubTarget $target): void
    {
        $target->save();
    }
}
