<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Actions;

use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Feedback\Models\HelpTask;
use App\Domains\ClubAdmin\Feedback\Notifications\HelpOfferedNotification;
use App\Domains\ClubAdmin\Feedback\Services\FeedbackReaders;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\HelpRhythm;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

final class OfferHelp
{
    /**
     * Record a member offering to give the club a hand.
     *
     * Always in their own name: the club has to call them back. A managed
     * account is never asked — the guardian answering for a child offers help
     * from their own form — and a member is not asked twice while the club
     * still owes them an answer.
     *
     * @param  array<int, int>  $taskIds
     */
    public function __invoke(User $volunteer, HelpRhythm $rhythm, array $taskIds, ?string $message): HelpOffer
    {
        if ($volunteer->isManagedAccount()) {
            throw new DomainException('A managed account cannot offer help.');
        }

        if (HelpOffer::open()->whereBelongsTo($volunteer, 'volunteer')->exists()) {
            throw new DomainException('This member already has an offer of help waiting for an answer.');
        }

        $offer = DB::transaction(function () use ($volunteer, $rhythm, $taskIds, $message): HelpOffer {
            $offer = HelpOffer::create([
                'user_id' => $volunteer->id,
                'rhythm' => $rhythm,
                'message' => filled($message) ? trim($message) : null,
            ]);

            $offer->tasks()->sync(HelpTask::offered()->whereKey($taskIds)->pluck('id'));

            return $offer;
        });

        $offer->setRelation('volunteer', $volunteer);
        $offer->load('tasks');

        Notification::send((new FeedbackReaders)->toNotify(), new HelpOfferedNotification($offer));

        return $offer;
    }
}
