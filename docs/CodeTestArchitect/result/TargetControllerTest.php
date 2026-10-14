<?php

namespace Tests\Unit\Http\Controllers;

use App\Enums\GithubTargetSyncStatus;
use App\Enums\GithubTargetType;
use App\Http\Controllers\TargetController;
use App\Http\Requests\StoreTargetRequest;
use App\Jobs\SyncGithubTarget;
use App\Models\GithubRepository;
use App\Models\GithubTarget;
use App\Models\User;
use App\Repositories\GithubTargetRepository;
use App\Services\AddGithubTargetForUser;
use App\Services\AddGithubTargetResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class TargetControllerTest extends TestCase
{
    private GithubTargetRepository $targets;

    private AddGithubTargetForUser $addGithubTargetForUser;

    private TargetController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->targets = Mockery::mock(GithubTargetRepository::class);
        $this->addGithubTargetForUser = Mockery::mock(AddGithubTargetForUser::class);
        $this->controller = new TargetController($this->targets, $this->addGithubTargetForUser);
    }

    public function test_index_loads_both_target_types_and_maps_only_safe_display_data(): void
    {
        $user = $this->user(7);
        $syncedAt = Carbon::parse('2026-01-02T03:04:05Z');
        $userTarget = $this->target(
            id: 10,
            name: 'octocat',
            type: GithubTargetType::User,
            status: GithubTargetSyncStatus::Failed,
            lastSyncedAt: $syncedAt,
            lastError: 'private provider response',
        );
        $orgTarget = $this->target(
            id: 20,
            name: 'laravel',
            type: GithubTargetType::Organization,
            status: GithubTargetSyncStatus::Synced,
            lastSyncedAt: $syncedAt,
        );

        $this->targets
            ->shouldReceive('paginatedForUserByType')
            ->once()
            ->with($user, GithubTargetType::User, 'users_page')
            ->andReturn($this->paginator([$userTarget]));
        $this->targets
            ->shouldReceive('paginatedForUserByType')
            ->once()
            ->with($user, GithubTargetType::Organization, 'orgs_page')
            ->andReturn($this->paginator([$orgTarget]));

        $response = $this->controller->index($this->requestFor($user));

        $this->assertSame('targets/Index', $response->getComponent());
        $props = $response->getProps();
        $this->assertSame('octocat', $props['users']->items()[0]['name']);
        $this->assertSame('user', $props['users']->items()[0]['type']);
        $this->assertSame('failed', $props['users']->items()[0]['status']);
        $this->assertSame($syncedAt->toISOString(), $props['users']->items()[0]['last_synced_at']);
        $this->assertSame(trans('targets.request_failed'), $props['users']->items()[0]['last_error']);
        $this->assertSame('laravel', $props['orgs']->items()[0]['name']);
        $this->assertSame('org', $props['orgs']->items()[0]['type']);
        $this->assertSame('PHP', $props['orgs']->items()[0]['repositories'][0]['language']);
        $this->assertSame(42, $props['orgs']->items()[0]['repositories'][0]['stars']);
    }

    #[DataProvider('storeProvider')]
    public function test_store_delegates_the_validated_target_and_flashes_the_service_result(
        GithubTargetType $type,
        AddGithubTargetResult $result,
    ): void {
        $user = $this->user(8);
        $request = Mockery::mock(StoreTargetRequest::class);
        $request->shouldReceive('validated')
            ->once()
            ->andReturn(['name' => 'octocat', 'type' => $type->value]);
        $request->shouldReceive('user')->once()->andReturn($user);

        $this->addGithubTargetForUser
            ->shouldReceive('add')
            ->once()
            ->with($user, 'octocat', $type)
            ->andReturn($result);

        $response = $this->controller->store($request);

        $this->assertSame(route('targets.index'), $response->getTargetUrl());
        $this->assertSame(
            trans($result->translationKey(), ['target' => 'octocat']),
            session('target_result'),
        );
    }

    public static function storeProvider(): array
    {
        return [
            'user target saved' => [GithubTargetType::User, AddGithubTargetResult::saved('octocat')],
            'organization target saved' => [GithubTargetType::Organization, AddGithubTargetResult::saved('octocat')],
            'user target failed' => [GithubTargetType::User, AddGithubTargetResult::failed('octocat', 'targets.request_failed')],
            'organization target failed' => [GithubTargetType::Organization, AddGithubTargetResult::failed('octocat', 'targets.target_not_found')],
        ];
    }

    #[DataProvider('subscriptionProvider')]
    public function test_sync_dispatches_after_commit_only_for_a_subscribed_user(bool $subscribed): void
    {
        Queue::fake();
        $user = $this->user(9);
        $target = $this->target(
            id: 11,
            name: 'octocat',
            type: GithubTargetType::User,
            status: GithubTargetSyncStatus::Pending,
        );

        $this->targets->shouldReceive('belongsToUser')
            ->once()
            ->with($user, $target)
            ->andReturn($subscribed);

        if (! $subscribed) {
            try {
                $this->controller->sync($target, $this->requestFor($user));
                $this->fail('An unsubscribed user must not be allowed to sync the target.');
            } catch (NotFoundHttpException) {
                Queue::assertNothingPushed();
            }

            return;
        }

        $response = $this->controller->sync($target, $this->requestFor($user));

        $this->assertSame(route('targets.index'), $response->getTargetUrl());
        Queue::assertPushed(SyncGithubTarget::class, fn (SyncGithubTarget $job): bool =>
            $job->targetId === $target->id
            && $job->userId === $user->id
            && $job->afterCommit === true);
        Queue::assertPushed(SyncGithubTarget::class, 1);
    }

    #[DataProvider('subscriptionProvider')]
    public function test_destroy_detaches_only_for_a_subscribed_user(bool $subscribed): void
    {
        $user = $this->user(10);
        $target = $this->target(
            id: 12,
            name: 'laravel',
            type: GithubTargetType::Organization,
            status: GithubTargetSyncStatus::Synced,
        );

        $this->targets->shouldReceive('belongsToUser')
            ->once()
            ->with($user, $target)
            ->andReturn($subscribed);

        if (! $subscribed) {
            $this->targets->shouldNotReceive('detachFromUser');

            try {
                $this->controller->destroy($target, $this->requestFor($user));
                $this->fail('An unsubscribed user must not be allowed to remove the target.');
            } catch (NotFoundHttpException) {
                // The 404 is the controller's authorization response.
            }

            return;
        }

        $this->targets->shouldReceive('detachFromUser')->once()->with($user, $target);

        $response = $this->controller->destroy($target, $this->requestFor($user));

        $this->assertSame(route('targets.index'), $response->getTargetUrl());
    }

    public static function subscriptionProvider(): array
    {
        return [
            'subscribed' => [true],
            'not subscribed' => [false],
        ];
    }

    private function user(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    private function requestFor(User $user): Request
    {
        $request = Request::create('/targets');
        $request->setUserResolver(fn (): User => $user);

        return $request;
    }

    private function paginator(array $items): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, count($items), 5, 1, ['path' => '/targets']);
    }

    private function target(
        int $id,
        string $name,
        GithubTargetType $type,
        GithubTargetSyncStatus $status,
        ?Carbon $lastSyncedAt = null,
        ?string $lastError = null,
    ): GithubTarget {
        $target = new GithubTarget([
            'name' => $name,
            'type' => $type,
            'sync_status' => $status,
            'last_synced_at' => $lastSyncedAt,
            'last_error' => $lastError,
        ]);
        $target->id = $id;
        $target->setAttribute('repositories_count', 1);
        $target->setRelation('repositories', new EloquentCollection([$this->repository($id)]));

        return $target;
    }

    private function repository(int $id): GithubRepository
    {
        $repository = new GithubRepository([
            'name' => 'framework',
            'full_name' => 'laravel/framework',
            'description' => 'The framework repository.',
            'html_url' => 'https://github.com/laravel/framework',
            'language' => 'PHP',
            'stargazers_count' => 42,
            'open_issues_count' => 3,
            'archived' => false,
            'external_updated_at' => Carbon::parse('2026-01-03T04:05:06Z'),
        ]);
        $repository->id = $id;

        return $repository;
    }
}
