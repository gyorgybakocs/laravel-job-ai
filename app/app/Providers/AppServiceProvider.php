<?php

namespace App\Providers;

use App\Src\Github\GithubClient;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GithubClient::class, fn () => new GithubClient());
        $this->app->bind(ConnectionInterface::class, fn () => DB::connection());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
