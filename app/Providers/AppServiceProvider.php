<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Competitions\Interclub\Services\InterclubService;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Trainings\Services\TrainingBuilder;
use App\Domains\Trainings\Services\TrainingDateGenerator;
use Database\Seeders\RoleSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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

        /*
         * Invitations leave through Gmail, which tolerates the volume and not the
         * burst: a season's worth of members invited in one click is fifty near
         * identical messages in a few seconds, which is what spam filters are
         * built to catch. Fifteen a minute turns that into three quarters of an
         * hour, which nobody is waiting on — the members were not expecting the
         * mail in the first place.
         */
        RateLimiter::for('invitations', fn (): Limit => Limit::perMinute(15));

        /*
         * Le limiteur que le groupe `api` référence par son nom (`throttle:api`).
         * Il vivait dans RouteServiceProvider, que le squelette 13 remplace par
         * withRouting() — et withRouting ne définit pas de limiteur.
         */
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        /*
         * The role → permission matrix lives in the Role enum, and the database
         * must follow it. Nothing re-applied it after the initial backfill, so
         * every later change to the matrix stayed in the code and never reached
         * a running install — the tests kept passing because RefreshDatabase
         * replays the migration each time, which hid the gap entirely.
         *
         * Re-seeding after every `migrate` closes it: the seeder is idempotent,
         * touches no role assignment, and takes a few milliseconds.
         */
        Event::listen(CommandFinished::class, static function (CommandFinished $event): void {
            // Deliberately not MigrationsEnded: that only fires when there is
            // something pending, so a deploy with no new migration would leave a
            // changed matrix unapplied — which is exactly how it drifted before.
            if (! in_array($event->command, ['migrate', 'migrate:fresh', 'migrate:refresh'], true)) {
                return;
            }

            if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
                (new RoleSeeder)->run();
            }
        });

        // @feature('bar') ... @endfeature — hides a switched-off domain from the
        // navigation and the dashboard, so a member never clicks through to a 404.
        //
        // Several domains may be passed: the block shows as soon as one of them is
        // on. That is what keeps a grouping menu — "Events", holding meetings and
        // tournaments — from rendering as an empty shell once both are off.
        Blade::if('feature', fn (string ...$features): bool => array_any($features, fn (string $feature) => Feature::from($feature)->enabled()));
    }

    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(TrainingDateGenerator::class, fn (Application $app): TrainingDateGenerator => new TrainingDateGenerator);

        $this->app->singleton(TrainingBuilder::class, fn (Application $app): TrainingBuilder => new TrainingBuilder);

        $this->app->singleton(InterclubService::class, fn (Application $app): InterclubService => new InterclubService);
    }
}
