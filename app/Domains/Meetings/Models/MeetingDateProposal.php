<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MeetingDateVoteEnum;
use Database\Factories\Domains\Meetings\Models\MeetingDateProposalFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_id
 * @property Carbon $proposed_at
 * @property bool $is_selected
 * @property-read Meeting $meeting
 * @property-read Collection<int, MeetingDateVote> $votes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $votes_count
 *
 * @method static \Database\Factories\Domains\Meetings\Models\MeetingDateProposalFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateProposal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateProposal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateProposal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateProposal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateProposal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateProposal whereIsSelected($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateProposal whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateProposal whereProposedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingDateProposal whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[UseFactory(MeetingDateProposalFactory::class)]
class MeetingDateProposal extends Model
{
    use HasFactory;

    protected $casts = [
        'proposed_at' => 'datetime',
        'is_selected' => 'boolean',
    ];

    protected $fillable = [
        'meeting_id',
        'proposed_at',
        'is_selected',
    ];

    public function availableCount(): int
    {
        return $this->votes()->where('vote', MeetingDateVoteEnum::AVAILABLE->value)->count();
    }

    public function getUserVote(User $user): ?MeetingDateVoteEnum
    {
        $vote = $this->votes()->where('user_id', $user->id)->first();

        return $vote ? MeetingDateVoteEnum::from($vote->vote) : null;
    }

    public function maybeCount(): int
    {
        return $this->votes()->where('vote', MeetingDateVoteEnum::MAYBE->value)->count();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function unavailableCount(): int
    {
        return $this->votes()->where('vote', MeetingDateVoteEnum::UNAVAILABLE->value)->count();
    }

    public function votes(): HasMany
    {
        return $this->hasMany(MeetingDateVote::class);
    }
}
