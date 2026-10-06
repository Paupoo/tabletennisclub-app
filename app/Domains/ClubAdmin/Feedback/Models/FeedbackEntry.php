<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FeedbackStatus;
use Database\Factories\Domains\ClubAdmin\Feedback\Models\FeedbackEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something a member wrote to the committee.
 *
 * Deliberately left out of the audit log: an anonymous entry must not leave,
 * in the activity table, the causer its own row refuses to keep.
 *
 * @property int $id
 * @property int|null $user_id null when the member stayed anonymous
 * @property int $feedback_theme_id
 * @property int|null $feedback_campaign_response_id a comment left in a survey answer
 * @property string $body
 * @property FeedbackStatus $status
 * @property Carbon|null $read_at
 * @property string|null $internal_note
 * @property Carbon|null $hidden_at
 * @property int|null $hidden_by_id
 * @property string|null $hidden_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 * @property-read FeedbackTheme $theme
 * @property-read User|null $hiddenBy
 * @property-read FeedbackCampaignResponse|null $response
 *
 * @method static FeedbackEntryFactory factory($count = null, $state = [])
 */
class FeedbackEntry extends Model
{
    /** @use HasFactory<FeedbackEntryFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'new',
    ];

    protected $casts = [
        'status' => FeedbackStatus::class,
        'read_at' => 'datetime',
        'hidden_at' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'feedback_theme_id',
        'feedback_campaign_response_id',
        'body',
        'status',
        'read_at',
        'internal_note',
        'hidden_at',
        'hidden_by_id',
        'hidden_reason',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by_id');
    }

    public function isAnonymous(): bool
    {
        return $this->user_id === null;
    }

    /**
     * @return BelongsTo<FeedbackCampaignResponse, $this>
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(FeedbackCampaignResponse::class, 'feedback_campaign_response_id');
    }

    /**
     * @return BelongsTo<FeedbackTheme, $this>
     */
    public function theme(): BelongsTo
    {
        return $this->belongsTo(FeedbackTheme::class, 'feedback_theme_id');
    }
}
