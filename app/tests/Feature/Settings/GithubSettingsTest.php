<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GithubSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('githubSettingsProvider')]
    public function test_github_settings_can_be_updated(
        ?string $existingToken,
        string $submittedToken,
        ?string $expectedToken,
    ): void
    {
        $user = User::factory()->create([
            'github_username' => 'old-user',
            'github_token' => $existingToken,
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('github.update'), [
                'github_username' => 'new-user',
                'github_token' => $submittedToken,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('github.edit'));

        $user->refresh();

        $this->assertSame('new-user', $user->github_username);
        $this->assertSame($expectedToken, $user->github_token);
        $this->assertSame(trans('github.saved'), session('status'));
    }

    public static function githubSettingsProvider(): array
    {
        return [
            'token is saved when submitted' => [null, 'github-token', 'github-token'],
            'existing token is kept when submitted blank' => ['existing-token', '', 'existing-token'],
        ];
    }

    #[DataProvider('invalidGithubSettingsProvider')]
    public function test_github_settings_validation(array $payload, string $expectedErrorField): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('github.edit'))
            ->patch(route('github.update'), $payload);

        $response
            ->assertSessionHasErrors($expectedErrorField)
            ->assertRedirect(route('github.edit'));
    }

    public static function invalidGithubSettingsProvider(): array
    {
        return [
            'username longer than 39 characters' => [
                [
                    'github_username' => Str::repeat('a', 40),
                    'github_token' => 'github-token',
                ],
                'github_username',
            ],
            'username cannot start with hyphen' => [
                [
                    'github_username' => '-octocat',
                    'github_token' => 'github-token',
                ],
                'github_username',
            ],
            'username cannot end with hyphen' => [
                [
                    'github_username' => 'octocat-',
                    'github_token' => 'github-token',
                ],
                'github_username',
            ],
            'username cannot contain underscores' => [
                [
                    'github_username' => 'octo_cat',
                    'github_token' => 'github-token',
                ],
                'github_username',
            ],
            'token longer than 255 characters' => [
                [
                    'github_username' => 'octocat',
                    'github_token' => Str::repeat('a', 256),
                ],
                'github_token',
            ],
        ];
    }

    public function test_guests_cannot_view_or_update_github_settings(): void
    {
        $this->get(route('github.edit'))->assertRedirect(route('login'));
        $this->patch(route('github.update'), [])->assertRedirect(route('login'));
    }

    public function test_page_shares_username_but_hides_encrypted_token(): void
    {
        $user = User::factory()->create(['github_username' => 'octocat', 'github_token' => 'secret-token']);
        $this->assertNotSame('secret-token', $user->getRawOriginal('github_token'));
        $this->assertArrayNotHasKey('github_token', $user->toArray());
        $this->actingAs($user)->get(route('github.edit'))->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('settings/Github')
                ->where('githubUsername', 'octocat')
                ->where('lang.github.menu', 'GitHub')
                ->missing('githubToken')
                ->missing('auth.user.github_token'));
    }
}
