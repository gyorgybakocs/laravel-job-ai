<?php

namespace App\Repositories;

use App\Models\GithubRepository;
use App\Models\GithubTarget;
use Illuminate\Support\Carbon;

class GithubRepositoryRepository
{
    /**
     * @param array<int, array<string, mixed>> $repositories
     */
    public function upsertManyForTarget(GithubTarget $target, array $repositories): void
    {
        foreach ($repositories as $repository) {
            $this->upsertForTarget($target, $repository);
        }
    }

    /**
     * @param array<string, mixed> $repository
     */
    public function upsertForTarget(GithubTarget $target, array $repository): GithubRepository
    {
        return GithubRepository::query()->updateOrCreate(
            [
                'external_id' => $repository['id'],
            ],
            [
                'github_target_id' => $target->id,
                'name' => $repository['name'],
                'full_name' => $repository['full_name'],
                'description' => $repository['description'] ?? null,
                'html_url' => $repository['html_url'],
                'language' => $repository['language'] ?? null,
                'stargazers_count' => $repository['stargazers_count'] ?? 0,
                'open_issues_count' => $repository['open_issues_count'] ?? 0,
                'archived' => $repository['archived'] ?? false,
                'external_updated_at' => isset($repository['updated_at'])
                    ? Carbon::parse($repository['updated_at'])
                    : null,
            ],
        );
    }
}
