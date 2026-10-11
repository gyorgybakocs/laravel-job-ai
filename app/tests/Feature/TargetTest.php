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

        $this->get(route('targets.index'))->assertInertia(fn (Assert $page) => $page
            ->where('flash.targetResult', trans("targets.{$message}", ['target' => 'octocat'])));
    }

    public static function lookupProvider(): array
    {
        return [
            'user found with no repositories' => [200, 'user', 'target_found'],
            'organization found' => [200, 'org', 'target_found'],
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
}
