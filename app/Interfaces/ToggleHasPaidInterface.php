<?php
declare(strict_types=1);

namespace App\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface ToggleHasPaidInterface
{
    public function toggleHasPaid(Model $model): bool;
}
