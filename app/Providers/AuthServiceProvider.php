<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\ClubPosts\Policies\EventPostPolicy;
use App\Domains\ClubPosts\Policies\NewsPostPolicy;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Policies\TrainingPackPolicy;
use App\Domains\Trainings\Policies\TrainingPolicy;
use App\Policies\ClubPolicy;
use App\Policies\ContactPolicy;
use App\Policies\GuardianPolicy;
use App\Policies\InterclubPolicy;
use App\Policies\RoomPolicy;
use App\Policies\SeasonPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\TablePolicy;
use App\Policies\TeamPolicy;
use App\Policies\TournamentPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * Registered explicitly on purpose. Policies live in two places — `App\Policies`
     * and `App\Domains\*\Policies` — and until now none were declared, so every
     * decision relied on Gate's namespace-walking fallback. That resolved by
     * coincidence: dropping a `Policies\` namespace anywhere between a model and
     * `App\Policies` would have silently hijacked its authorization.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Club::class => ClubPolicy::class,
        Contact::class => ContactPolicy::class,
        EventPost::class => EventPostPolicy::class,
        Guardian::class => GuardianPolicy::class,
        Interclub::class => InterclubPolicy::class,
        NewsPost::class => NewsPostPolicy::class,
        Room::class => RoomPolicy::class,
        Season::class => SeasonPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        Table::class => TablePolicy::class,
        Team::class => TeamPolicy::class,
        Tournament::class => TournamentPolicy::class,
        Training::class => TrainingPolicy::class,
        TrainingPack::class => TrainingPackPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // No Gate::before() admin bypass on purpose: some policies deliberately deny
        // everyone (GuardianPolicy::forceDelete) or guard against the actor themself
        // (UserPolicy::delete stops an admin deleting their own account), and a
        // blanket short-circuit would silently override both. Administrators instead
        // hold every permission explicitly — see Role::ADMINISTRATOR.

        Gate::define('manage-contacts', fn (User $user): bool => $user->canManageClubAdmin());
        Gate::define('manage-season', fn (User $user): bool => $user->canManageClubAdmin());
        Gate::define('view-audit-log', fn (User $user): bool => $user->canViewAuditLog());
        Gate::define('view-queue-monitoring', fn (User $user): bool => $user->is_admin || $user->is_committee_member);

        // Coach area (personal training sessions): coaches, with admin oversight.
        Gate::define('access-coach-area', fn (User $user): bool => $user->is_admin || $user->is_coach);
    }
}
