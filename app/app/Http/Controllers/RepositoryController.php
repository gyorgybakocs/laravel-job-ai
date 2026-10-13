<?php

namespace App\Http\Controllers;

use App\Http\Requests\RepositoryIndexRequest;
use App\Models\GithubRepository;
use App\Repositories\GithubRepositoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    public function __construct(
        private readonly GithubRepositoryRepository $repositories,
    ) {
    }

    public function index(RepositoryIndexRequest $request): Response
    {
        $validated = $request->validated();
        $status = $validated['status'] ?? null;
        $archived = $status === null ? null : $status === 'archived';
        $languages = $validated['languages'] ?? [];
        $sort = $validated['sort'] ?? config('repositories.default_sort');
        $search = trim($validated['search'] ?? '');
        $searchIn = $validated['search_in'] ?? config('repositories.default_search_field');

        return Inertia::render('repositories/Index', [
            'filterOptions' => [
                'statuses' => config('repositories.statuses'),
                'sorts' => config('repositories.sorts'),
                'searchFields' => config('repositories.search_fields'),
            ],
            'filters' => ['status' => $status, 'languages' => $languages, 'search' => $search, 'search_in' => $searchIn],
            'sort' => $sort,
            'languageOptions' => $this->repositories->languageCountsForUser($request->user(), $archived),
            'repositories' => $this->repositoryPaginator(
                $this->repositories->paginatedForUser(
                    $request->user(),
                    archived: $archived,
                    languages: $languages,
                    sort: $sort,
                    search: $search,
                    searchIn: $searchIn,
                ),
            ),
        ]);
    }

    private function repositoryPaginator(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (GithubRepository $repository): array => [
            'id' => $repository->id,
            'name' => $repository->name,
            'full_name' => $repository->full_name,
            'description' => $repository->description,
            'url' => $repository->html_url,
            'language' => $repository->language,
            'stars' => $repository->stargazers_count,
            'open_issues' => $repository->open_issues_count,
            'archived' => $repository->archived,
            'github_updated_at' => $repository->external_updated_at?->toISOString(),
            'target' => [
                'id' => $repository->githubTarget->id,
                'name' => $repository->githubTarget->name,
                'type' => $repository->githubTarget->type->value,
            ],
        ]);
    }
}
