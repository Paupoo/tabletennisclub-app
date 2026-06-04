<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Models;

use App\Contracts\PayableInterface;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use Database\Factories\Domains\ClubAdmin\Subscriptions\Models\RegistrationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Registration extends Model implements PayableInterface
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    public function getAmountDue(): int|float
    {
        return $this->getAttribute('amount_due');
    }

    // ==================== Relations ====================

    /**
     * Tous les paiements associés à cette registration
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    // ==================== Mutators ====================
    protected function amountDue(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) ($value * 100),
        );
    }
}
