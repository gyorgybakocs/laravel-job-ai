<?php

namespace Tests\Feature;

use App\Enums\GithubTargetSyncStatus;
use App\Jobs\SyncGithubTarget;
use App\Models\GithubTarget;
use App\Models\User;
use App\Src\Github\GithubClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SyncGithubTargetJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.github.base_url' => 'https://api.github.test']);
        Http::preventStrayRequests();
    }

    #[DataProvider('successProvider')]
    public function test_job_uses_the_current_token_and_upserts_repositories_without_duplicates(string $type, ?string $token): void
    {
        $user = User::factory()->create(['github_token' => 'old-token']);
        $target = GithubTarget::query()->create(['name' => 'octocat', 'type' => $type]);
        $user->githubTargets()->attach($target);
        $job = new SyncGithubTarget($target->id, $user->id);
        $serialized = serialize($job);
        $this->assertStringNotContainsString('old-token', $serialized);
        $user->update(['github_token' => $token]);
        $this->travelTo(now()->startOfSecond());

        $payload = [
            'id' => 123, 'name' => 'hello-world', 'full_name' => 'octocat/hello-world',
            'html_url' => 'https://github.com/octocat/hello-world', 'stargazers_count' => 1,
        ];
        Http::fake(['https://api.github.test/*' => Http::sequence()
            ->push([$payload])
            ->push([array_replace($payload, ['stargazers_count' => 42])])]);

        app()->call([unserialize($serialized), 'handle']);
        $this->assertDatabaseHas('github_repositories', ['external_id' => 123, 'stargazers_count' => 1]);
        app()->call([$job, 'handle']);

        $this->assertDatabaseCount('github_repositories', 1);
        $this->assertDatabaseHas('github_repositories', ['github_target_id' => $target->id, 'external_id' => 123, 'stargazers_count' => 42]);
        $this->assertDatabaseCount('github_target_user', 1);
        $target->refresh();
        $this->assertSame(GithubTargetSyncStatus::Synced, $target->sync_status);
        $this->assertTrue($target->last_synced_at->equalTo(now()));
        $this->assertNull($target->last_error);
        $this->assertNull(app(GithubClient::class)->getToken());
        $path = $type === 'org' ? 'orgs' : 'users';
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), "https://api.github.test/{$path}/octocat/repos?")
            && ($token === null ? ! $request->hasHeader('Authorization') : $request->hasHeader('Authorization', 'Bearer '.$token)));
        Http::assertSentCount(2);
    }

    public static function successProvider(): array
    {
        return [
            'user with updated token' => ['user', 'new-token'],
            'organization with updated token' => ['org', 'new-token'],
            'user without token' => ['user', null],
            'organization without token' => ['org', null],
        ];
    }

    #[DataProvider('failureProvider')]
    public function test_job_preserves_repositories_and_records_failure_before_rethrowing(?int $status): void
    {
        $this->travelTo(now()->startOfSecond());
        $lastSyncedAt = now()->subDay();
        $user = User::factory()->create(['github_token' => 'test-token']);
        $target = GithubTarget::query()->create([
            'name' => 'octocat', 'type' => 'user',
            'sync_status' => GithubTargetSyncStatus::Synced, 'last_synced_at' => $lastSyncedAt,
        ]);
        $user->githubTargets()->attach($target);
        $target->repositories()->create([
            'external_id' => 123, 'name' => 'hello-world', 'full_name' => 'octocat/hello-world',
            'html_url' => 'https://github.com/octocat/hello-world', 'stargazers_count' => 42,
        ]);
        Http::fake(['https://api.github.test/*' => $status === null ? Http::failedConnection() : Http::response([], $status)]);

        try {
            app()->call([new SyncGithubTarget($target->id, $user->id), 'handle']);
            $this->fail('The job must propagate the failure to the queue worker.');
        } catch (ConnectionException|RequestException $exception) {
            $target->refresh();
            $this->assertSame(GithubTargetSyncStatus::Failed, $target->sync_status);
            $this->assertSame($exception->getMessage(), $target->last_error);
        }

        $this->assertTrue($target->last_synced_at->equalTo($lastSyncedAt));
        $this->assertDatabaseCount('github_repositories', 1);
        $this->assertDatabaseHas('github_repositories', ['external_id' => 123, 'stargazers_count' => 42]);
        $this->assertDatabaseCount('github_target_user', 1);
        $this->assertNull(app(GithubClient::class)->getToken());
    }

    public static function failureProvider(): array
    {
        return [
            'not found' => [404], 'invalid token' => [401], 'forbidden' => [403],
            'rate limited' => [429], 'server error' => [500], 'unavailable' => [503],
            'connection failure' => [null],
        ];
    }

    #[DataProvider('unavailableRequesterProvider')]
    public function test_job_skips_a_target_that_is_no_longer_accessible(string $reason): void
    {
        $user = User::factory()->create();
        $target = GithubTarget::query()->create(['name' => 'octocat', 'type' => 'user']);
        $user->githubTargets()->attach($target);
        $job = new SyncGithubTarget($target->id, $user->id);

        match ($reason) {
            'deleted user' => $user->delete(),
            'deleted target' => $target->delete(),
            'removed subscription' => $user->githubTargets()->detach($target),
        };

        app()->call([$job, 'handle']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('github_repositories', 0);
        if ($reason !== 'deleted target') {
            $this->assertSame(GithubTargetSyncStatus::Pending, $target->fresh()->sync_status);
        }
    }

    public static function unavailableRequesterProvider(): array
    {
        return [
            'deleted user' => ['deleted user'], 'deleted target' => ['deleted target'],
            'removed subscription' => ['removed subscription'],
        ];
    }
}
