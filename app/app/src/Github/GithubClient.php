<?php

namespace App\Src\Github;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GithubClient
{
    private int $perPage;
    private ?string $token = null;

    public function __construct()
    {
        $this->perPage = (int) config('services.github.per_page');
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
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
            $response = $this->http()->get($endpoint, [
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

    private function http(): PendingRequest
    {
        $request = Http::baseUrl(config('services.github.base_url'))
            ->timeout((int) config('services.github.timeout'))
            ->acceptJson();

        if ($this->token) {
            return $request->withToken($this->token);
        }

        return $request;
    }
}
