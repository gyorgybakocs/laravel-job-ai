<?php

namespace App\Repositories;

use App\Models\GithubRepository;
use App\Models\GithubTarget;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function paginatedForUser(User $user, ?int $perPage = null, ?bool $archived = null, array $languages = [], string $sort = 'updated_desc', string $search = '', string $searchIn = 'target'): LengthAwarePaginator
    {
        $query = GithubRepository::query()
            ->with('githubTarget')
            ->whereHas('githubTarget.users', fn ($query) => $query->whereKey($user->id))
            ->when($archived !== null, fn ($query) => $query->where('archived', $archived))
            ->when($languages !== [], fn ($query) => $query->whereIn('language', $languages));

        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            if ($searchIn === 'description') {
                $query->whereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$pattern]);
            } else {
                $query->whereHas('githubTarget', fn ($targetQuery) => $targetQuery
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern]));
            }
        }

        switch ($sort) {
            case 'target_asc':
            case 'target_desc':
                $query->orderBy(
                    GithubTarget::query()->select('name')->whereColumn('github_targets.id', 'github_repositories.github_target_id'),
                    $sort === 'target_asc' ? 'asc' : 'desc',
                );
                break;
            case 'name_asc':
            case 'name_desc':
                $query->orderBy('name', $sort === 'name_asc' ? 'asc' : 'desc');
                break;
            case 'stars_desc':
                $query->orderByDesc('stargazers_count');
                break;
            default:
                $query->orderByRaw('CASE WHEN external_updated_at IS NULL THEN 1 ELSE 0 END')
                    ->orderByDesc('external_updated_at')
                    ->orderByDesc('stargazers_count');
        }

        return $query
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate($perPage ?? config('repositories.per_page'))
            ->withQueryString();
    }

    public function languageCountsForUser(User $user, ?bool $archived = null): array
    {
        return GithubRepository::query()
            ->whereHas('githubTarget.users', fn ($query) => $query->whereKey($user->id))
            ->when($archived !== null, fn ($query) => $query->where('archived', $archived))
            ->whereNotNull('language')
            ->where('language', '!=', '')
            ->select('language')
            ->selectRaw('COUNT(*) AS repository_count')
            ->groupBy('language')
            ->orderByRaw('LOWER(language) ASC')
            ->orderBy('language')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->language,
                'count' => (int) $row->repository_count,
            ])
            ->all();
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
