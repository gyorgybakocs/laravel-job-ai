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
        } catch (ConnectionException) {
            return AddGithubTargetResult::failed($name, 'targets.request_failed');
        } catch (RequestException $exception) {
            if ($exception->response->status() === 404) {
                return AddGithubTargetResult::notFound($name);
            }

            $translationKey = match ($exception->response->status()) {
                401 => 'targets.invalid_token',
                403, 429 => 'targets.access_restricted',
                default => 'targets.request_failed',
            };

            return AddGithubTargetResult::failed($name, $translationKey);
        }

        return AddGithubTargetResult::saved($name);
    }
}
