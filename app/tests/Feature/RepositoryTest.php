<?php

namespace Tests\Feature;

use App\Enums\GithubTargetType;
use App\Models\GithubTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('searchProvider')]
    public function test_search_matches_the_selected_field_with_filters_and_user_scope(string $field, string $search, int $expected): void
    {
        $user = User::factory()->create();
        foreach (['Roman-tools', 'other', 'hidden-Roman'] as $i => $name) {
            $target = GithubTarget::query()->create(['name' => $name, 'type' => GithubTargetType::User]);
            if ($i < 2) {
                $user->githubTargets()->attach($target);
            }
            foreach ([false, true] as $archived) {
                $target->repositories()->create([
                    'external_id' => $i * 2 + ($archived ? 2 : 1),
                    'name' => 'repo',
                    'full_name' => "{$name}/repo".($archived ? '-archived' : ''),
                    'html_url' => "https://github.com/{$name}/repo",
                    'description' => $i === 1 ? 'Tool with 100% coverage and foo_bar' : 'Other tools',
                    'language' => 'PHP',
                    'archived' => $archived,
                ]);
            }
        }

        $this->actingAs($user)
            ->get(route('repositories.index', [
                'search' => $search,
                'search_in' => $field,
                'status' => 'active',
                'languages' => ['PHP'],
                'sort' => 'name_desc',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search', trim($search))
                ->where('filters.search_in', $field)
                ->where('sort', 'name_desc')
                ->where('repositories.total', $expected));
    }

    public static function searchProvider(): array
    {
        return [
            'target case insensitive partial match' => ['target', ' ROMAN ', 1],
            'description case insensitive partial match' => ['description', 'COVERAGE', 1],
            'percent is literal' => ['description', '100%', 1],
            'underscore is literal' => ['description', 'foo_bar', 1],
            'underscore does not match arbitrary characters' => ['description', 'fooXbar', 0],
            'target search does not search descriptions' => ['target', 'coverage', 0],
            'cleared search returns all filtered repositories' => ['description', '', 2],
        ];
    }


    #[DataProvider('sortProvider')]
    public function test_repositories_are_sorted_with_filters_preserved(string $sort, array $expected): void
    {
        $user = User::factory()->create();
        foreach ([
            ['alpha', 'zulu', 10, '2026-01-01'],
            ['bravo', 'alpha', 30, '2026-03-01'],
            ['charlie', 'bravo', 20, null],
        ] as $i => [$targetName, $name, $stars, $updated]) {
            $target = GithubTarget::query()->create(['name' => $targetName, 'type' => GithubTargetType::User]);
            $user->githubTargets()->attach($target);
            foreach ([false, true] as $archived) {
                $target->repositories()->create([
                    'external_id' => $i * 2 + ($archived ? 2 : 1),
                    'name' => $name,
                    'full_name' => "{$targetName}/{$name}".($archived ? '-archived' : ''),
                    'html_url' => "https://github.com/{$targetName}/{$name}",
                    'language' => 'PHP',
                    'archived' => $archived,
                    'stargazers_count' => $stars,
                    'external_updated_at' => $updated,
                ]);
            }
        }

        $this->actingAs($user)
            ->get(route('repositories.index', ['sort' => $sort, 'status' => 'active', 'languages' => ['PHP']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sort', $sort)
                ->where('filters.status', 'active')
                ->where('filters.languages', ['PHP'])
                ->where('repositories.total', 3)
                ->where('repositories.data', fn ($items) => collect($items)->pluck('name')->all() === $expected));
    }

    public static function sortProvider(): array
    {
        return [
            'target ascending' => ['target_asc', ['zulu', 'alpha', 'bravo']],
            'target descending' => ['target_desc', ['bravo', 'alpha', 'zulu']],
            'name ascending' => ['name_asc', ['alpha', 'bravo', 'zulu']],
            'name descending' => ['name_desc', ['zulu', 'bravo', 'alpha']],
            'updated descending with null last' => ['updated_desc', ['alpha', 'zulu', 'bravo']],
            'stars descending' => ['stars_desc', ['alpha', 'bravo', 'zulu']],
        ];
    }


    public function test_language_options_are_counted_and_sorted_and_multiple_languages_can_be_filtered(): void
    {
        $user = User::factory()->create();
        $target = GithubTarget::query()->create(['name' => 'visible', 'type' => GithubTargetType::User]);
        $hidden = GithubTarget::query()->create(['name' => 'hidden', 'type' => GithubTargetType::User]);
        $user->githubTargets()->attach($target);

        foreach (['Python', 'PHP', 'JavaScript', 'PHP', null] as $i => $language) {
            $target->repositories()->create([
                'external_id' => $i + 1,
                'name' => "repo-{$i}",
                'full_name' => "visible/repo-{$i}",
                'html_url' => "https://github.com/visible/repo-{$i}",
                'language' => $language,
                'archived' => $i === 3,
            ]);
        }
        $hidden->repositories()->create([
            'external_id' => 100,
            'name' => 'hidden',
            'full_name' => 'hidden/hidden',
            'html_url' => 'https://github.com/hidden/hidden',
            'language' => 'PHP',
        ]);

        $this->actingAs($user)
            ->get(route('repositories.index', ['languages' => ['PHP', 'JavaScript']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.languages', ['PHP', 'JavaScript'])
                ->where('repositories.total', 3)
                ->where('languageOptions', [
                    ['name' => 'JavaScript', 'count' => 1],
                    ['name' => 'PHP', 'count' => 2],
                    ['name' => 'Python', 'count' => 1],
                ])
                ->where('repositories.data', fn ($items) => collect($items)->every(
                    fn ($item) => in_array($item['language'], ['PHP', 'JavaScript'], true),
                )));

        $this->get(route('repositories.index', ['status' => 'active', 'languages' => ['PHP']]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('repositories.total', 1)
                ->where('languageOptions.1.count', 1)
                ->where('repositories.data.0.archived', false));

        $this->get(route('repositories.index', ['status' => 'active']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.languages', [])
                ->where('filters.status', 'active')
                ->where('repositories.total', 4));
    }


    public function test_status_filters_apply_before_pagination_and_can_be_cleared(): void
    {
        $user = User::factory()->create();
        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);
        $hiddenTarget = GithubTarget::query()->create([
            'name' => 'other',
            'type' => GithubTargetType::User,
        ]);
        $user->githubTargets()->attach($target);

        for ($i = 1; $i <= 18; $i++) {
            $target->repositories()->create([
                'external_id' => $i,
                'name' => "repo-{$i}",
                'full_name' => "octocat/repo-{$i}",
                'html_url' => "https://github.com/octocat/repo-{$i}",
                'archived' => $i <= 16,
            ]);
        }
        foreach ([true, false] as $archived) {
            $hiddenTarget->repositories()->create([
                'external_id' => $archived ? 101 : 102,
                'name' => $archived ? 'archived' : 'active',
                'full_name' => $archived ? 'other/archived' : 'other/active',
                'html_url' => 'https://github.com/other/repo',
                'archived' => $archived,
            ]);
        }

        foreach (['archived' => 16, 'active' => 2] as $status => $total) {
            $this->actingAs($user)
                ->get(route('repositories.index', ['status' => $status]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filters.status', $status)
                    ->where('repositories.total', $total)
                    ->has('repositories.data', min(15, $total))
                    ->where('repositories.data', fn ($items) => collect($items)->every(
                        fn ($item) => $item['archived'] === ($status === 'archived'),
                    )));
        }

        $this->get(route('repositories.index', ['status' => 'archived', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('repositories.current_page', 2)
                ->has('repositories.data', 1)
                ->where('repositories.data.0.archived', true)
                ->where('repositories.prev_page_url', fn ($url) => str_contains($url, 'status=archived')));

        $this->get(route('repositories.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', null)
                ->where('repositories.total', 18));
    }


    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('repositories.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_targets_sees_an_empty_repository_list(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('repositories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('repositories/Index')
                ->has('repositories.data', 0)
                ->where('repositories.total', 0)
                ->where('lang.repositories.page_title', 'Repositories'));
    }

    public function test_repository_list_second_page_contains_the_remaining_repository(): void
    {
        $this->travelTo(now()->startOfSecond());
        $user = User::factory()->create();
        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);
        $user->githubTargets()->attach($target);

        for ($i = 1; $i <= 16; $i++) {
            $target->repositories()->create([
                'external_id' => $i,
                'name' => "repo-{$i}",
                'full_name' => "octocat/repo-{$i}",
                'html_url' => "https://github.com/octocat/repo-{$i}",
                'external_updated_at' => now()->addMinutes($i),
            ]);
        }

        $this->actingAs($user)
            ->get(route('repositories.index', ['page' => 2]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('repositories/Index')
                ->where('repositories.current_page', 2)
                ->where('repositories.total', 16)
                ->has('repositories.data', 1)
                ->where('repositories.data.0.full_name', 'octocat/repo-1')
                ->where('repositories.data.0.github_updated_at', now()->addMinute()->toISOString()));
    }

    public function test_repositories_page_is_displayed_with_only_the_authenticated_users_repositories(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);
        $otherTarget = GithubTarget::query()->create([
            'name' => 'laravel',
            'type' => GithubTargetType::Organization,
        ]);

        $target->repositories()->create([
            'external_id' => 123,
            'name' => 'hello-world',
            'full_name' => 'octocat/hello-world',
            'description' => 'Visible repository.',
            'html_url' => 'https://github.com/octocat/hello-world',
            'language' => 'PHP',
            'stargazers_count' => 10,
            'open_issues_count' => 2,
            'archived' => false,
            'external_updated_at' => now(),
        ]);
        $otherTarget->repositories()->create([
            'external_id' => 456,
            'name' => 'framework',
            'full_name' => 'laravel/framework',
            'html_url' => 'https://github.com/laravel/framework',
        ]);

        $user->githubTargets()->attach($target);
        $otherUser->githubTargets()->attach($otherTarget);
        $otherUser->githubTargets()->attach($target);

        $this
            ->actingAs($user)
            ->get(route('repositories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('repositories/Index')
                ->has('repositories.data', 1)
                ->where('repositories.data.0.full_name', 'octocat/hello-world')
                ->where('repositories.data.0.description', 'Visible repository.')
                ->where('repositories.data.0.url', 'https://github.com/octocat/hello-world')
                ->where('repositories.data.0.language', 'PHP')
                ->where('repositories.data.0.stars', 10)
                ->where('repositories.data.0.open_issues', 2)
                ->where('repositories.data.0.archived', false)
                ->where('repositories.data.0.target.name', 'octocat'));
    }
    #[DataProvider('invalidFilterProvider')]
    public function test_invalid_filters_are_rejected(array $query, string $field): void
    {
        $this->actingAs(User::factory()->create())->getJson(route('repositories.index', $query))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public static function invalidFilterProvider(): array
    {
        return [
            'invalid search field' => [['search_in' => 'invalid'], 'search_in'],
            'invalid sort' => [['sort' => 'invalid'], 'sort'],
            'invalid status' => [['status' => 'invalid'], 'status'],
            'languages must be array' => [['languages' => 'PHP'], 'languages'],
            'languages must be strings' => [['languages' => [['PHP']]], 'languages.0'],
            'duplicate languages' => [['languages' => ['PHP', 'PHP']], 'languages.0'],
            'search too long' => [['search' => str_repeat('a', 256)], 'search'],
        ];
    }
}
