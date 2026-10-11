<?php

namespace Tests\Unit\Github;

use App\Src\Github\GithubClient;
use App\Src\Github\GithubClientFactory;
use Tests\TestCase;

class GithubClientFactoryTest extends TestCase
{
    public function test_make_returns_github_client(): void
    {
        $factory = new GithubClientFactory();

        $client = $factory->make('github-token');

        $this->assertInstanceOf(GithubClient::class, $client);
    }
}
