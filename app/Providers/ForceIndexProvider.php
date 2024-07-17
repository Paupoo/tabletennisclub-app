<?php

namespace App\Providers;

use App\Services\ForceIndex;
use Illuminate\Support\ServiceProvider;

class ForceIndexProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
        $this->app->singleton('ForceIndex', function ($app) {
            return new ForceIndex();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
