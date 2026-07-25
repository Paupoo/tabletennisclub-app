<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Models;

use App\Contracts\DescribesPayment;
use App\Contracts\PayableInterface;
use App\Contracts\SubscriptionState;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\States\Payments\CancelledState;
use App\Domains\Shared\States\Payments\PaidState;
use App\Domains\Shared\States\Payments\PendingState;
use App\Domains\Shared\States\Payments\RefundedState;
use App\Domains\Shared\States\Payments\ValidatedState;
use App\Domains\Shared\Traits\HasAuditLog;
use App\Domains\Trainings\Models\TrainingPack;
use App\Observers\SubscriptionObserver;
use Database\Factories\Domains\ClubAdmin\Subscriptions\Models\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $season_id
 * @property int $user_id
 * @property string $status
 * @property bool $is_competitive
 * @property bool $has_other_family_members
 * @property int $trainings_count
 * @property bool $can_drive
 * @property int|null $seats_available
 * @property bool $wants_to_be_captain
 * @property bool $volunteer_help
 * @property bool $wants_directed_training
 * @property float $subscription_price
 * @property float $training_unit_price
 * @property float $amount_due
 * @property float $amount_paid
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Payment> $payments
 * @property-read int|null $payments_count
 * @property-read Season $season
 * @property-read Collection<int, TrainingPack> $trainingPacks
 * @property-read int|null $training_packs_count
 * @property-read User $user
 *
 * @method static Builder<static>|Subscription active()
 * @method static Builder<static>|Subscription captainVolunteers()
 * @method static Builder<static>|Subscription drivers()
 * @method static Builder<static>|Subscription wantsDirectedTraining()
 * @method static \Database\Factories\Domains\ClubAdmin\Subscriptions\Models\SubscriptionFactory factory($count = null, $state = [])
 * @method static Builder<static>|Subscription forSeason(\App\Domains\Competitions\Interclub\Models\Season|int $season)
 * @method static Builder<static>|Subscription newModelQuery()
 * @method static Builder<static>|Subscription newQuery()
 * @method static Builder<static>|Subscription onlyTrashed()
 * @method static Builder<static>|Subscription pendingPayment()
 * @method static Builder<static>|Subscription query()
 * @method static Builder<static>|Subscription whereAmountDue($value)
 * @method static Builder<static>|Subscription whereAmountPaid($value)
 * @method static Builder<static>|Subscription whereCanDrive($value)
 * @method static Builder<static>|Subscription whereCreatedAt($value)
 * @method static Builder<static>|Subscription whereDeletedAt($value)
 * @method static Builder<static>|Subscription whereHasOtherFamilyMembers($value)
 * @method static Builder<static>|Subscription whereId($value)
 * @method static Builder<static>|Subscription whereIsCompetitive($value)
 * @method static Builder<static>|Subscription whereSeasonId($value)
 * @method static Builder<static>|Subscription whereSeatsAvailable($value)
 * @method static Builder<static>|Subscription whereStatus($value)
 * @method static Builder<static>|Subscription whereSubscriptionPrice($value)
 * @method static Builder<static>|Subscription whereTrainingUnitPrice($value)
 * @method static Builder<static>|Subscription whereTrainingsCount($value)
 * @method static Builder<static>|Subscription whereUpdatedAt($value)
 * @method static Builder<static>|Subscription whereUserId($value)
 * @method static Builder<static>|Subscription whereVolunteerHelp($value)
 * @method static Builder<static>|Subscription whereWantsDirectedTraining($value)
 * @method static Builder<static>|Subscription whereWantsToBeCaptain($value)
 * @method static Builder<static>|Subscription withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Subscription withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy(SubscriptionObserver::class)]
class Subscription extends Model implements DescribesPayment, PayableInterface
{
    use HasAuditLog;

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, SoftDeletes;

    protected $casts = [
        'is_competitive' => 'boolean',
        'has_other_family_members' => 'boolean',
        'trainings_count' => 'integer',
        'can_drive' => 'boolean',
        'seats_available' => 'integer',
        'wants_to_be_captain' => 'boolean',
        'volunteer_help' => 'boolean',
        'wants_directed_training' => 'boolean',
    ];

