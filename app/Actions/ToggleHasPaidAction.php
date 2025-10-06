<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\ToggleHasPaidInterface;
use App\Models\User;

abstract class ToggleHasPaidAction implements ToggleHasPaidInterface
{
    /**
     * Initiative toggling payment related to a specific user
     */
    public function __construct(protected User $user) {}
}
