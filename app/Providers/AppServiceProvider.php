<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ForceList;
use App\Services\InterclubService;
use App\Services\TrainingBuilder;
use App\Services\TrainingDateGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton('ForceList', function ($app) {
            return new ForceList;
        });

        $this->app->singleton(TrainingDateGenerator::class, function ($app) {
            return new TrainingDateGenerator;
        });

        $this->app->singleton(TrainingBuilder::class, function ($app) {
            return new TrainingBuilder;
        });

        $this->app->singleton(InterclubService::class, function ($app) {
            return new InterclubService;
        });
    }
}
