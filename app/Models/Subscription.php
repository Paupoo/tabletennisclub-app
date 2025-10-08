<?php

namespace App\Models;

use App\Contracts\PayableInterface;
use App\Contracts\SubscriptionState;
use App\States\Payments\CancelledState;
use App\States\Payments\ConfirmedState;
use App\States\Payments\PaidState;
use App\States\Payments\PendingState;
use App\States\Payments\RefundedState;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model implements PayableInterface
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory, SoftDeletes;

    private SubscriptionState $state;

    protected $table = 'subscriptions';

    protected $fillable = [
        'user_id',
        'season_id',
        'is_competitive',
        'has_other_family_members',
        'trainings_count',
        'amount_due',
        'amount_paid',
        'subscription_price',
        'training_unit_price',
        'status'
    ];

    protected $casts = [
        'is_competitive' => 'boolean',
        'has_other_family_members' => 'boolean',
        'trainings_count' => 'integer',
    ];


    // ==================== Accessors/Mutators ====================
    protected function amountDue(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => $value * 100,
        );
    }

    protected function amountPaid(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => $value * 100,
        );

    }

    public function subscriptionPrice(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0)/100, 2),
            set: fn (int|float $value): int => (int) $value * 100, 
        );
    }

    // ==================== Relations ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Tous les paiements associés à cette subscription
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    // ==================== Observers ====================
    public static function booted(): void
    {
        static::deleting(function (self $subscription) {
            $subscription->payments()->delete();
        });
    }

    // ==================== Status ====================
    public function confirm(): void
    {
        $this->getCurrentState()->confirm($this);
    }

    public function unconfirm(): void
    {
        $this->getCurrentState()->unconfirm($this);
    }

    public function markAsPaid(): void
    {
        $this->getCurrentState()->markAsPaid($this);
    }

    public function refund(): void
    {
        $this->getCurrentState()->refund($this);
    }

    public function cancel(): void
    {
        $this->getCurrentState()->cancel($this);
    }

    public function setState(SubscriptionState $state): void
    {
        $this->status = $state->getStatus();
        $this->save();
    }
 
    private function getCurrentState(): SubscriptionState
    {
        return match($this->status) {
            'pending' => new PendingState(),
            'confirmed' => new ConfirmedState(),
            'paid' => new PaidState(),
            'refunded' => new RefundedState(),
            'cancelled' => new CancelledState(),
            default => new PendingState(),
        };
    }

    // Optionnel : helper pour obtenir le status actuel
    public function getStatus(): string
    {
        return $this->status;
    }

    // ==================== Others ====================


    /**
     * Calcule le total payé via tous les payments
     */
    public function totalPaid(): float
    {
        return (float) $this->payments()
            ->whereIn('status', ['paid', 'refunded'])
            ->sum('amount_paid');
    }

    /**
     * Calcule le solde restant à payer
     */
    public function balanceDue(): float
    {
        return max(0, $this->amount_due - $this->totalPaid());
    }

    /**
     * Vérifie si la subscription est complètement payée
     */
    public function isFullyPaid(): bool
    {
        return $this->balanceDue() <= 0.01; // Tolérance de 1 centime
    }

    /**
     * Vérifie si la subscription est dans un état terminal
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['paid', 'canceled', 'refunded'], true);
    }

    /**
     * Vérifie si la subscription est modifiable
     */
    public function isEditable(): bool
    {
        return $this->can('update_options');
    }

    // ==================== Scopes ====================

    /**
     * Scope pour récupérer les subscriptions d'une saison
     */
    public function scopeForSeason($query, int|Season $season)
    {
        $seasonId = $season instanceof Season ? $season->id : $season;
        return $query->where('season_id', $seasonId);
    }

    /**
     * Scope pour récupérer les subscriptions actives (payées)
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope pour récupérer les subscriptions en attente de paiement
     */
    public function scopePendingPayment($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }

    // ==================== Other ====================
    public function getAmountDue(): int|float
    {
        return $this->getAttribute('amount_due');    
    }
}
