<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TargetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.github.base_url' => 'https://api.github.test']);
        Http::preventStrayRequests();
    }

    public function test_guests_cannot_view_or_check_targets(): void
    {
        $this->get(route('targets.index'))->assertRedirect(route('login'));
        $this->post(route('targets.store'), [])->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_targets_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())->get(route('targets.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('targets/Index'));
    }

    #[DataProvider('lookupProvider')]
    public function test_lookup_reports_success_or_a_safe_error(?int $status, string $type, string $message): void
    {
        $user = User::factory()->create(['github_token' => 'test-token']);
        Http::fake(['https://api.github.test/*' => $status === null
            ? Http::failedConnection()
            : Http::response([], $status)]);

        $this->actingAs($user)->post(route('targets.store'), ['name' => 'octocat', 'type' => $type])
            ->assertSessionHasNoErrors()->assertRedirect(route('targets.index'))
            ->assertSessionHas('target_result', trans("targets.{$message}", ['target' => 'octocat']));

        if ($status !== null) {
            $path = $type === 'org' ? 'orgs' : 'users';
            Http::assertSent(fn (Request $request) =>
                str_starts_with($request->url(), "https://api.github.test/{$path}/octocat/repos?")
                && $request->hasHeader('Authorization', 'Bearer test-token'));
            Http::assertSentCount(1);
        }

        $this->assertDatabaseCount('github_targets', $status === 200 ? 1 : 0);
        $this->assertDatabaseCount('github_target_user', $status === 200 ? 1 : 0);
        $this->assertNull(app(\App\Src\Github\GithubClient::class)->getToken());

        $this->get(route('targets.index'))->assertInertia(fn (Assert $page) => $page
            ->where('flash.targetResult', trans("targets.{$message}", ['target' => 'octocat'])));
    }

    public static function lookupProvider(): array
    {
        return [
            'user found with no repositories' => [200, 'user', 'target_saved'],
            'organization found' => [200, 'org', 'target_saved'],
            'missing target' => [404, 'user', 'target_not_found'],
            'invalid token' => [401, 'user', 'invalid_token'],
            'forbidden or rate limited' => [403, 'user', 'access_restricted'],
            'rate limited' => [429, 'user', 'access_restricted'],
            'server error' => [500, 'user', 'request_failed'],
            'service unavailable' => [503, 'user', 'request_failed'],
            'connection failed' => [null, 'user', 'request_failed'],
        ];
    }

    #[DataProvider('invalidTargetProvider')]
    public function test_invalid_targets_are_rejected_before_calling_github(array $payload, string $field): void
    {
        $this->actingAs(User::factory()->create())->from(route('targets.index'))
            ->post(route('targets.store'), $payload)
            ->assertSessionHasErrors($field)->assertRedirect(route('targets.index'));
        Http::assertNothingSent();
    }

    public static function invalidTargetProvider(): array
    {
        return [
            'missing name' => [['type' => 'user'], 'name'],
            'invalid name' => [['name' => '../invalid', 'type' => 'user'], 'name'],
            'long name' => [['name' => str_repeat('a', 40), 'type' => 'user'], 'name'],
            'invalid type' => [['name' => 'octocat', 'type' => 'invalid'], 'type'],
        ];
    }

    #[DataProvider('storageProvider')]
    public function test_add_stores_repositories_without_duplicates_and_lists_only_followed_targets(string $type, bool $anotherUser): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $unrelated = \App\Models\GithubTarget::query()->create(['name' => 'unrelated', 'type' => $type]);
        $other->githubTargets()->attach($unrelated);

        $payload = ['id' => 123, 'name' => 'hello-world', 'full_name' => 'octocat/hello-world',
            'html_url' => 'https://github.com/octocat/hello-world', 'stargazers_count' => 1];
        Http::fake(['https://api.github.test/*' => Http::sequence()
            ->push([$payload])
            ->push([array_replace($payload, ['stargazers_count' => 42])])]);

        foreach ([$user, $anotherUser ? $other : $user] as $requester) {
            $this->actingAs($requester)->post(route('targets.store'), ['name' => 'octocat', 'type' => $type])
                ->assertSessionHasNoErrors()->assertRedirect(route('targets.index'));
        }

        $this->assertDatabaseCount('github_targets', 2);
        $this->assertDatabaseCount('github_target_user', $anotherUser ? 3 : 2);
        $this->assertDatabaseCount('github_repositories', 1);
        $this->assertDatabaseHas('github_repositories', ['external_id' => 123, 'stargazers_count' => 42]);
        $target = $user->githubTargets()->first();
        $this->assertSame(\App\Enums\GithubTargetSyncStatus::Synced, $target->sync_status);
        $this->assertNotNull($target->last_synced_at);
        $this->assertNull($target->last_error);

        $this->actingAs($user)->get(route('targets.index'))->assertInertia(fn (Assert $page) => $page
            ->component('targets/Index')->has(($type === 'user' ? 'users' : 'orgs').'.data', 1)
            ->where(($type === 'user' ? 'users' : 'orgs').'.data.0.name', 'octocat')
            ->where(($type === 'user' ? 'users' : 'orgs').'.data.0.type', $type)
            ->where(($type === 'user' ? 'users' : 'orgs').'.data.0.status', 'synced')
            ->where(($type === 'user' ? 'users' : 'orgs').'.data.0.repositories_count', 1));
    }

    public static function storageProvider(): array
    {
        return [
            'repeat user target' => ['user', false],
            'repeat organization target' => ['org', false],
            'shared user target' => ['user', true],
            'shared organization target' => ['org', true],
        ];
    }

    #[DataProvider('lookupProvider')]
    public function test_manual_sync_handles_github_results_and_clears_the_token(?int $status, string $type, string $message): void
    {
        $user = User::factory()->create(['github_token' => 'test-token']);
        $target = \App\Models\GithubTarget::query()->create(['name' => 'octocat', 'type' => $type]);
        $user->githubTargets()->attach($target);
        Http::fake(['https://api.github.test/*' => $status === null ? Http::failedConnection() : Http::response([], $status)]);
        $expected = $status === 200 ? 'sync_success' : $message;

        $this->actingAs($user)->post(route('targets.sync', $target))->assertRedirect(route('targets.index'))
            ->assertSessionHas('target_result', trans("targets.{$expected}", ['target' => 'octocat']));
        $target->refresh();
        $this->assertSame($status === 200 ? \App\Enums\GithubTargetSyncStatus::Synced : \App\Enums\GithubTargetSyncStatus::Failed, $target->sync_status);
        $this->assertNull(app(\App\Src\Github\GithubClient::class)->getToken());
        if ($status !== 200) {
            $this->assertNotNull($target->last_error);
            $group = $type === 'user' ? 'users' : 'orgs';
            $this->get(route('targets.index'))->assertInertia(fn (Assert $page) => $page
                ->where("{$group}.data.0.last_error", trans('targets.request_failed')));
        }
    }

    #[DataProvider('targetActionProvider')]
    public function test_target_actions_require_a_subscription_and_removal_keeps_shared_data(string $action, string $access): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $target = \App\Models\GithubTarget::query()->create(['name' => 'octocat', 'type' => 'user']);
        $other->githubTargets()->attach($target);
        $target->repositories()->create(['external_id' => 123, 'name' => 'hello-world', 'full_name' => 'octocat/hello-world', 'html_url' => 'https://github.com/octocat/hello-world']);
        if ($access === 'subscribed') $user->githubTargets()->attach($target);
        if ($access !== 'guest') $this->actingAs($user);
        Http::fake(['https://api.github.test/*' => Http::response([], 200)]);

        $response = $action === 'sync' ? $this->post(route('targets.sync', $target)) : $this->delete(route('targets.destroy', $target));
        if ($access === 'guest') $response->assertRedirect(route('login'));
        elseif ($access === 'unsubscribed') $response->assertNotFound();
        else $response->assertRedirect(route('targets.index'));
        if ($access !== 'subscribed' || $action === 'destroy') Http::assertNothingSent();
        $this->assertTrue($target->fresh()->users()->whereKey($other)->exists());
        $this->assertDatabaseCount('github_targets', 1);
        $this->assertDatabaseCount('github_repositories', 1);
        if ($access === 'subscribed' && $action === 'destroy') $this->assertFalse($user->githubTargets()->whereKey($target)->exists());
    }

    public static function targetActionProvider(): array
    {
        return [
            'guest sync' => ['sync', 'guest'], 'guest removal' => ['destroy', 'guest'],
            'unauthorized sync' => ['sync', 'unsubscribed'], 'unauthorized removal' => ['destroy', 'unsubscribed'],
            'subscribed sync' => ['sync', 'subscribed'], 'subscribed removal' => ['destroy', 'subscribed'],
        ];
    }

    #[DataProvider('pageProvider')]
    public function test_target_lists_paginate_independently_and_include_repository_data(int $usersPage, int $orgsPage): void
    {
        config(['services.github.targets_per_page' => 2]);
        $user = User::factory()->create();
        foreach (['user', 'org'] as $type) {
            for ($i = 1; $i <= 3; $i++) {
                $target = \App\Models\GithubTarget::query()->create(['name' => "{$type}-{$i}", 'type' => $type]);
                $user->githubTargets()->attach($target);
                $target->repositories()->create(['external_id' => $target->id, 'name' => 'repo', 'full_name' => "{$target->name}/repo", 'html_url' => 'https://github.com/example/repo', 'language' => 'PHP', 'stargazers_count' => 42]);
            }
        }
        $this->actingAs($user)->get(route('targets.index', ['users_page' => $usersPage, 'orgs_page' => $orgsPage]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.current_page', $usersPage)->where('orgs.current_page', $orgsPage)
                ->where('users.total', 3)->where('orgs.total', 3)
                ->has('users.data', $usersPage === 1 ? 2 : 1)->has('orgs.data', $orgsPage === 1 ? 2 : 1)
                ->where('users.data.0.repositories.0.language', 'PHP')
                ->where('orgs.data.0.repositories.0.stars', 42));
        Http::assertNothingSent();
    }

    public static function pageProvider(): array
    {
        return ['first pages' => [1, 1], 'user next page' => [2, 1], 'organization next page' => [1, 2], 'both next pages' => [2, 2]];
    }
}
