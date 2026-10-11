<?php

namespace Tests\Feature;

use App\Enums\GithubTargetSyncStatus;
use App\Enums\GithubTargetType;
use App\Models\GithubRepository;
use App\Models\GithubTarget;
use App\Models\User;
use App\Models\UserGithubTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GithubTargetModelTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('subscriptionProvider')]
    public function test_users_can_share_a_github_target(int $userCount, GithubTargetType $type): void
    {
        $users = User::factory()->count($userCount)->create();
        $target = GithubTarget::query()->create(['name' => 'example', 'type' => $type]);
        $target->users()->attach($users->pluck('id'));

        $this->assertSame($userCount, $target->users()->count());
        $this->assertSame(1, GithubTarget::query()->count());
        foreach ($users as $user) {
            $this->assertTrue($user->githubTargets()->whereKey($target)->exists());
            $this->assertTrue($target->users()->whereKey($user)->exists());
        }
    }

    public static function subscriptionProvider(): array
    {
        return [
            'one user follows a user' => [1, GithubTargetType::User],
            'one user follows an organization' => [1, GithubTargetType::Organization],
            'multiple users follow a user' => [2, GithubTargetType::User],
            'multiple users follow an organization' => [2, GithubTargetType::Organization],
        ];
    }

    public function test_user_github_target_subscriptions_can_be_queried_from_user_and_target(): void
    {
        $user = User::factory()->create();
        $target = GithubTarget::query()->create([
            'name' => 'laravel',
            'type' => GithubTargetType::Organization,
        ]);

        $user->githubTargets()->attach($target);

        $userSubscription = $user->githubTargetSubscriptions()->first();
        $targetSubscription = $target->userSubscriptions()->first();

        $this->assertInstanceOf(UserGithubTarget::class, $userSubscription);
        $this->assertInstanceOf(UserGithubTarget::class, $targetSubscription);
        $this->assertTrue($userSubscription->is($targetSubscription));
        $this->assertSame($user->id, $userSubscription->user_id);
        $this->assertSame($target->id, $userSubscription->github_target_id);
    }

    public function test_user_github_target_model_belongs_to_user_and_github_target(): void
    {
        $user = User::factory()->create();
        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);

        $subscription = UserGithubTarget::query()->create([
            'user_id' => $user->id,
            'github_target_id' => $target->id,
        ]);

        $this->assertTrue($user->is($subscription->user));
        $this->assertTrue($target->is($subscription->githubTarget));
    }

    public function test_the_same_user_cannot_attach_the_same_github_target_twice(): void
    {
        $user = User::factory()->create();
        $target = GithubTarget::query()->create([
            'name' => 'laravel',
            'type' => GithubTargetType::Organization,
        ]);

        $user->githubTargets()->attach($target);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $user->githubTargets()->attach($target);
    }

    public function test_github_target_can_have_repositories(): void
    {
        $target = GithubTarget::query()->create([
            'name' => 'laravel',
            'type' => GithubTargetType::Organization,
        ]);

        $repository = $target->repositories()->create([
            'external_id' => 123,
            'name' => 'framework',
            'full_name' => 'laravel/framework',
            'description' => 'The Laravel Framework.',
            'html_url' => 'https://github.com/laravel/framework',
            'language' => 'PHP',
            'stargazers_count' => 80000,
            'open_issues_count' => 10,
            'archived' => false,
            'external_updated_at' => now(),
        ]);

        $this->assertTrue($repository->is($target->repositories()->first()));
        $this->assertTrue($target->is($repository->githubTarget));
    }

    public function test_github_target_enum_casts_work(): void
    {
        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
            'sync_status' => GithubTargetSyncStatus::Synced,
        ]);

        $target->refresh();

        $this->assertSame(GithubTargetType::User, $target->type);
        $this->assertSame(GithubTargetSyncStatus::Synced, $target->sync_status);
    }

    public function test_github_repository_casts_work(): void
    {
        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);

        $repository = GithubRepository::query()->create([
            'github_target_id' => $target->id,
            'external_id' => 123,
            'name' => 'hello-world',
            'full_name' => 'octocat/hello-world',
            'html_url' => 'https://github.com/octocat/hello-world',
            'stargazers_count' => 42,
            'open_issues_count' => 3,
            'archived' => true,
            'external_updated_at' => now(),
        ]);

        $repository->refresh();

        $this->assertIsInt($repository->external_id);
        $this->assertIsInt($repository->stargazers_count);
        $this->assertIsInt($repository->open_issues_count);
        $this->assertTrue($repository->archived);
        $this->assertNotNull($repository->external_updated_at);
    }

    public function test_github_target_type_and_name_are_unique_together(): void
    {
        GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);

        GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::Organization,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);
    }
}
