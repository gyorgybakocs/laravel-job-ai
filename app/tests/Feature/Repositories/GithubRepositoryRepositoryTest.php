<?php

namespace Tests\Feature\Repositories;

use App\Enums\GithubTargetType;
use App\Models\GithubRepository;
use App\Models\GithubTarget;
use App\Models\User;
use App\Repositories\GithubRepositoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GithubRepositoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('repositoryPayloadProvider')]
    public function test_upsert_for_target_maps_github_payload_to_repository_columns(
        array $payload,
        array $expected,
    ): void {
        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);

        $repository = (new GithubRepositoryRepository())->upsertForTarget($target, $payload);
        $repository->refresh();

        $this->assertSame($target->id, $repository->github_target_id);
        $this->assertSame($payload['id'], $repository->external_id);
        $this->assertSame($expected['name'], $repository->name);
        $this->assertSame($expected['full_name'], $repository->full_name);
        $this->assertSame($expected['description'], $repository->description);
        $this->assertSame($expected['html_url'], $repository->html_url);
        $this->assertSame($expected['language'], $repository->language);
        $this->assertSame($expected['stargazers_count'], $repository->stargazers_count);
        $this->assertSame($expected['open_issues_count'], $repository->open_issues_count);
        $this->assertSame($expected['archived'], $repository->archived);

        if ($expected['external_updated_at'] === null) {
            $this->assertNull($repository->external_updated_at);
        } else {
            $this->assertTrue($repository->external_updated_at->equalTo($expected['external_updated_at']));
        }
    }

    public function test_upsert_for_target_updates_existing_repository_without_creating_duplicates(): void
    {
        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);

        $repository = new GithubRepositoryRepository();

        $repository->upsertForTarget($target, [
            'id' => 123,
            'name' => 'old-name',
            'full_name' => 'octocat/old-name',
            'html_url' => 'https://github.com/octocat/old-name',
        ]);
        $updatedRepository = $repository->upsertForTarget($target, [
            'id' => 123,
            'name' => 'new-name',
            'full_name' => 'octocat/new-name',
            'description' => 'Updated description.',
            'html_url' => 'https://github.com/octocat/new-name',
            'language' => 'PHP',
            'stargazers_count' => 42,
            'open_issues_count' => 3,
            'archived' => true,
            'updated_at' => '2026-10-10T07:00:00Z',
        ]);

        $this->assertSame(1, GithubRepository::query()->where('external_id', 123)->count());
        $this->assertSame('new-name', $updatedRepository->name);
        $this->assertSame('Updated description.', $updatedRepository->description);
        $this->assertSame(42, $updatedRepository->stargazers_count);
        $this->assertTrue($updatedRepository->archived);
    }

    #[DataProvider('targetTypeProvider')]
    public function test_paginated_for_user_returns_only_repositories_for_the_users_targets(GithubTargetType $type): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $target = GithubTarget::query()->create([
            'name' => 'visible-target',
            'type' => $type,
        ]);
        $otherTarget = GithubTarget::query()->create([
            'name' => 'hidden-target',
            'type' => $type,
        ]);

        $target->repositories()->create([
            'external_id' => 901,
            'name' => 'visible-repo',
            'full_name' => 'visible-target/visible-repo',
            'html_url' => 'https://github.com/visible-target/visible-repo',
        ]);
        $otherTarget->repositories()->create([
            'external_id' => 902,
            'name' => 'hidden-repo',
            'full_name' => 'hidden-target/hidden-repo',
            'html_url' => 'https://github.com/hidden-target/hidden-repo',
        ]);

        $user->githubTargets()->attach($target);
        $otherUser->githubTargets()->attach($otherTarget);

        $paginator = (new GithubRepositoryRepository())->paginatedForUser($user);
        $items = $paginator->items();

        $this->assertSame(1, $paginator->total());
        $this->assertSame('visible-target/visible-repo', $items[0]->full_name);
        $this->assertTrue($items[0]->relationLoaded('githubTarget'));
        $this->assertTrue($items[0]->githubTarget->is($target));
    }

    public function test_paginated_for_user_uses_fifteen_items_by_default(): void
    {
        $this->freezeTime();
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

        $paginator = (new GithubRepositoryRepository())->paginatedForUser($user);

        $this->assertSame(16, $paginator->total());
        $this->assertCount(15, $paginator->items());
        $this->assertSame('octocat/repo-16', $paginator->items()[0]->full_name);
    }

    #[DataProvider('repositoryBatchProvider')]
    public function test_upsert_many_for_target_persists_each_repository(
        array $existingPayloads,
        array $incomingPayloads,
        int $expectedCount,
        array $expectedNamesByExternalId,
    ): void {
        $target = GithubTarget::query()->create([
            'name' => 'octocat',
            'type' => GithubTargetType::User,
        ]);

        $repository = new GithubRepositoryRepository();

        foreach ($existingPayloads as $payload) {
            $repository->upsertForTarget($target, $payload);
        }

        $repository->upsertManyForTarget($target, $incomingPayloads);

        $this->assertSame($expectedCount, GithubRepository::query()->where('github_target_id', $target->id)->count());

        foreach ($expectedNamesByExternalId as $externalId => $expectedName) {
            $this->assertSame(
                $expectedName,
                GithubRepository::query()
                    ->where('external_id', $externalId)
                    ->value('name'),
            );
        }
    }

    public static function repositoryPayloadProvider(): array
    {
        return [
            'full payload' => [
                [
                    'id' => 123,
                    'name' => 'hello-world',
                    'full_name' => 'octocat/hello-world',
                    'description' => 'My first repository.',
                    'html_url' => 'https://github.com/octocat/hello-world',
                    'language' => 'PHP',
                    'stargazers_count' => 10,
                    'open_issues_count' => 2,
                    'archived' => true,
                    'updated_at' => '2026-10-10T07:00:00Z',
                ],
                [
                    'name' => 'hello-world',
                    'full_name' => 'octocat/hello-world',
                    'description' => 'My first repository.',
                    'html_url' => 'https://github.com/octocat/hello-world',
                    'language' => 'PHP',
                    'stargazers_count' => 10,
                    'open_issues_count' => 2,
                    'archived' => true,
                    'external_updated_at' => Carbon::parse('2026-10-10T07:00:00Z'),
                ],
            ],
            'optional values default cleanly' => [
                [
                    'id' => 456,
                    'name' => 'empty-repo',
                    'full_name' => 'octocat/empty-repo',
                    'html_url' => 'https://github.com/octocat/empty-repo',
                ],
                [
                    'name' => 'empty-repo',
                    'full_name' => 'octocat/empty-repo',
                    'description' => null,
                    'html_url' => 'https://github.com/octocat/empty-repo',
                    'language' => null,
                    'stargazers_count' => 0,
                    'open_issues_count' => 0,
                    'archived' => false,
                    'external_updated_at' => null,
                ],
            ],
        ];
    }

    public static function repositoryBatchProvider(): array
    {
        return [
            'multiple new repositories' => [
                [],
                [
                    [
                        'id' => 101,
                        'name' => 'alpha',
                        'full_name' => 'octocat/alpha',
                        'html_url' => 'https://github.com/octocat/alpha',
                    ],
                    [
                        'id' => 102,
                        'name' => 'beta',
                        'full_name' => 'octocat/beta',
                        'html_url' => 'https://github.com/octocat/beta',
                    ],
                ],
                2,
                [
                    101 => 'alpha',
                    102 => 'beta',
                ],
            ],
            'existing repository is updated in batch' => [
                [
                    [
                        'id' => 201,
                        'name' => 'old-name',
                        'full_name' => 'octocat/old-name',
                        'html_url' => 'https://github.com/octocat/old-name',
                    ],
                ],
                [
                    [
                        'id' => 201,
                        'name' => 'new-name',
                        'full_name' => 'octocat/new-name',
                        'html_url' => 'https://github.com/octocat/new-name',
                    ],
                    [
                        'id' => 202,
                        'name' => 'new-repo',
                        'full_name' => 'octocat/new-repo',
                        'html_url' => 'https://github.com/octocat/new-repo',
                    ],
                ],
                2,
                [
                    201 => 'new-name',
                    202 => 'new-repo',
                ],
            ],
        ];
    }

    public static function targetTypeProvider(): array
    {
        return [
            'user target' => [GithubTargetType::User],
            'organization target' => [GithubTargetType::Organization],
        ];
    }
}
