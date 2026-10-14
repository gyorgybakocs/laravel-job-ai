<?php

namespace Tests\Feature;

use App\Enums\GithubSyncOperationStatus;
use App\Enums\GithubTargetSyncStatus;
use App\Jobs\SyncGithubTarget;
use App\Models\GithubSyncOperation;
use App\Models\GithubTarget;
use App\Models\User;
use App\Src\Github\GithubClient;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
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
        $operation = $this->createOperation($user, $target);
        $job = new SyncGithubTarget($operation->id, $target->id);
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
        Http::assertSentCount(1);
        $nextOperation = $this->createOperation($user, $target);
        app()->call([new SyncGithubTarget($nextOperation->id, $target->id), 'handle']);

        $this->assertSame(GithubSyncOperationStatus::Succeeded, $operation->fresh()->status);
        $this->assertNotNull($operation->fresh()->started_at);
        $this->assertNotNull($operation->fresh()->finished_at);
        $this->assertNull($operation->fresh()->error);
        $this->assertSame(GithubSyncOperationStatus::Succeeded, $nextOperation->fresh()->status);
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
    public function test_job_preserves_repositories_and_records_failure_before_rethrowing(?int $status, string $error): void
    {
        $this->travelTo(now()->startOfSecond());
        $lastSyncedAt = now()->subDay();
        $user = User::factory()->create(['github_token' => 'test-token']);
        $target = GithubTarget::query()->create([
            'name' => 'octocat', 'type' => 'user',
            'sync_status' => GithubTargetSyncStatus::Synced, 'last_synced_at' => $lastSyncedAt, 'last_error' => 'old target error',
        ]);
        $user->githubTargets()->attach($target);
        $target->repositories()->create([
            'external_id' => 123, 'name' => 'hello-world', 'full_name' => 'octocat/hello-world',
            'html_url' => 'https://github.com/octocat/hello-world', 'stargazers_count' => 42,
        ]);
        Http::fake(['https://api.github.test/*' => $status === null ? Http::failedConnection() : Http::response([], $status)]);

        $operation = $this->createOperation($user, $target);
        try {
            app()->call([new SyncGithubTarget($operation->id, $target->id), 'handle']);
            $this->fail('The job must propagate the failure to the queue worker.');
        } catch (ConnectionException|RequestException $exception) {
            $target->refresh();
            $this->assertSame(GithubTargetSyncStatus::Synced, $target->sync_status);
            $this->assertSame('old target error', $target->last_error);
            $this->assertSame(GithubSyncOperationStatus::Failed, $operation->fresh()->status);
            $this->assertSame($error, $operation->fresh()->error);
            $this->assertNotNull($operation->fresh()->finished_at);
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
            'not found' => [404, 'targets.target_not_found'],
            'invalid token' => [401, 'targets.invalid_token'],
            'forbidden' => [403, 'targets.access_restricted'],
            'rate limited' => [429, 'targets.access_restricted'],
            'server error' => [500, 'targets.request_failed'],
            'unavailable' => [503, 'targets.request_failed'],
            'connection failure' => [null, 'targets.request_failed'],
        ];
    }

    #[DataProvider('unavailableRequesterProvider')]
    public function test_job_skips_a_target_that_is_no_longer_accessible(string $reason): void
    {
        $user = User::factory()->create();
        $target = GithubTarget::query()->create(['name' => 'octocat', 'type' => 'user']);
        $user->githubTargets()->attach($target);
        $operation = $this->createOperation($user, $target);
        $job = new SyncGithubTarget($operation->id, $target->id);

        match ($reason) {
            'deleted user' => $user->delete(),
            'deleted target' => $target->delete(),
            'removed subscription' => $user->githubTargets()->detach($target),
        };

        app()->call([$job, 'handle']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('github_repositories', 0);
        if ($reason !== 'deleted user') {
            $this->assertSame(GithubSyncOperationStatus::Failed, $operation->fresh()->status);
            $this->assertSame('targets.target_not_found', $operation->fresh()->error);
        }
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

    public function test_shared_target_jobs_wait_for_the_same_lock_and_resume_after_release(): void
    {
        $users = User::factory()->count(2)->create();
        $target = GithubTarget::query()->create(['name' => 'octocat', 'type' => 'user']);
        $target->users()->attach($users->pluck('id'));
        $firstOperation = $this->createOperation($users[0], $target);
        $secondOperation = $this->createOperation($users[1], $target);
        $firstJob = new SyncGithubTarget($firstOperation->id, $target->id);
        $secondJob = new SyncGithubTarget($secondOperation->id, $target->id);
        $middleware = $firstJob->middleware()[0];
        $this->assertSame($middleware->getLockKey($firstJob), $secondJob->middleware()[0]->getLockKey($secondJob));
        $lock = Cache::lock($middleware->getLockKey($firstJob), config('services.github.sync_lock_seconds'));
        $this->assertTrue($lock->get());
        $queueJob = Mockery::mock(Job::class);
        $queueJob->shouldReceive('release')->once()->with(config('services.github.sync_lock_retry_delay'));
        $secondJob->setJob($queueJob);

        try {
            $middleware->handle($secondJob, fn () => $this->fail('The second job must wait for the lock.'));
            $this->assertSame(GithubSyncOperationStatus::Queued, $secondOperation->fresh()->status);
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }

        Http::fake(['https://api.github.test/*' => Http::response([])]);
        $middleware->handle($secondJob, fn ($job) => app()->call([$job, 'handle']));
        $this->assertSame(GithubSyncOperationStatus::Succeeded, $secondOperation->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_job_rolls_back_all_repository_changes_when_mapping_fails(): void
    {
        $user = User::factory()->create(['github_token' => 'test-token']);
        $target = GithubTarget::query()->create(['name' => 'octocat', 'type' => 'user']);
        $user->githubTargets()->attach($target);
        $operation = $this->createOperation($user, $target);
        Http::fake(['https://api.github.test/*' => Http::response([
            ['id' => 1, 'name' => 'valid', 'full_name' => 'octocat/valid', 'html_url' => 'https://github.com/octocat/valid'],
            ['id' => 2, 'name' => 'invalid'],
        ])]);

        try {
            app()->call([new SyncGithubTarget($operation->id, $target->id), 'handle']);
            $this->fail('Invalid repository data must fail the job.');
        } catch (\Throwable $exception) {
            $this->assertNotInstanceOf(AssertionFailedError::class, $exception);
        }

        $this->assertDatabaseCount('github_repositories', 0);
        $this->assertNull($target->fresh()->last_synced_at);
        $this->assertNull($target->fresh()->last_error);
        $this->assertSame(GithubSyncOperationStatus::Failed, $operation->fresh()->status);
        $this->assertSame('targets.request_failed', $operation->fresh()->error);
        $this->assertNull(app(GithubClient::class)->getToken());
    }

    #[DataProvider('failureStateProvider')]
    public function test_worker_failure_hook_finishes_an_unfinished_operation(GithubSyncOperationStatus $status): void
    {
        $user = User::factory()->create();
        $target = GithubTarget::query()->create(['name' => 'octocat', 'type' => 'user']);
        $operation = $this->createOperation($user, $target);
        $operation->update(['status' => $status]);
        (new SyncGithubTarget($operation->id, $target->id))->failed(new RuntimeException('Internal worker failure.'));
        $this->assertSame(GithubSyncOperationStatus::Failed, $operation->fresh()->status);
        $this->assertSame('targets.request_failed', $operation->fresh()->error);
        $this->assertNotNull($operation->fresh()->finished_at);
    }

    public static function failureStateProvider(): array
    {
        return ['queued' => [GithubSyncOperationStatus::Queued], 'running' => [GithubSyncOperationStatus::Running]];
    }

    private function createOperation(User $user, GithubTarget $target): GithubSyncOperation
    {
        return $user->githubSyncOperations()->create([
            'github_target_id' => $target->id,
            'target_name' => $target->name,
            'target_type' => $target->type,
        ])->fresh();
    }
}
