<?php

namespace App\Src\Github;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GithubClient
{
    private PendingRequest $http;
    private int $perPage;

    public function __construct(?string $token = null)
    {
        $this->perPage = (int) config('services.github.per_page');

        $this->http = Http::baseUrl(config('services.github.base_url'))
            ->timeout((int) config('services.github.timeout'))
            ->acceptJson();

        if ($token) {
            $this->http = $this->http->withToken($token);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws RequestException
     * @throws ConnectionException
     */
    public function getRepositories(string $target, string $type): array
    {
        $endpoint = $type === 'org'
            ? "/orgs/{$target}/repos"
            : "/users/{$target}/repos";

        $repositories = [];
        $page = 1;

        do {
            $response = $this->http->get($endpoint, [
                'per_page' => $this->perPage,
                'page'     => $page,
            ]);

            $response->throw();

            $items = $response->json() ?? [];
            $repositories = array_merge($repositories, $items);
            $page++;
        } while (count($items) === $this->perPage);

        return $repositories;
    }
}
