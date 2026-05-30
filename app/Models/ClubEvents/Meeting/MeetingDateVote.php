<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Meeting;

use App\Domains\Shared\Enums\MeetingDateVoteEnum;
use App\Models\ClubAdmin\Users\User;
use Database\Factories\ClubEvents\Meeting\MeetingDateVoteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
