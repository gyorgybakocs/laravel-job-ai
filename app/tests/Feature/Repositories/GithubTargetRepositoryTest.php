<?php

namespace Tests\Feature\Repositories;

use App\Enums\GithubTargetSyncStatus;
use App\Enums\GithubTargetType;
use App\Models\GithubTarget;
use App\Models\User;
use App\Repositories\GithubTargetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GithubTargetRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('targetTypeProvider')]
    public function test_find_or_create_by_type_and_name_reuses_existing_targets(GithubTargetType $type): void
    {
        $repository = new GithubTargetRepository();

        $firstTarget = $repository->findOrCreateByTypeAndName($type, 'octocat');
        $secondTarget = $repository->findOrCreateByTypeAndName($type, 'octocat');

        $this->assertTrue($firstTarget->is($secondTarget));
        $this->assertSame(1, GithubTarget::query()->where('type', $type->value)->where('name', 'octocat')->count());
        $this->assertSame(GithubTargetSyncStatus::Pending, $firstTarget->sync_status);
    }

    public function test_attach_to_user_is_idempotent(): void
    {
        $user = User::factory()->create();
        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);

        $repository = new GithubTargetRepository();

        $repository->attachToUser($user, $target);
        $repository->attachToUser($user, $target);

        $this->assertSame(1, $user->githubTargets()->whereKey($target)->count());
    }

    public function test_latest_for_user_orders_targets_by_subscription_created_at_descending(): void
    {
        $user = User::factory()->create();
        $oldestTarget = GithubTarget::query()->create([
            'name' => 'oldest',
            'type' => GithubTargetType::User,
        ]);
        $newestTarget = GithubTarget::query()->create([
            'name' => 'newest',
            'type' => GithubTargetType::Organization,
        ]);

        $user->githubTargets()->attach($oldestTarget, [
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        $user->githubTargets()->attach($newestTarget, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targets = (new GithubTargetRepository())->latestForUser($user);

        $this->assertSame([$newestTarget->id, $oldestTarget->id], $targets->pluck('id')->all());
    }

    #[DataProvider('targetSaveProvider')]
    public function test_save_persists_target_changes(
        GithubTargetSyncStatus $status,
        ?string $lastError,
        ?string $lastSyncedAt,
    ): void {
        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);

        $target->sync_status = $status;
        $target->last_error = $lastError;
        $target->last_synced_at = $lastSyncedAt ? Carbon::parse($lastSyncedAt) : null;

        (new GithubTargetRepository())->save($target);

        $target->refresh();

        $this->assertSame($status, $target->sync_status);
        $this->assertSame($lastError, $target->last_error);

        if ($lastSyncedAt === null) {
            $this->assertNull($target->last_synced_at);
        } else {
            $this->assertTrue($target->last_synced_at->equalTo(Carbon::parse($lastSyncedAt)));
        }
    }

    public static function targetTypeProvider(): array
    {
        return [
            'user target' => [GithubTargetType::User],
            'organization target' => [GithubTargetType::Organization],
        ];
    }

    public static function targetSaveProvider(): array
    {
        return [
            'synced target' => [
                GithubTargetSyncStatus::Synced,
                null,
                '2026-10-10T07:00:00Z',
            ],
            'failed target' => [
                GithubTargetSyncStatus::Failed,
                'GitHub request failed.',
                null,
            ],
        ];
    }
}
