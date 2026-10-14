<?php

namespace App\Jobs;

use App\Enums\GithubSyncOperationStatus;
use App\Models\GithubSyncOperation;
use App\Repositories\GithubTargetRepository;
use App\Services\AddGithubTargetResult;
use App\Services\AddGithubTargetForUser;
use App\Services\TargetRepositoriesSynchronizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGithubTarget implements ShouldQueue
{
    use Queueable;

    // Lock contention releases the job; an execution exception fails it immediately.
    public int $tries = 0;

    public int $maxExceptions = 1;

    public bool $failOnTimeout = true;

    public int $timeout;

    public function __construct(
        public readonly int $operationId,
        public readonly ?int $targetId = null,
    ) {
        $this->timeout = (int) config('services.github.sync_timeout');
    }

    public function middleware(): array
    {
        $operation = GithubSyncOperation::query()->find($this->operationId);
        if ($operation === null) {
            return [];
        }

        return [(new WithoutOverlapping('github-target:'.$operation->target_type->value.':'.strtolower($operation->target_name)))
            ->releaseAfter(config('services.github.sync_lock_retry_delay'))
            ->expireAfter(config('services.github.sync_lock_seconds'))];
    }

    public function handle(
        GithubTargetRepository $targets,
        TargetRepositoriesSynchronizer $synchronizer,
        AddGithubTargetForUser $addTarget,
    ): void {
        $operation = GithubSyncOperation::query()->with(['user', 'githubTarget'])->find($this->operationId);

        if ($operation === null || in_array($operation->status, [GithubSyncOperationStatus::Succeeded, GithubSyncOperationStatus::Failed], true)) {
            return;
        }

        $target = $operation->githubTarget;
        $user = $operation->user;
        if ($user === null || ($this->targetId !== null && ($target === null || $target->id !== $this->targetId || ! $targets->belongsToUser($user, $target)))) {
            $operation->update([
                'status' => GithubSyncOperationStatus::Failed,
                'error' => 'targets.target_not_found',
                'finished_at' => now(),
            ]);

            return;
        }

        $operation->update([
            'status' => GithubSyncOperationStatus::Running,
            'error' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        try {
            if ($this->targetId === null) {
                $target = $addTarget->add($user, $operation->target_name, $operation->target_type);
            } else {
                $synchronizer->syncForOperation($target, $user->github_token);
            }
            $operation->update([
                'github_target_id' => $target->id,
                'status' => GithubSyncOperationStatus::Succeeded,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $this->failed($exception);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $operation = GithubSyncOperation::query()->find($this->operationId);
        if ($operation === null || in_array($operation->status, [GithubSyncOperationStatus::Succeeded, GithubSyncOperationStatus::Failed], true)) {
            return;
        }

        $error = $exception instanceof ConnectionException || $exception instanceof RequestException
            ? AddGithubTargetResult::fromException($operation->target_name, $exception)->translationKey()
            : 'targets.request_failed';
        $operation->update([
            'status' => GithubSyncOperationStatus::Failed,
            'error' => $error,
            'finished_at' => now(),
        ]);
        Log::warning('GitHub sync operation failed.', [
            'operation_id' => $operation->id,
            'target_id' => $operation->github_target_id,
            'user_id' => $operation->user_id,
            'exception_type' => $exception === null ? null : $exception::class,
            'http_status' => $exception instanceof RequestException ? $exception->response->status() : null,
        ]);
    }
}
