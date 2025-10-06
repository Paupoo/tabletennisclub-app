<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ToggleHasPaidInterface
{
    public function toggleHasPaid(Model $model): bool;
}
