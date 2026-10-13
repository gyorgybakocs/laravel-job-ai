<?php

namespace App\Jobs;

use App\Models\GithubTarget;
use App\Models\User;
use App\Repositories\GithubTargetRepository;
use App\Services\TargetRepositoriesSynchronizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGithubTarget implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $targetId,
        public readonly int $userId,
    ) {}

    public function handle(
        GithubTargetRepository $targets,
        TargetRepositoriesSynchronizer $synchronizer,
    ): void {
        $target = GithubTarget::query()->find($this->targetId);
        $user = User::query()->find($this->userId);

        if ($target === null || $user === null || ! $targets->belongsToUser($user, $target)) {
            return;
        }

        $synchronizer->sync($target, $user->github_token);
    }
}
