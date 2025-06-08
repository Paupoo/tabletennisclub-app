<?php
declare(strict_types=1);

namespace App\Actions;

use App\Interfaces\ToggleHasPaidInterface;
use App\Models\User;

abstract class ToggleHasPaidAction implements ToggleHasPaidInterface
{
    /**
     * Initiative toggling payment related to a specific user
     * @param \App\Models\User $user
     */
    public function __construct(protected User $user) {}

}
