<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface PayableInterface
{
    public function payments(): MorphMany;

    public function getAmountDue(): int|float;
}
