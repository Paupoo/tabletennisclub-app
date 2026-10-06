<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\ClubAdmin\Feedback\Models\FeedbackCampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The yearly survey: a few weeks during which every active member is asked
 * for a rating of the club, a comment per theme and the question of the year.
 *
 * The form itself is the same every year, so that seasons compare. Once the
 * campaign opens, only its closing date and its introduction may change.
 *
 * @property int $id
 * @property string $title
 * @property string $intro
 * @property string|null $year_question
 * @property Carbon $opens_on
 * @property Carbon $closes_on
 * @property Carbon|null $scheduled_at null while a draft
 * @property Carbon|null $invited_at
 * @property Carbon|null $reminded_at
 * @property Carbon|null $summarised_at
 * @property int|null $created_by_id
 * @property-read Collection<int, FeedbackCampaignResponse> $responses
 * @property-read Collection<int, User> $participants
 *
 * @method static FeedbackCampaignFactory factory($count = null, $state = [])
 * @method static Builder<static>|FeedbackCampaign openOn(Carbon $day)
 * @method static Builder<static>|FeedbackCampaign scheduled()
 */
class FeedbackCampaign extends Model
{
    use HasAuditLog;

    /** @use HasFactory<FeedbackCampaignFactory> */
    use HasFactory;

    protected $casts = [
        'opens_on' => 'date',
        'closes_on' => 'date',
        'scheduled_at' => 'datetime',
        'invited_at' => 'datetime',
        'reminded_at' => 'datetime',
        'summarised_at' => 'datetime',
    ];

    protected $fillable = [
        'title',
        'intro',
        'year_question',
        'opens_on',
        'closes_on',
        'scheduled_at',
        'invited_at',
        'reminded_at',
        'summarised_at',
        'created_by_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function hasAnswered(User $member): bool
    {
        return $this->participants()->whereKey($member->id)->exists();
    }

    /**
     * Whether the campaign has opened: from then on the form is frozen, and
     * only the closing date and the introduction may still change.
     */
    public function hasStarted(): bool
    {
        return $this->scheduled_at !== null && $this->opens_on->lte(today());
    }

    public function isDraft(): bool
    {
        return $this->scheduled_at === null;
    }

    public function isOpen(): bool
    {
        return $this->scheduled_at !== null
            && today()->betweenIncluded($this->opens_on, $this->closes_on);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'feedback_campaign_participants');
    }

    /**
     * The day the one reminder goes: halfway between the opening and the close.
     */
    public function reminderDay(): Carbon
    {
        return $this->opens_on->copy()->addDays(intdiv((int) $this->opens_on->diffInDays($this->closes_on), 2));
    }

    /**
     * @return HasMany<FeedbackCampaignResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(FeedbackCampaignResponse::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpenOn(Builder $query, Carbon $day): Builder
    {
        return $query->scheduled()
            ->whereDate('opens_on', '<=', $day)
            ->whereDate('closes_on', '>=', $day);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->whereNotNull('scheduled_at');
    }
}
