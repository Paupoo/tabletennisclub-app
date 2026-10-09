<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExternalParticipants\Models;

use App\Contracts\CampEnrolment;
use App\Contracts\DescribesPayment;
use App\Contracts\PayableInterface;
use App\Domains\ClubAdmin\ExternalParticipants\Notifications\ExternalRefundRequestedNotification;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Database\Factories\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A non-member's registration to one event, their identity copied onto it.
 *
 * Not a {@see User}: a person who is not a member must not surface in any
 * member list, audience or count, and a separate table guarantees it by
 * construction rather than by a filter every screen would have to remember.
 *
 * One row per registration, never shared: nothing to deduplicate, and the
 * GDPR erasure is computed from the end of the event. Once erased the row
 * keeps the event, the price, the status, the attendance and the payments,
 * and loses everything that names someone.
 *
 * For a minor, `email` is the responsible adult's address and `guardian_phone`
 * the number a coach calls; the child has neither of their own here.
 *
 * @property int $id
 * @property string $registrable_type
 * @property int $registrable_id
 * @property string $status enrolled | left | cancelled
 * @property string|null $first_name
 * @property string|null $last_name
 * @property bool $is_minor
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $guardian_first_name
 * @property string|null $guardian_last_name
 * @property string|null $guardian_phone
 * @property int|null $override_amount centimes, as every amount in the app
 * @property string|null $override_reason
 * @property int|null $created_by
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TrainingPack $registrable
 * @property-read User|null $creator
 */
class ExternalRegistration extends Model implements CampEnrolment, DescribesPayment, PayableInterface
{
    use HasAuditLog;

    /** @use HasFactory<ExternalRegistrationFactory> */
    use HasFactory;

    /** Statuses whose registration is owed in full: a stage has no prorata. */
    public const array OWED_STATUSES = ['enrolled', 'left'];

    /** Statuses that hold a spot in the stage. */
    public const array SEATED_STATUSES = ['enrolled'];

    protected $casts = [
        'is_minor' => 'boolean',
        'override_amount' => 'integer',
        'anonymized_at' => 'datetime',
    ];

    protected $fillable = [
        'status',
        'first_name',
        'last_name',
        'is_minor',
        'email',
        'phone',
        'guardian_first_name',
        'guardian_last_name',
        'guardian_phone',
        'override_amount',
        'override_reason',
        'created_by',
        'anonymized_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The name the club reads: the participant's, or a number once erased.
     */
    public function displayName(): string
    {
        if ($this->isAnonymized()) {
            return __('External participant no. :id', ['id' => $this->id]);
        }

        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * The audit traces what happened to the registration, never whom it names:
     * a copy of the identity there would outlive the erasure.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'override_amount', 'override_reason', 'created_by', 'anonymized_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * What the registration costs: the forced amount when the club set one,
     * the stage's external price otherwise, its member price failing that.
     */
    public function getAmountDue(): float
    {
        if ($this->override_amount !== null) {
            return round($this->override_amount / 100, 2);
        }

        $stage = $this->registrable;

        return (float) ($stage->external_price ?? $stage->price);
    }

    public function getPayerName(): string
    {
        return $this->displayName();
    }

    /**
     * @return array{type: string, name: string}
     */
    public function getPaymentLabel(): array
    {
        return [
            'type' => __('Training camp'),
            'name' => $this->registrable->name,
        ];
    }

    /** Whom the club writes to: the adult for a child, the participant otherwise. */
    public function greetingName(): string
    {
        return (string) ($this->is_minor ? $this->guardian_first_name : $this->first_name);
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    public function isOwed(): bool
    {
        return in_array($this->status, self::OWED_STATUSES, true);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * The club never holds a non-member's account number: the treasurer
     * enters it on the refund itself.
     */
    public function refundIban(): ?string
    {
        return null;
    }

    public function refundRequestedNotification(Payment $refund, string $reason): Notification
    {
        return new ExternalRefundRequestedNotification($refund, $this, $reason);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function registrable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The sessions the coach marked this participant at, the status on the pivot.
     *
     * @return BelongsToMany<Training, $this>
     */
    public function trainings(): BelongsToMany
    {
        return $this->belongsToMany(Training::class)->withPivot('status')->withTimestamps();
    }
}
