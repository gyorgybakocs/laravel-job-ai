<?php

namespace App\Services;

use App\Enums\GithubTargetSyncStatus;
use App\Enums\GithubTargetType;
use App\Models\GithubTarget;
use App\Models\User;
use App\Repositories\GithubRepositoryRepository;
use App\Repositories\GithubTargetRepository;
use App\Src\Github\GithubClient;
use Illuminate\Database\ConnectionInterface;

class AddGithubTargetForUser
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly GithubTargetRepository $targets,
        private readonly GithubRepositoryRepository $repositories,
        private readonly GithubClient $githubClient,
    ) {}

    public function add(User $user, string $name, GithubTargetType $type): GithubTarget
    {
        try {
            $repositories = $this->githubClient->setToken($user->github_token)->getRepositories($name, $type->value);
        } finally {
            $this->githubClient->setToken(null);
        }

        return $this->database->transaction(function () use ($user, $name, $type, $repositories): GithubTarget {
            $target = $this->targets->findOrCreateByTypeAndName($type, $name);
            $this->targets->attachToUser($user, $target);
            $this->repositories->upsertManyForTarget($target, $repositories);
            $target->sync_status = GithubTargetSyncStatus::Synced;
            $target->last_synced_at = now();
            $target->last_error = null;
            $this->targets->save($target);

            return $target;
        });
    }
}
