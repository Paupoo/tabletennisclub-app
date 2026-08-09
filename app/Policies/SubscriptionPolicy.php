<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;

class SubscriptionPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    /**
     * Destroying an affiliation outright — as opposed to cancelling it, which is
     * the normal path and belongs to the members duty — stays with administrators.
     * It takes a paid membership and its payment history out of the books.
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->hasRole(Role::ADMINISTRATOR->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Subscription $subscription): bool
    {
        return $user->hasRole(Role::ADMINISTRATOR->value);
    }

    public function generatePayment(User $user, Subscription $subscription): bool
    {
        return $user->can(Permission::SubscriptionsManage->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Subscription $subscription): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Subscription $subscription): bool
    {
        return $user->can('update', $subscription->season);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Subscription $subscription): bool
    {
        return $user->can('update', $subscription->season);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }
}
