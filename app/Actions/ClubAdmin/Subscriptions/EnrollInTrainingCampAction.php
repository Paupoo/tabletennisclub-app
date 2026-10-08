<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingCampEnrolledNotification;
use App\Domains\Trainings\Notifications\TrainingWaitlistJoinedNotification;
use App\Domains\Trainings\Services\TrainingCampBilling;
use Illuminate\Support\Facades\DB;

/**
 * Puts a member on a stage, by their own hand or by the club's.
 *
 * A stage is not a season pack: no request by default, no prorata, no
 * automatic discount, and an invoice of its own born the moment the line
 * becomes `enrolled` — whatever the route that got it there.
 *
 * - The member enrols directly; past the cap they join the waiting list. When
 *   the committee wants to sort the requests (children only, adults only…),
 *   `requires_approval` turns the enrolment into a request.
 * - The club enrols directly, past the cap and with enrolments closed: closing
 *   enrolments shuts the door on members, not on the club. It may set the
 *   member's price at the same time, so the invoice is born right.
 */
final readonly class EnrollInTrainingCampAction
{
    /** Affiliation statuses that can no longer carry anything. */
    private const array CLOSED_AFFILIATION_STATUSES = ['cancelled', 'refunded'];

    /**
     * Statuses that tell a finished story rather than a commitment: they do not
     * stop a member coming back.
     *
     * @var list<string>
     */
    private const array SPENT_STATUSES = ['left', 'expired', 'cancelled'];

    public function __construct(private TrainingCampBilling $billing = new TrainingCampBilling) {}

    /**
     * @param  float|null  $overrideAmount  In euros, the club only.
     * @return string the line's status: enrolled, pending or waiting
     *
     * @throws \DomainException
     */
    public function __invoke(
        Subscription $subscription,
        TrainingPack $camp,
        bool $byClub = false,
        ?float $overrideAmount = null,
        ?string $overrideReason = null,
    ): string {
        $this->assertCanEnrol($subscription, $camp, $byClub);

        $overrideReason = $overrideReason !== null && trim($overrideReason) !== '' ? trim($overrideReason) : null;

        if ($overrideAmount !== null && $overrideAmount < 0) {
            throw new \DomainException(__('A forced amount cannot be negative.'));
        }

        if ($overrideAmount !== null && $overrideReason === null) {
            throw new \DomainException(__('A reason is required to force the amount of a training pack.'));
        }

        $status = match (true) {
            $byClub => 'enrolled',
            ! $camp->hasAvailableSpot() => 'waiting',
            $camp->requires_approval => 'pending',
            default => 'enrolled',
        };

        $position = $status === 'waiting' ? $camp->waitlistCount() + 1 : null;

        $line = DB::transaction(function () use ($subscription, $camp, $status, $position, $overrideAmount, $overrideReason): SubscriptionTrainingPack {
            $this->write($subscription, $camp, [
                'status' => $status,
                'waitlist_position' => $position,
                'override_amount' => $overrideAmount !== null ? (int) round($overrideAmount * 100) : null,
                'override_reason' => $overrideAmount !== null ? $overrideReason : null,
            ]);

            $line = $this->billing->line($subscription, $camp);

            if ($status === 'enrolled') {
                $this->billing->invoice($line);
            }

            return $line;
        });

        if ($status === 'enrolled') {
            $subscription->user->notify(new TrainingCampEnrolledNotification($camp, $line->getAmountDue(), $byClub));
        }

        if ($status === 'waiting') {
            $subscription->user->notify(new TrainingWaitlistJoinedNotification($camp, (int) $position));
        }

        return $status;
    }

    /**
     * The member takes the spot the waiting list offered them.
     *
     * A stage that sorts its requests still sorts this one: the spot was held,
     * the decision was not taken.
     *
     * @throws \DomainException
     */
    public function confirmOffer(Subscription $subscription, TrainingPack $camp): string
    {
        $line = $this->billing->line($subscription, $camp);

        if ($line === null || $line->status !== 'offered') {
            throw new \DomainException(__('This spot is no longer on offer.'));
        }

        $status = $camp->requires_approval ? 'pending' : 'enrolled';

        DB::transaction(function () use ($line, $status): void {
            $line->forceFill([
                'status' => $status,
                'waitlist_position' => null,
                'confirmation_deadline' => null,
            ])->save();

            if ($status === 'enrolled') {
                $this->billing->invoice($line);
            }
        });

        if ($status === 'enrolled') {
            $subscription->user->notify(new TrainingCampEnrolledNotification($camp, $line->getAmountDue()));
        }

        return $status;
    }

    /**
     * @throws \DomainException
     */
    private function assertCanEnrol(Subscription $subscription, TrainingPack $camp, bool $byClub): void
    {
        if (! $camp->is_camp) {
            throw new \DomainException(__('This training pack is not a training camp.'));
        }

        if (in_array($subscription->status, self::CLOSED_AFFILIATION_STATUSES, true)) {
            throw new \DomainException(__('This member has no active membership for the season.'));
        }

        // The federation insurance covers the affiliated members of a season:
        // a stage held in August belongs to one season, and only its members
        // may take a table.
        if ($subscription->season_id !== $camp->season_id) {
            throw new \DomainException(__('This training camp requires an affiliation for the :season season.', [
                'season' => $camp->season?->name,
            ]));
        }

        if (! $byClub && ! $camp->enrollments_open) {
            throw new \DomainException(__('Enrolments are closed for this training pack.'));
        }

        $existing = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $camp->id)
            ->value('status');

        if ($existing !== null && ! in_array($existing, self::SPENT_STATUSES, true)) {
            throw new \DomainException(__('Already enrolled or waitlisted for this training pack.'));
        }
    }

    /**
     * One line per (affiliation, pack): coming back reuses the old one, and its
     * payments stay attached to it — the money history of the stage is read
     * on a single row.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function write(Subscription $subscription, TrainingPack $camp, array $attributes): void
    {
        $attributes += [
            'invoiced_separately' => true,
            'confirmation_deadline' => null,
            'starts_on' => null,
            'ends_on' => null,
        ];

        $exists = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $camp->id)
            ->exists();

        if ($exists) {
            $subscription->trainingPacks()->updateExistingPivot($camp->id, $attributes);

            return;
        }

        $subscription->trainingPacks()->attach($camp->id, $attributes);
    }
}
