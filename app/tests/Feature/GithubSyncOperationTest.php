<?php

namespace Tests\Feature;

use App\Enums\GithubSyncOperationStatus;
use App\Enums\GithubTargetSyncStatus;
use App\Jobs\SyncGithubTarget;
use App\Models\GithubSyncOperation;
use App\Models\GithubTarget;
use App\Models\User;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class GithubSyncOperationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Queue::fake();
    }

    #[DataProvider('activeStateProvider')]
    public function test_repeated_requests_reuse_the_users_active_operation(GithubSyncOperationStatus $status): void
    {
        $user = User::factory()->create();
        $target = $this->targetFor($user);
        $operation = $this->operationFor($user, $target, $status);

        $this->actingAs($user)->post(route('targets.sync', $target))
            ->assertRedirect(route('targets.index'))
            ->assertSessionHas('target_result', trans('targets.sync_already_queued', ['target' => $target->name]));

        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->assertDatabaseCount('github_sync_operations', 1);
        $this->assertSame($operation->id, $user->githubSyncOperations()->first()->id);
        $this->assertSame($status, $operation->fresh()->status);
    }

    public static function activeStateProvider(): array
    {
        return ['queued' => [GithubSyncOperationStatus::Queued], 'running' => [GithubSyncOperationStatus::Running]];
    }

    #[DataProvider('completedStateProvider')]
    public function test_a_completed_attempt_does_not_prevent_a_new_sync(GithubSyncOperationStatus $status): void
    {
        $user = User::factory()->create();
        $target = $this->targetFor($user);
        $previous = $this->operationFor($user, $target, $status);

        $this->actingAs($user)->post(route('targets.sync', $target))->assertRedirect(route('targets.index'));
        $current = $user->githubSyncOperations()->latest('id')->first();

        $this->assertNotSame($previous->id, $current->id);
        $this->assertSame(GithubSyncOperationStatus::Queued, $current->status);
        $this->assertSame($status, $previous->fresh()->status);
        $this->assertDatabaseCount('github_sync_operations', 2);
        Queue::assertPushed(SyncGithubTarget::class, fn ($job) => $job->operationId === $current->id && $job->targetId === $target->id);
        Queue::assertPushed(SyncGithubTarget::class, 1);
        Http::assertNothingSent();
    }

    public static function completedStateProvider(): array
    {
        return ['succeeded' => [GithubSyncOperationStatus::Succeeded], 'failed' => [GithubSyncOperationStatus::Failed]];
    }

    public function test_two_submitted_requests_dispatch_only_one_job(): void
    {
        $user = User::factory()->create();
        $target = $this->targetFor($user);
        $this->actingAs($user);

        for ($i = 0; $i < 2; $i++) {
            $this->post(route('targets.sync', $target))->assertRedirect(route('targets.index'));
        }

        $this->assertDatabaseCount('github_sync_operations', 1);
        Queue::assertPushed(SyncGithubTarget::class, 1);
        Http::assertNothingSent();
    }

    public function test_users_of_a_shared_target_create_separate_operations(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $target = $this->targetFor($first);
        $second->githubTargets()->attach($target);

        foreach ([$first, $second] as $user) {
            $this->actingAs($user)->post(route('targets.sync', $target))->assertRedirect(route('targets.index'));
            $this->assertSame(1, $user->githubSyncOperations()->count());
        }
        $this->assertDatabaseCount('github_sync_operations', 2);
        Queue::assertPushed(SyncGithubTarget::class, 2);
        Http::assertNothingSent();
    }

    public function test_queue_dispatch_failure_is_recorded_and_presented_safely(): void
    {
        $this->mock(Dispatcher::class)->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Secret internal queue details.'));
        $user = User::factory()->create();
        $target = $this->targetFor($user);

        $this->actingAs($user)->post(route('targets.sync', $target))
            ->assertRedirect(route('targets.index'))
            ->assertSessionHas('target_result', trans('targets.request_failed'));
        $operation = $user->githubSyncOperations()->first();
        $this->assertSame(GithubSyncOperationStatus::Failed, $operation->status);
        $this->assertSame('targets.request_failed', $operation->error);
        $this->assertNotNull($operation->finished_at);
        Http::assertNothingSent();
    }

    #[DataProvider('displayStateProvider')]
    public function test_targets_show_only_the_requesting_users_latest_operation(
        GithubSyncOperationStatus $status,
        ?string $error,
        bool $active,
    ): void {
        config(['services.github.sync_poll_interval' => 1234]);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $target = $this->targetFor($first);
        $second->githubTargets()->attach($target);
        $target->update(['last_error' => 'Raw shared target exception.']);
        $this->operationFor($first, $target, GithubSyncOperationStatus::Succeeded);
        $own = $this->operationFor($first, $target, $status, $error);
        $other = $this->operationFor($second, $target, GithubSyncOperationStatus::Failed, 'targets.access_restricted');

        $this->actingAs($first)->get(route('targets.index'))->assertInertia(fn (Assert $page) => $page
            ->where('syncPollInterval', 1234)
            ->where('hasActiveSyncOperations', $active)
            ->where('users.data.0.sync_operation.id', $own->id)
            ->where('users.data.0.sync_operation.status', $status->value)
            ->where('users.data.0.last_error', $error === null ? null : trans($error, ['target' => $target->name])));

        $this->actingAs($second)->get(route('targets.index'))->assertInertia(fn (Assert $page) => $page
            ->where('hasActiveSyncOperations', false)
            ->where('users.data.0.sync_operation.id', $other->id)
            ->where('users.data.0.last_error', trans('targets.access_restricted')));
        Http::assertNothingSent();
    }

    public static function displayStateProvider(): array
    {
        return [
            'queued' => [GithubSyncOperationStatus::Queued, null, true],
            'running' => [GithubSyncOperationStatus::Running, null, true],
            'succeeded' => [GithubSyncOperationStatus::Succeeded, null, false],
            'failed' => [GithubSyncOperationStatus::Failed, 'targets.invalid_token', false],
        ];
    }

    public function test_other_users_operations_and_removed_targets_do_not_start_polling(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $target = $this->targetFor($first);
        $second->githubTargets()->attach($target);
        $this->operationFor($second, $target, GithubSyncOperationStatus::Queued);
        $removedTarget = $this->targetFor($first, 'removed');
        $this->operationFor($first, $removedTarget, GithubSyncOperationStatus::Running);
        $first->githubTargets()->detach($removedTarget);

        $this->actingAs($first)->get(route('targets.index'))->assertInertia(fn (Assert $page) => $page
            ->where('hasActiveSyncOperations', false)
            ->has('users.data', 1)
            ->where('users.data.0.sync_operation', null)
            ->where('users.data.0.last_error', null));
    }

    public function test_an_active_operation_on_another_page_is_still_followed(): void
    {
        config(['services.github.targets_per_page' => 1]);
        $user = User::factory()->create();
        $first = $this->targetFor($user, 'first');
        $second = $this->targetFor($user, 'second');
        $operation = $this->operationFor($user, $first, GithubSyncOperationStatus::Running);
        $this->actingAs($user)->get(route('targets.index'))->assertInertia(fn (Assert $page) => $page
            ->where('users.data.0.id', $second->id)
            ->where('hasActiveSyncOperations', true));

        $operation->update(['status' => GithubSyncOperationStatus::Succeeded]);
        $this->get(route('targets.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => \Inertia\Inertia::getVersion(),
            'X-Inertia-Partial-Component' => 'targets/Index',
            'X-Inertia-Partial-Data' => 'users,orgs,hasActiveSyncOperations,flash',
        ])->assertOk()->assertJsonPath('props.hasActiveSyncOperations', false);
    }

    private function targetFor(User $user, string $name = 'octocat'): GithubTarget
    {
        $target = GithubTarget::query()->create([
            'name' => $name, 'type' => 'user', 'sync_status' => GithubTargetSyncStatus::Synced,
        ]);
        $user->githubTargets()->attach($target);

        return $target;
    }

    private function operationFor(User $user, GithubTarget $target, GithubSyncOperationStatus $status, ?string $error = null): GithubSyncOperation
    {
        return $user->githubSyncOperations()->create([
            'github_target_id' => $target->id, 'target_name' => $target->name,
            'target_type' => $target->type, 'status' => $status, 'error' => $error,
        ])->fresh();
    }
}
