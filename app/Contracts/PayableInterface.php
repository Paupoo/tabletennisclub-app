<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface PayableInterface
{
    public function getAmountDue(): int|float;

    public function payments(): MorphMany;
}
