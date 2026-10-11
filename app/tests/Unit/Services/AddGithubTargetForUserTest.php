<?php

namespace Tests\Unit\Services;

use App\Enums\GithubTargetSyncStatus;
use App\Enums\GithubTargetType;
use App\Models\GithubTarget;
use App\Models\User;
use App\Repositories\GithubTargetRepository;
use App\Services\AddGithubTargetForUser;
use App\Services\AddGithubTargetResult;
use App\Services\TargetRepositoriesSynchronizer;
use Closure;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AddGithubTargetForUserTest extends TestCase
{
    #[DataProvider('targetTypeProvider')]
    public function test_add_attaches_the_target_and_synchronizes_it(GithubTargetType $type): void
    {
        $user = new User();
        $user->github_token = 'github-token';

        $target = new GithubTarget([
            'name' => 'octocat',
            'type' => $type,
            'sync_status' => GithubTargetSyncStatus::Pending,
        ]);

        $database = Mockery::mock(ConnectionInterface::class);
        $database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type(Closure::class))
            ->andReturnUsing(fn (Closure $callback) => $callback());

        $targets = Mockery::mock(GithubTargetRepository::class);
        $targets
            ->shouldReceive('findOrCreateByTypeAndName')
            ->once()
            ->with($type, 'octocat')
            ->andReturn($target);
        $targets
            ->shouldReceive('attachToUser')
            ->once()
            ->with(Mockery::on(fn (User $givenUser): bool => $givenUser === $user), $target);

        $synchronizer = Mockery::mock(TargetRepositoriesSynchronizer::class);
        $synchronizer
            ->shouldReceive('sync')
            ->once()
            ->with($target, 'github-token');

        $service = new AddGithubTargetForUser($database, $targets, $synchronizer);

        $result = $service->add($user, 'octocat', $type);

        $this->assertInstanceOf(AddGithubTargetResult::class, $result);
        $this->assertTrue($result->wasSaved());
        $this->assertSame('octocat', $result->targetName());
        $this->assertSame('targets.target_saved', $result->translationKey());
    }

    #[DataProvider('githubFailureProvider')]
    public function test_add_reports_the_error_when_synchronization_fails(Closure $exceptionFactory, string $translationKey): void
    {
        $exception = $exceptionFactory();
        $user = new User();
        $user->github_token = 'github-token';

        $target = new GithubTarget([
            'name' => 'missing-target',
            'type' => GithubTargetType::User,
            'sync_status' => GithubTargetSyncStatus::Pending,
        ]);

        $database = Mockery::mock(ConnectionInterface::class);
        $database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type(Closure::class))
            ->andReturnUsing(fn (Closure $callback) => $callback());

        $targets = Mockery::mock(GithubTargetRepository::class);
        $targets
            ->shouldReceive('findOrCreateByTypeAndName')
            ->once()
            ->with(GithubTargetType::User, 'missing-target')
            ->andReturn($target);
        $targets
            ->shouldReceive('attachToUser')
            ->once()
            ->with($user, $target);

        $synchronizer = Mockery::mock(TargetRepositoriesSynchronizer::class);
        $synchronizer
            ->shouldReceive('sync')
            ->once()
            ->with($target, 'github-token')
            ->andThrow($exception);

        $service = new AddGithubTargetForUser($database, $targets, $synchronizer);

        $result = $service->add($user, 'missing-target', GithubTargetType::User);

        $this->assertFalse($result->wasSaved());
        $this->assertSame('missing-target', $result->targetName());
        $this->assertSame($translationKey, $result->translationKey());
    }

    public static function targetTypeProvider(): array
    {
        return [
            'user target' => [GithubTargetType::User],
            'organization target' => [GithubTargetType::Organization],
        ];
    }

    public static function githubFailureProvider(): array
    {
        return [
            'connection exception' => [
                fn () => new ConnectionException('GitHub connection failed.'),
                'targets.request_failed',
            ],
            'request exception' => [
                fn () => new RequestException(new Response(new PsrResponse(404, [], '{}'))),
                'targets.target_not_found',
            ],
        ];
    }
}
