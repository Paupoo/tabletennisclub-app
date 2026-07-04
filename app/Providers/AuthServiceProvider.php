<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('manage-contacts', fn (User $user): bool => $user->canManageClubAdmin());
        Gate::define('manage-season', fn (User $user): bool => $user->canManageClubAdmin());
        Gate::define('view-audit-log', fn (User $user): bool => $user->canViewAuditLog());
        Gate::define('view-queue-monitoring', fn (User $user): bool => $user->is_admin || $user->is_committee_member);
    }
}
