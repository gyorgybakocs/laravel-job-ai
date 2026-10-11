<?php

namespace App\Services;

use App\Enums\GithubTargetSyncStatus;
use App\Models\GithubTarget;
use App\Repositories\GithubRepositoryRepository;
use App\Repositories\GithubTargetRepository;
use App\Src\Github\GithubClient;
use Throwable;

class TargetRepositoriesSynchronizer
{
    public function __construct(
        private readonly GithubTargetRepository $targets,
        private readonly GithubRepositoryRepository $repositories,
        private readonly GithubClient $githubClient,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function sync(GithubTarget $target, ?string $token): void
    {
        $target->sync_status = GithubTargetSyncStatus::Syncing;
        $target->last_error = null;
        $this->targets->save($target);

        try {
            $repositories = $this->githubClient
                ->setToken($token)
                ->getRepositories($target->name, $target->type->value);

            $this->repositories->upsertManyForTarget($target, $repositories);
        } catch (Throwable $e) {
            $target->sync_status = GithubTargetSyncStatus::Failed;
            $target->last_error = $e->getMessage();
            $this->targets->save($target);

            throw $e;
        } finally {
            $this->githubClient->setToken(null);
        }

        $target->sync_status = GithubTargetSyncStatus::Synced;
        $target->last_synced_at = now();
        $target->last_error = null;
        $this->targets->save($target);
    }
}
