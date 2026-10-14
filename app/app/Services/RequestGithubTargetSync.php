<?php

namespace App\Services;

use App\Enums\GithubSyncOperationStatus;
use App\Enums\GithubTargetType;
use App\Jobs\SyncGithubTarget;
use App\Models\GithubSyncOperation;
use App\Models\GithubTarget;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class RequestGithubTargetSync
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly Dispatcher $dispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function request(User $user, GithubTarget $target): GithubSyncOperation
    {
        $operation = $this->database->transaction(function () use ($user, $target): GithubSyncOperation {
            GithubTarget::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();

            $existing = $user->githubSyncOperations()
                ->where('github_target_id', $target->id)
                ->whereIn('status', [GithubSyncOperationStatus::Queued, GithubSyncOperationStatus::Running])
                ->latest('id')
                ->first();

            return $existing ?? $user->githubSyncOperations()->create([
                'github_target_id' => $target->id,
                'target_name' => $target->name,
                'target_type' => $target->type,
                'status' => GithubSyncOperationStatus::Queued,
            ]);
        });

        return $this->dispatch($operation, $target->id);
    }

    public function requestAdd(User $user, string $name, GithubTargetType $type): GithubSyncOperation
    {
        $operation = $user->githubSyncOperations()->create([
            'target_name' => $name,
            'target_type' => $type,
            'status' => GithubSyncOperationStatus::Queued,
        ]);

        return $this->dispatch($operation, null);
    }

    private function dispatch(GithubSyncOperation $operation, ?int $targetId): GithubSyncOperation
    {
        if ($operation->wasRecentlyCreated) {
            try {
                $this->dispatcher->dispatch((new SyncGithubTarget($operation->id, $targetId))->afterCommit());
            } catch (Throwable $exception) {
                $operation->update([
                    'status' => GithubSyncOperationStatus::Failed,
                    'error' => 'targets.request_failed',
                    'finished_at' => now(),
                ]);
                $this->logger->error('GitHub sync dispatch failed.', [
                    'operation_id' => $operation->id,
                    'exception_type' => $exception::class,
                ]);
            }
        }

        return $operation;
    }
}
