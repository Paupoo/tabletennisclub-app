<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MeetingDateVoteEnum;
use Database\Factories\Domains\Meetings\Models\MeetingDateVoteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_date_proposal_id
 * @property int $user_id
 * @property MeetingDateVoteEnum $vote
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MeetingDateProposal $proposal
 * @property-read User $user
 *
 * @method static \Database\Factories\Domains\Meetings\Models\MeetingDateVoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateVote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateVote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateVote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateVote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateVote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateVote whereMeetingDateProposalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateVote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateVote whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateVote whereVote($value)
 *
 * @mixin \Eloquent
 */
#[UseFactory(MeetingDateVoteFactory::class)]
class MeetingDateVote extends Model
{
    use HasFactory;

    protected $casts = [
        'vote' => MeetingDateVoteEnum::class,
    ];

    protected $fillable = [
        'meeting_date_proposal_id',
        'user_id',
        'vote',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(MeetingDateProposal::class, 'meeting_date_proposal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
