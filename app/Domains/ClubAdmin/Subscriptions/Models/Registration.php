<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Models;

use App\Contracts\PayableInterface;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use Attribute;
use Database\Factories\Domains\ClubAdmin\Subscriptions\Models\RegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Registration extends Model implements PayableInterface
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    // ==================== Mutators ====================
    public function amountDue(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): float => round($value / 100, 2),
            set: fn (string $value): int => $value * 100,
        );
    }

    public function getAmountDue(): int|float
    {
        return $this->amountDue;
    }

    // ==================== Relations ====================

    /**
     * Tous les paiements associés à cette registration
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }
}
