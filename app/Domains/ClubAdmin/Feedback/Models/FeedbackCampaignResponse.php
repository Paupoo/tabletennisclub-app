<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Database\Factories\Domains\ClubAdmin\Feedback\Models\FeedbackCampaignResponseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One member's answer to a campaign: the rating, the answer to the question
 * of the year, and a comment per theme (as feedback entries).
 *
 * Kept out of the audit log, like the feedback itself: an anonymous answer
 * must not leave its causer in the activity table.
 *
 * @property int $id
 * @property int $feedback_campaign_id
 * @property int|null $user_id null when the member stayed anonymous
 * @property int $rating 1 to 5
 * @property string|null $year_answer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FeedbackCampaign $campaign
 * @property-read User|null $author
 * @property-read Collection<int, FeedbackEntry> $comments
 *
 * @method static FeedbackCampaignResponseFactory factory($count = null, $state = [])
 */
class FeedbackCampaignResponse extends Model
{
    /** @use HasFactory<FeedbackCampaignResponseFactory> */
    use HasFactory;

    protected $fillable = ['feedback_campaign_id', 'user_id', 'rating', 'year_answer'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<FeedbackCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FeedbackCampaign::class, 'feedback_campaign_id');
    }

    /**
     * @return HasMany<FeedbackEntry, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(FeedbackEntry::class);
    }
}
