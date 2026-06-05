<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Meeting;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingDateVote;
use App\Domains\Shared\Enums\MeetingDateVoteEnum;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingPollController extends Controller
{
    public function show(Request $request, Meeting $meeting, User $user): View|RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $meeting->loadMissing(['dateProposals.votes' => fn (Relation $q) => $q->where('user_id', $user->id)]);

        return view('meetings.poll', compact('meeting', 'user'));
    }

    public function vote(Request $request, Meeting $meeting, User $user): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $validated = $request->validate([
            'votes' => ['required', 'array'],
            'votes.*' => ['required', 'string', 'in:' . implode(',', array_column(MeetingDateVoteEnum::cases(), 'value'))],
        ]);

        foreach ($validated['votes'] as $proposalId => $vote) {
            MeetingDateVote::updateOrCreate(
                ['meeting_date_proposal_id' => (int) $proposalId, 'user_id' => $user->id],
                ['vote' => $vote]
            );
        }

        return redirect()->back()->with('status', __('Your votes have been saved. Thank you!'));
    }
}
