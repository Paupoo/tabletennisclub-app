<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Competitions\Interclub\Services\InterclubService;
use App\Domains\Trainings\Services\TrainingBuilder;
use App\Domains\Trainings\Services\TrainingDateGenerator;
use App\Services\ForceList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent lazy loading in non-production environments to catch N+1 queries early
        if ($this->app->environment() !== 'production') {
            Model::preventLazyLoading();
        }

        Paginator::defaultView('custom-paginate');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton('ForceList', function (Application $app): ForceList {
            return new ForceList;
        });

        $this->app->singleton(TrainingDateGenerator::class, function (Application $app): TrainingDateGenerator {
            return new TrainingDateGenerator;
        });

        $this->app->singleton(TrainingBuilder::class, function (Application $app): TrainingBuilder {
            return new TrainingBuilder;
        });

        $this->app->singleton(InterclubService::class, function (Application $app): InterclubService {
            return new InterclubService;
        });
    }
}
