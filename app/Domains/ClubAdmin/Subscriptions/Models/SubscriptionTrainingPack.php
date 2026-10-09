<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Models;

use App\Contracts\CampEnrolment;
use App\Contracts\DescribesPayment;
use App\Contracts\PayableInterface;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Subscriptions\Notifications\SubscriptionRefundRequestedNotification;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Notifications\Notification;

/**
 * The `subscription_training_pack` row: one member's enrolment in one pack.
 *
 * It exists to give the columns a type. Everything that touches a training
 * enrolment reads `$pack->pivot->status`, `->starts_on`, `->override_amount`,
 * and static analysis had no idea what any of them were — the errors were
 * carried in the PHPStan baseline instead (issue #67).
 *
 * **No casts on purpose.** Without `using()`, these rows were hydrated by the
 * bare {@see Pivot}, which casts nothing: `starts_on` and `ends_on` come back
 * as strings, `confirmation_deadline` as a string, `override_amount` as
 * whatever PDO returns. Declaring casts here would change what every existing
 * call site receives, in the same commit as a typing change — so the types
 * below document what is actually returned rather than what would be tidier.
 * Adding casts is a separate, testable change.
 *
 * The many raw `DB::table('subscription_training_pack')` queries bypass Eloquent
 * altogether and are unaffected by any of this.
 *
 * **A stage line is a payable of its own.** When `invoiced_separately` is set,
 * the money for the line never touches the affiliation: its payments hang off
 * this row, like a tournament entry or a meeting meal. The affiliation's
 * balance, the amount an attestation certifies and the overpayment rule all
 * ignore it by construction rather than by a filter someone could forget.
 *
 * @property int $id
 * @property int $subscription_id
 * @property int $training_pack_id
 * @property string $status enrolled | waiting | offered | left | cancelled
 * @property bool|int $invoiced_separately
 * @property int|null $waitlist_position
 * @property string|null $confirmation_deadline
 * @property string|null $starts_on
 * @property string|null $ends_on
 * @property int|string|null $override_amount centimes, as every amount in the app
 * @property string|null $override_reason
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property-read Subscription $subscription
 * @property-read TrainingPack $trainingPack
 * @property-read User|null $user
 */
class SubscriptionTrainingPack extends Pivot implements CampEnrolment, DescribesPayment, PayableInterface
{
    /** Payments point at this row by its id: it must be read back after an insert. */
    public $incrementing = true;

    protected $table = 'subscription_training_pack';

    /**
     * What the line costs the member: the forced amount when the treasurer set
     * one, the pack's price otherwise. A stage has no prorata and no automatic
     * discount — a partial attendance is a forced amount with its reason.
     */
    public function getAmountDue(): float
    {
        if ($this->override_amount !== null) {
            return round(((int) $this->override_amount) / 100, 2);
        }

        return (float) $this->loadMissing('trainingPack')->trainingPack->price;
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
            'type' => __('Training camp'),
            'name' => $this->trainingPack->name,
        ];
    }

    /** A stage line is owed in full once enrolled, or left after it started: no prorata. */
    public function isOwed(): bool
    {
        return in_array($this->status, ['enrolled', 'left'], true);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function refundIban(): ?string
    {
        return $this->loadMissing('subscription.user')->subscription->user?->iban;
    }

    public function refundRequestedNotification(Payment $refund, string $reason): Notification
    {
        return new SubscriptionRefundRequestedNotification($refund, $this->loadMissing('subscription.user', 'subscription.season')->subscription, $reason);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<TrainingPack, $this>
     */
    public function trainingPack(): BelongsTo
    {
        return $this->belongsTo(TrainingPack::class);
    }

    /**
     * The member, through the affiliation.
     *
     * Every other member payable carries a `user`: the payment mail greets
     * them, the treasury searches and preloads them, the bank matcher reads
     * their IBAN. A relation rather than an accessor, so all of that works the
     * same here — the row itself holds no `user_id`.
     *
     * @return HasOneThrough<User, Subscription, $this>
     */
    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, Subscription::class, 'id', 'id', 'subscription_id', 'user_id')->withTrashed();
    }
}
