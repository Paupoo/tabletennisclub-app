<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'season_id',
        'is_competitive',
        'has_other_family_members',
        'trainings_count',
        'amount_due',
        'subscription_price',
        'training_unit_price',
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
            get: fn (string $value): float => round($value / 100, 2),
            set: fn (string $value): int => $value * 100,
        );
    }

    protected function amountPaid(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): float => round($value / 100, 2),
            set: fn (string $value): int => $value * 100,
        );

    }

    public function subscriptionPrice(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): float => round($value/100, 2),
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

    // ==================== State Pattern Integration ====================

    /**
     * Retourne l'instance d'état actuelle
     */
    public function state(): SubscriptionState
    {
        return SubscriptionStateMachine::for($this);
    }

    /**
     * Vérifie si une action est possible dans l'état actuel
     */
    public function can(string $action): bool
    {
        return SubscriptionStateMachine::can($this, $action);
    }

    /**
     * Retourne les transitions possibles
     * 
     * @return array<string>
     */
    public function availableTransitions(): array
    {
        return SubscriptionStateMachine::availableTransitions($this);
    }

    // ==================== Business Logic ====================

    /**
     * Calcule le total payé via tous les payments
     */
    public function totalPaid(): float
    {
        return (float) $this->payments()
            ->whereIn('status', ['paid', 'refunded'])
            ->sum('amount_paid') / 100;
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
}
