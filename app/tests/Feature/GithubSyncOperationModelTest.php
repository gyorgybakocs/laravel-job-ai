<?php

namespace Tests\Feature;

use App\Enums\GithubSyncOperationStatus;
use App\Enums\GithubTargetType;
use App\Models\GithubSyncOperation;
use App\Models\GithubTarget;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GithubSyncOperationModelTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('targetTypeProvider')]
    public function test_an_operation_can_be_saved_before_a_target_exists(GithubTargetType $type): void
    {
        $user = User::factory()->create();
        $operation = $user->githubSyncOperations()->create([
            'target_name' => 'example',
            'target_type' => $type,
        ])->fresh();

        $this->assertSame(GithubSyncOperationStatus::Queued, $operation->status);
        $this->assertSame($type, $operation->target_type);
        $this->assertSame('example', $operation->target_name);
        $this->assertTrue($user->is($operation->user));
        $this->assertNull($operation->github_target_id);
        $this->assertNull($operation->githubTarget);
        $this->assertNull($operation->error);
        $this->assertNull($operation->started_at);
        $this->assertNull($operation->finished_at);
        $this->assertSame(0, GithubTarget::query()->count());
    }

    public static function targetTypeProvider(): array
    {
        return [
            'user' => [GithubTargetType::User],
            'organization' => [GithubTargetType::Organization],
        ];
    }

    #[DataProvider('statusProvider')]
    public function test_operation_status_and_timestamps_are_persisted(
        GithubSyncOperationStatus $status,
        bool $started,
        bool $finished,
        ?string $error,
    ): void {
        $user = User::factory()->create();
        $startedAt = $started ? now()->startOfSecond() : null;
        $finishedAt = $finished ? now()->addMinute()->startOfSecond() : null;
        $operation = $user->githubSyncOperations()->create([
            'target_name' => 'example',
            'target_type' => GithubTargetType::User,
            'status' => $status,
            'error' => $error,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ])->fresh();

        $this->assertSame($status, $operation->status);
        $this->assertSame($error, $operation->error);
        $this->assertSame($startedAt?->toIso8601String(), $operation->started_at?->toIso8601String());
        $this->assertSame($finishedAt?->toIso8601String(), $operation->finished_at?->toIso8601String());
    }

    public static function statusProvider(): array
    {
        return [
            'queued' => [GithubSyncOperationStatus::Queued, false, false, null],
            'running' => [GithubSyncOperationStatus::Running, true, false, null],
            'succeeded' => [GithubSyncOperationStatus::Succeeded, true, true, null],
            'failed' => [GithubSyncOperationStatus::Failed, true, true, 'targets.invalid_token'],
        ];
    }

    public function test_shared_target_operations_keep_separate_user_errors(): void
    {
        $users = User::factory()->count(2)->create();
        $target = GithubTarget::query()->create(['name' => 'example', 'type' => GithubTargetType::User]);
        $operations = [];

        foreach ($users as $index => $user) {
            $operations[] = $user->githubSyncOperations()->create([
                'github_target_id' => $target->id,
                'target_name' => $target->name,
                'target_type' => $target->type,
                'status' => $index === 0 ? GithubSyncOperationStatus::Failed : GithubSyncOperationStatus::Succeeded,
                'error' => $index === 0 ? 'targets.invalid_token' : null,
            ]);
        }

        $this->assertSame(2, $target->githubSyncOperations()->count());
        foreach ($users as $index => $user) {
            $this->assertSame(1, $user->githubSyncOperations()->count());
            $this->assertTrue($operations[$index]->is($user->githubSyncOperations()->first()));
            $this->assertTrue($target->is($operations[$index]->githubTarget));
        }
        $this->assertSame('targets.invalid_token', $operations[0]->fresh()->error);
        $this->assertNull($operations[1]->fresh()->error);
        $this->assertNull($target->fresh()->last_error);
    }

    #[DataProvider('deletionProvider')]
    public function test_deleting_a_parent_handles_operation_ownership(string $parent): void
    {
        $user = User::factory()->create();
        $target = GithubTarget::query()->create(['name' => 'example', 'type' => GithubTargetType::User]);
        $operation = $user->githubSyncOperations()->create([
            'github_target_id' => $target->id,
            'target_name' => $target->name,
            'target_type' => $target->type,
        ]);

        if ($parent === 'user') {
            $user->delete();
            $this->assertModelMissing($operation);
            $this->assertModelExists($target);
        } else {
            $target->delete();
            $operation->refresh();
            $this->assertNull($operation->github_target_id);
            $this->assertNull($operation->githubTarget);
            $this->assertSame('example', $operation->target_name);
            $this->assertSame(GithubTargetType::User, $operation->target_type);
            $this->assertTrue($user->is($operation->user));
        }
    }

    public static function deletionProvider(): array
    {
        return ['user deletion' => ['user'], 'target deletion' => ['target']];
    }

    #[DataProvider('invalidForeignKeyProvider')]
    public function test_operations_require_existing_referenced_records(string $foreignKey): void
    {
        $user = User::factory()->create();
        $attributes = [
            'user_id' => $user->id,
            'target_name' => 'example',
            'target_type' => GithubTargetType::User,
        ];
        $attributes[$foreignKey] = 999999;

        $this->expectException(QueryException::class);
        GithubSyncOperation::query()->create($attributes);
    }

    public static function invalidForeignKeyProvider(): array
    {
        return ['missing user' => ['user_id'], 'missing target' => ['github_target_id']];
    }
}
