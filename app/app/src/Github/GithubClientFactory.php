<?php

namespace App\Src\Github;

class GithubClientFactory
{
    public function make(?string $token): GithubClient
    {
        return new GithubClient($token);
    }
}
