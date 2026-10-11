<?php

namespace Tests\Unit\Github;

use App\Src\Github\GithubClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GithubClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.github.base_url' => 'https://api.github.test',
            'services.github.per_page' => 100,
            'services.github.timeout' => 10,
        ]);

        Http::preventStrayRequests();
    }

    #[DataProvider('tokenProvider')]
    public function test_authorization_header(mixed $token, bool $expectsHeader): void
    {
        Http::fake(['https://api.github.test/*' => Http::response([], 200)]);

        $client = (new GithubClient())->setToken($token);
        $client->getRepositories('testuser', 'user');

        Http::assertSent(function (Request $request) use ($expectsHeader) {
            return $expectsHeader
                ? $request->hasHeader('Authorization', 'Bearer test-token')
                : !$request->hasHeader('Authorization');
        });
    }

    public static function tokenProvider(): array
    {
        return [
            'with token'    => ['test-token', true],
            'without token' => [null, false],
        ];
    }

    #[DataProvider('tokenStateProvider')]
    public function test_get_token_returns_the_last_set_token(?string $firstToken, ?string $secondToken): void
    {
        $client = new GithubClient();

        $this->assertNull($client->getToken());
        $this->assertSame($client, $client->setToken($firstToken));
        $this->assertSame($firstToken, $client->getToken());

        $client->setToken($secondToken);

        $this->assertSame($secondToken, $client->getToken());
    }

    public static function tokenStateProvider(): array
    {
        return [
            'token can be set' => ['test-token', 'next-token'],
            'token can be cleared' => ['test-token', null],
            'token can stay empty' => [null, null],
        ];
    }

    #[DataProvider('targetTypeProvider')]
    public function test_calls_correct_endpoint_for_target_type(string $type, string $expectedUrlPart): void
    {
        Http::fake(['https://api.github.test/*' => Http::response([], 200)]);

        $client = new GithubClient();
        $client->getRepositories('testuser', $type);

        Http::assertSent(fn (Request $request) => $request->url() === "https://api.github.test{$expectedUrlPart}?per_page=100&page=1");
    }

    public static function targetTypeProvider(): array
    {
        return [
            'user target' => ['user', '/users/testuser/repos'],
            'org target'  => ['org', '/orgs/testuser/repos'],
        ];
    }

    #[DataProvider('paginationProvider')]
    public function test_fetches_all_pages(array $pages, int $expectedCount): void
    {
        config(['services.github.per_page' => 2]);

        $sequence = Http::sequence();
        foreach ($pages as $page) {
            $sequence->push($page, 200);
        }
        Http::fake(['https://api.github.test/*' => $sequence]);

        $client = new GithubClient();
        $result = $client->getRepositories('testuser', 'user');

        $this->assertCount($expectedCount, $result);
    }

    public static function paginationProvider(): array
    {
        return [
            'single partial page' => [
                [[['id' => 1]]],
                1,
            ],
            'full page then partial' => [
                [[['id' => 1], ['id' => 2]], [['id' => 3]]],
                3,
            ],
            'empty response' => [
                [[]],
                0,
            ],
        ];
    }

    #[DataProvider('errorStatusProvider')]
    public function test_throws_request_exception_on_error_response(int $status): void
    {
        Http::fake(['https://api.github.test/*' => Http::response([], $status)]);

        $client = new GithubClient();

        $this->expectException(RequestException::class);
        $client->getRepositories('testuser', 'user');
    }

    public static function errorStatusProvider(): array
    {
        return [
            '404 not found'    => [404],
            '403 forbidden'    => [403],
            '429 rate limited' => [429],
            '500 server error' => [500],
        ];
    }
}