    protected $fillable = [
        'user_id',
        'season_id',
        'is_competitive',
        'has_other_family_members',
        'trainings_count',
        'can_drive',
        'seats_available',
        'wants_to_be_captain',
        'volunteer_help',
        'wants_directed_training',
        'amount_due',
        'amount_paid',
        'subscription_price',
        'training_unit_price',
        'status',
    ];

    protected $table = 'subscriptions';

    private SubscriptionState $state;

    // ==================== Observers ====================
    public static function booted(): void
    {
        static::deleting(function (self $subscription): void {
            $subscription->payments()->delete();
        });
    }

    // ==================== UI helpers ====================
    public function availableTransitions(): array
    {
        return $this->getCurrentState()->availableTransitions();
    }

    /**
     * Calcule le solde restant à payer
     */
    public function balanceDue(): float
    {
        return max(0, $this->amount_due - $this->totalPaid());
    }

    public function cancel(): void
    {
        $this->getCurrentState()->cancel($this);

        // A cancelled affiliation voids its training-pack enrolments too: without
        // this they stay stuck on "pending" and the registration history keeps
        // showing a training that no longer stands.
        //
        // `left` is spared: the member did attend those months, and the line is
        // already terminal. Overwriting it would erase the very history the
        // status was introduced to keep.
        $this->trainingPacks()
            ->wherePivotNotIn('status', ['cancelled', 'left'])
            ->get()
            ->each(fn (TrainingPack $pack) => $this->trainingPacks()
                ->updateExistingPivot($pack->id, ['status' => 'cancelled']));
    }

    public function canGeneratePayment(): bool
    {
        return $this->getCurrentState()->canGeneratePayment($this);
    }

