<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Actions\ToggleHasPaidAction;
use Illuminate\Database\Eloquent\Model;

final class ToggleHasPaidMembershipAction extends ToggleHasPaidAction
{
    public function toggleHasPaid(Model $model): bool
    {
        $this->user->update([
            'has_paid' => ! $this->user->has_paid,
        ]);

        return true;
    }
}
