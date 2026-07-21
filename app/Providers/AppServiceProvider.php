<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Competitions\Interclub\Services\InterclubService;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Trainings\Services\TrainingBuilder;
use App\Domains\Trainings\Services\TrainingDateGenerator;
use App\Services\ForceList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // One password policy for every form. The haveibeenpwned check needs
        // network access, so it only runs in production.
        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        Paginator::defaultView('custom-paginate');

        // @feature('bar') ... @endfeature — hides a switched-off domain from the
        // navigation and the dashboard, so a member never clicks through to a 404.
        //
        // Several domains may be passed: the block shows as soon as one of them is
        // on. That is what keeps a grouping menu — "Events", holding meetings and
        // tournaments — from rendering as an empty shell once both are off.
        Blade::if('feature', function (string ...$features): bool {
            foreach ($features as $feature) {
                if (Feature::from($feature)->enabled()) {
                    return true;
                }
            }

            return false;
        });
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
