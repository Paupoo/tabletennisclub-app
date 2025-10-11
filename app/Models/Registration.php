<?php

namespace App\Models;

use App\Contracts\PayableInterface;
use Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Registration extends Model implements PayableInterface
{
    /** @use HasFactory<\Database\Factories\RegistrationFactory> */
    use HasFactory;

    // ==================== Mutators ====================
    public function amountDue(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): float => round($value/100, 2),
            set: fn (string $value): int => $value * 100,
        );
    }

    // ==================== Relations ====================

    /**
     * Tous les paiements associés à cette registration
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function getAmountDue(): int|float
    {
        return $this->amountDue;
    }
}