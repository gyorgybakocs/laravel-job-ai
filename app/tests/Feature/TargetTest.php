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
            ->component('targets/Index')->has('targets', 1)
            ->where('targets.0.name', 'octocat')
            ->where('targets.0.type', $type)
            ->where('targets.0.sync_status', 'synced')
            ->where('targets.0.repositories_count', 1));
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
}
