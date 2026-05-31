<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MeetingFormatEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Database\Factories\Domains\Meetings\Models\MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property MeetingTypeEnum $type
 * @property MeetingStatusEnum $status
 * @property MeetingFormatEnum $format
 * @property bool $is_public
 * @property string|null $description
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $ends_at
 * @property string|null $location
 * @property string|null $meeting_link
 * @property Carbon|null $rsvp_deadline
 * @property bool $has_meal
 * @property string|null $meal_description
 * @property int|null $meal_price_cents
 * @property int|null $quorum
 * @property string|null $cancellation_note
 * @property string|null $postponed_note
 * @property Carbon|null $postponed_to
 * @property int $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $creator
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, MeetingDateProposal> $dateProposals
 * @property-read Collection<int, MeetingAgendaItem> $agendaItems
 * @property-read MeetingMinutes|null $minutes
 * @property-read Collection<int, MeetingActionItem> $actionItems
 */
#[UseFactory(MeetingFactory::class)]
class Meeting extends Model
{
    use HasFactory;

    protected $casts = [
        'type' => MeetingTypeEnum::class,
        'status' => MeetingStatusEnum::class,
        'format' => MeetingFormatEnum::class,
        'is_public' => 'boolean',
        'scheduled_at' => 'datetime',
        'ends_at' => 'datetime',
        'rsvp_deadline' => 'date',
        'has_meal' => 'boolean',
        'postponed_to' => 'datetime',
    ];

    protected $fillable = [
        'title',
        'type',
        'status',
        'format',
        'is_public',
        'description',
        'scheduled_at',
        'ends_at',
        'location',
        'meeting_link',
        'rsvp_deadline',
        'has_meal',
        'meal_description',
        'meal_price_cents',
        'quorum',
        'cancellation_note',
        'postponed_note',
        'postponed_to',
        'created_by',
    ];

    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class);
    }

    public function agendaItems(): HasMany
    {
        return $this->hasMany(MeetingAgendaItem::class)->orderBy('sort_order');
    }

    public function attendedCount(): int
    {
        return $this->users()
            ->wherePivot('status', MeetingUserStatusEnum::ATTENDED->value)
            ->count();
    }

    public function attendedUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('status', MeetingUserStatusEnum::ATTENDED->value);
    }

    public function confirmedCount(): int
    {
        return $this->users()
            ->wherePivotIn('status', [MeetingUserStatusEnum::CONFIRMED->value, MeetingUserStatusEnum::ATTENDED->value])
            ->count();
    }

    public function confirmedUsers(): BelongsToMany
    {
        return $this->users()->wherePivotIn('status', [
            MeetingUserStatusEnum::CONFIRMED->value,
            MeetingUserStatusEnum::ATTENDED->value,
        ]);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dateProposals(): HasMany
    {
        return $this->hasMany(MeetingDateProposal::class)->orderBy('proposed_at');
    }

    /** @return float|null Meal price in euros */
    public function getMealPriceAttribute(): ?float
    {
        return $this->meal_price_cents !== null ? $this->meal_price_cents / 100 : null;
    }

    public function isInPollPhase(): bool
    {
        return $this->status === MeetingStatusEnum::PLANNING && $this->dateProposals()->exists();
    }

    public function isQuorumReached(): bool
    {
        if ($this->quorum === null) {
            return true;
        }

        return $this->confirmedCount() >= $this->quorum;
    }

    public function minutes(): HasOne
    {
        return $this->hasOne(MeetingMinutes::class);
    }

    public function quorumPercentage(): float
    {
        if ($this->quorum === null || $this->quorum === 0) {
            return 100.0;
        }

        return min(100.0, round($this->confirmedCount() / $this->quorum * 100, 1));
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(MeetingUser::class)
            ->withPivot(['id', 'status', 'invitation_sent_at', 'response_at'])
            ->as('registration')
            ->withTimestamps();
    }
}