    /**
     * Check if a user can subscribe to this season (i.e. no active subscription for this season and season is active)
     */
    public function canUserSubscribe(User $user): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return ! Subscription::where('user_id', $user->id)
            ->where('season_id', $this->id)
            ->whereNotIn('status', ['cancelled'])
            ->exists();
    }

    // ==================== Status ====================
    public function confirm(): void
    {
        $this->getCurrentState()->confirm($this);
    }

    // ==================== Other ====================
    public function getAmountDue(): int|float
    {
        return $this->getAttribute('amount_due');
    }

    public function getPayerName(): string
    {
        return $this->user?->full_name ?? '—';
    }

    /**
     * @return array{type: string, name: string}
     */
    public function getPaymentLabel(): array
    {
        return [
            'type' => __('Subscription'),
            'name' => $this->season?->name ?? '—',
        ];
    }

    // Optionnel : helper pour obtenir le status actuel
    public function getStatus(): string
    {
        return $this->status;
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

    public function markAsPaid(): void
    {
        $this->getCurrentState()->markAsPaid($this);
    }

    // ==================== Others ====================

    /**
     * Ce que le membre a réellement versé, net des remboursements déjà engagés.
     *
     * {@see totalPaid()} compte les paiements de remboursement comme de
     * l'argent entrant : un `to_refund` annulé repasse en `paid`, et un
     * remboursement exécuté finit lui aussi en `paid`/`refunded`. S'en servir
     * pour décider d'un nouveau remboursement rembourserait deux fois.
     *
     * Le sens de l'argent est porté par `payment_method`, pas par le statut.
     */
    public function netAmountPaid(): float
    {
        $received = (float) $this->payments()
            ->where(fn ($q) => $q->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'))
            ->whereIn('status', ['paid', 'refunded'])
            ->sum('amount_paid');

        // Un `to_refund` compte déjà comme sorti : la demande est dans le
        // circuit trésorerie, la rejouer créerait un doublon.
        $refunded = (float) $this->payments()
            ->where('payment_method', 'refund')
            ->whereIn('status', ['to_refund', 'paid', 'refunded'])
            ->sum('amount_paid');

        return round(($received - $refunded) / 100, 2);
    }

    /**
     * Tous les paiements associés à cette subscription
     *
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function refund(): void
    {
        $this->getCurrentState()->refund($this);
    }

    /**
     * Scope pour récupérer les subscriptions des membres actifs du club.
     *
     * Un membre est actif dès que son inscription est confirmée : les statuts
     * pending, cancelled et refunded ne comptent pas dans les effectifs.
     * Aligné sur User::scopeActive().
     */
    public function scopeActive(Builder $query): Builder
    {
        // Colonne qualifiée : le pivot subscription_training_pack porte lui aussi
        // une colonne status, et un whereIn nu serait ambigu dans la jointure.
        return $query->whereIn($query->qualifyColumn('status'), ['confirmed', 'paid']);
    }

    // ==================== Scopes ====================

    /**
     * Scope pour récupérer les inscriptions encore en cours.
     *
     * Une inscription affiliée empêche d'en créer une nouvelle pour la même
     * saison. Les états terminaux (cancelled, refunded) en sont exclus : ils
     * n'engagent plus le membre et le laissent libre de se réinscrire.
     * Aligné sur User::scopeAffiliatedForCurrentSeason().
     */
    public function scopeAffiliated(Builder $query): Builder
    {
        return $query->whereIn($query->qualifyColumn('status'), ['pending', 'confirmed', 'paid']);
    }

    /**
     * Scope pour récupérer les inscriptions dont le membre se porte volontaire comme capitaine d'équipe.
     */
    public function scopeCaptainVolunteers(Builder $query): Builder
    {
        return $query->where('wants_to_be_captain', true);
    }

    /**
     * Scope pour récupérer les inscriptions dont le membre peut conduire (covoiturage).
     */
    public function scopeDrivers(Builder $query): Builder
    {
        return $query->where('can_drive', true);
    }

    /**
     * Scope pour récupérer les subscriptions d'une saison
     */
    public function scopeForSeason(Builder $query, int|Season $season): Builder
    {
        $seasonId = $season instanceof Season ? $season->id : $season;

        return $query->where('season_id', $seasonId);
    }

    /**
     * Scope pour récupérer les subscriptions en attente de paiement
     */
    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }

    /**
     * Scope pour récupérer les inscriptions dont le membre souhaite un entraînement dirigé (avec coach).
     */
    public function scopeWantsDirectedTraining(Builder $query): Builder
    {
        return $query->where('wants_directed_training', true);
    }

    // ==================== Relations ====================

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function setState(SubscriptionState $state): void
    {
        $this->status = $state->getStatus();
        $this->save();
    }

    public function subscriptionPrice(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) $value * 100,
        );
    }

    /**
     * Calcule le total payé (en euros) via tous les payments.
     * La colonne amount_paid est stockée en centimes.
     */
    public function totalPaid(): float
    {
        return round(((float) $this->payments()
            ->whereIn('status', ['paid', 'refunded'])
            ->sum('amount_paid')) / 100, 2);
    }

    /**
     * @return BelongsToMany<TrainingPack, $this>
     */
    public function trainingPacks(): BelongsToMany
    {
        return $this->belongsToMany(TrainingPack::class)
            ->withPivot([
                'status',
                'waitlist_position',
                'confirmation_deadline',
                'starts_on',
                'ends_on',
                'override_amount',
                'override_reason',
            ])
            ->withTimestamps();
    }

    public function unconfirm(): void
    {
        $this->getCurrentState()->unconfirm($this);
    }

    /**
     * Une cotisation est un historique financier : le membre doit rester
     * résolvable même après un soft delete de son compte.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    // ==================== Accessors/Mutators ====================

    /**
     * Les montants au pro rata tombent rarement sur un compte rond, et
     * `(int) ($value * 100)` tronque : 71.43 € se stocke 7142 centimes parce
     * que le flottant vaut 7142.999…. On arrondit, comme {@see Payment}.
     */
    protected function amountDue(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }

    protected function amountPaid(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }

    protected function trainingUnitPrice(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (float|int $value): int => (int) round($value * 100),
        );
    }

    private function getCurrentState(): SubscriptionState
    {
        return match ($this->status) {
            'pending' => new PendingState,
            'confirmed' => new ValidatedState,
            'paid' => new PaidState,
            'refunded' => new RefundedState,
            'cancelled' => new CancelledState,
            default => new PendingState,
        };
    }
}
