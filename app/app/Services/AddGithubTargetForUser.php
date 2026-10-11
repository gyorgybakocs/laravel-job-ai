<?php

namespace App\Services;

use App\Enums\GithubTargetType;
use App\Models\User;
use App\Repositories\GithubTargetRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class AddGithubTargetForUser
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly GithubTargetRepository $targets,
        private readonly TargetRepositoriesSynchronizer $synchronizer,
    ) {
    }

    public function add(User $user, string $name, GithubTargetType $type): AddGithubTargetResult
    {
        try {
            $this->database->transaction(function () use ($user, $name, $type): void {
                $target = $this->targets->findOrCreateByTypeAndName($type, $name);

                $this->targets->attachToUser($user, $target);
                $this->synchronizer->sync($target, $user->github_token);
            });
        } catch (ConnectionException|RequestException $exception) {
            return AddGithubTargetResult::fromException($name, $exception);
        }

        return AddGithubTargetResult::saved($name);
    }
}
