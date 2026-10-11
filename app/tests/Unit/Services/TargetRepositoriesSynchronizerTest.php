<?php

namespace Tests\Unit\Services;

use App\Enums\GithubTargetSyncStatus;
use App\Enums\GithubTargetType;
use App\Models\GithubTarget;
use App\Repositories\GithubRepositoryRepository;
use App\Repositories\GithubTargetRepository;
use App\Services\TargetRepositoriesSynchronizer;
use App\Src\Github\GithubClient;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class TargetRepositoriesSynchronizerTest extends TestCase
{
    #[DataProvider('targetTypeProvider')]
    public function test_sync_fetches_repositories_and_marks_the_target_synced(
        GithubTargetType $type,
        string $expectedGithubType,
    ): void {
        $target = new GithubTarget([
            'name' => 'octocat',
            'type' => $type,
            'sync_status' => GithubTargetSyncStatus::Pending,
        ]);

        $githubRepositories = [
            ['id' => 1, 'name' => 'hello-world'],
            ['id' => 2, 'name' => 'spoon-knife'],
        ];

        $savedStatuses = [];
        $targets = Mockery::mock(GithubTargetRepository::class);
        $targets
            ->shouldReceive('save')
            ->twice()
            ->with(Mockery::on(function (GithubTarget $givenTarget) use ($target, &$savedStatuses): bool {
                $savedStatuses[] = $givenTarget->sync_status;

                return $givenTarget === $target;
            }));

        $repositories = Mockery::mock(GithubRepositoryRepository::class);
        $repositories
            ->shouldReceive('upsertManyForTarget')
            ->once()
            ->with($target, $githubRepositories);

        $githubClient = Mockery::mock(GithubClient::class);
        $githubClient
            ->shouldReceive('setToken')
            ->once()
            ->with('github-token')
            ->andReturnSelf();
        $githubClient
            ->shouldReceive('getRepositories')
            ->once()
            ->with('octocat', $expectedGithubType)
            ->andReturn($githubRepositories);
        $githubClient
            ->shouldReceive('setToken')
            ->once()
            ->with(null)
            ->andReturnSelf();

        $synchronizer = new TargetRepositoriesSynchronizer($targets, $repositories, $githubClient);

        $synchronizer->sync($target, 'github-token');

        $this->assertSame([
            GithubTargetSyncStatus::Syncing,
            GithubTargetSyncStatus::Synced,
        ], $savedStatuses);
        $this->assertSame(GithubTargetSyncStatus::Synced, $target->sync_status);
        $this->assertNotNull($target->last_synced_at);
        $this->assertNull($target->last_error);
    }

    #[DataProvider('syncFailureProvider')]
    public function test_sync_marks_the_target_failed_and_clears_the_token_on_failure(Throwable $exception): void
    {
        $target = new GithubTarget([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
            'sync_status' => GithubTargetSyncStatus::Pending,
        ]);

        $savedStatuses = [];
        $targets = Mockery::mock(GithubTargetRepository::class);
        $targets
            ->shouldReceive('save')
            ->twice()
            ->with(Mockery::on(function (GithubTarget $givenTarget) use ($target, &$savedStatuses): bool {
                $savedStatuses[] = $givenTarget->sync_status;

                return $givenTarget === $target;
            }));

        $repositories = Mockery::mock(GithubRepositoryRepository::class);
        $repositories
            ->shouldReceive('upsertManyForTarget')
            ->never();

        $githubClient = Mockery::mock(GithubClient::class);
        $githubClient
            ->shouldReceive('setToken')
            ->once()
            ->with('github-token')
            ->andReturnSelf();
        $githubClient
            ->shouldReceive('getRepositories')
            ->once()
            ->with('octocat', GithubTargetType::User->value)
            ->andThrow($exception);
        $githubClient
            ->shouldReceive('setToken')
            ->once()
            ->with(null)
            ->andReturnSelf();

        $synchronizer = new TargetRepositoriesSynchronizer($targets, $repositories, $githubClient);

        try {
            $synchronizer->sync($target, 'github-token');

            $this->fail('Expected synchronization failure was not thrown.');
        } catch (Throwable $caught) {
            $this->assertSame($exception, $caught);
        }

        $this->assertSame([
            GithubTargetSyncStatus::Syncing,
            GithubTargetSyncStatus::Failed,
        ], $savedStatuses);
        $this->assertSame(GithubTargetSyncStatus::Failed, $target->sync_status);
        $this->assertSame($exception->getMessage(), $target->last_error);
        $this->assertNull($target->last_synced_at);
    }

    public static function targetTypeProvider(): array
    {
        return [
            'user target' => [GithubTargetType::User, 'user'],
            'organization target' => [GithubTargetType::Organization, 'org'],
        ];
    }

    public static function syncFailureProvider(): array
    {
        return [
            'connection exception' => [
                new ConnectionException('GitHub connection failed.'),
            ],
            'request exception' => [
                new RequestException(new Response(new PsrResponse(404, [], '{}'))),
            ],
            'unexpected exception' => [
                new RuntimeException('Unexpected synchronization failure.'),
            ],
        ];
    }
}
