<?php

namespace Tests\Unit\Services;

use App\Enums\GithubTargetSyncStatus;
use App\Enums\GithubTargetType;
use App\Models\GithubTarget;
use App\Models\User;
use App\Repositories\GithubRepositoryRepository;
use App\Repositories\GithubTargetRepository;
use App\Services\AddGithubTargetForUser;
use App\Src\Github\GithubClient;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\ConnectionException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AddGithubTargetForUserTest extends TestCase
{
    #[DataProvider('targetTypeProvider')]
    public function test_fetch_precedes_the_transaction_and_target_is_returned(GithubTargetType $type): void
    {
        $user = new User();
        $user->github_token = 'github-token';
        $target = new GithubTarget(['name' => 'octocat', 'type' => $type]);
        $client = Mockery::mock(GithubClient::class);
        $client->shouldReceive('setToken')->once()->with('github-token')->globally()->ordered()->andReturnSelf();
        $client->shouldReceive('getRepositories')->once()->with('octocat', $type->value)->globally()->ordered()->andReturn([]);
        $client->shouldReceive('setToken')->once()->with(null)->globally()->ordered()->andReturnSelf();
        $database = Mockery::mock(ConnectionInterface::class);
        $database->shouldReceive('transaction')->once()->globally()->ordered()->andReturnUsing(fn ($callback) => $callback());
        $targets = Mockery::mock(GithubTargetRepository::class);
        $targets->shouldReceive('findOrCreateByTypeAndName')->once()->with($type, 'octocat')->andReturn($target);
        $targets->shouldReceive('attachToUser')->once()->with($user, $target);
        $targets->shouldReceive('save')->once()->with($target);
        $repositories = Mockery::mock(GithubRepositoryRepository::class);
        $repositories->shouldReceive('upsertManyForTarget')->once()->with($target, []);

        $this->assertSame($target, (new AddGithubTargetForUser($database, $targets, $repositories, $client))->add($user, 'octocat', $type));
        $this->assertSame(GithubTargetSyncStatus::Synced, $target->sync_status);
        $this->assertNotNull($target->last_synced_at);
    }

    #[DataProvider('targetTypeProvider')]
    public function test_failed_fetch_resets_token_without_starting_a_transaction(GithubTargetType $type): void
    {
        $client = Mockery::mock(GithubClient::class);
        $client->shouldReceive('setToken')->with(null)->twice()->andReturnSelf();
        $client->shouldReceive('getRepositories')->once()->with('missing', $type->value)->andThrow(new ConnectionException('Unavailable'));
        $database = Mockery::mock(ConnectionInterface::class);
        $database->shouldNotReceive('transaction');
        $service = new AddGithubTargetForUser($database, Mockery::mock(GithubTargetRepository::class), Mockery::mock(GithubRepositoryRepository::class), $client);
        $this->expectException(ConnectionException::class);
        $service->add(new User(), 'missing', $type);
    }

    public static function targetTypeProvider(): array
    {
        return ['user' => [GithubTargetType::User], 'organization' => [GithubTargetType::Organization]];
    }
}
