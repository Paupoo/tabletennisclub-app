<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Services;

use App\Domains\ClubAdmin\Communications\Data\Audience;
use App\Domains\ClubAdmin\Communications\Data\AudienceCriteria;
use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AudienceActivityKind;
use App\Domains\Shared\Enums\AudienceActivityMode;
use App\Domains\Shared\Enums\AudienceAgeBand;
use App\Domains\Shared\Enums\AudienceBase;
use App\Domains\Shared\Enums\AudienceFunction;
use App\Domains\Shared\Enums\AudienceLicence;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes who a club-wide message reaches, from the affiliations.
 *
 * The one place that answers "who is a member right now" for a mailing, so the
 * copy-paste list and the messages sent from the application can never
 * disagree about it.
 */
class AudienceBuilder
{
    public function build(AudienceCriteria $criteria): Audience
    {
        $matching = $this->baseQuery($criteria);
        $this->whereGender($matching, $criteria->genders);
        $this->whereAgeBand($matching, $criteria->ageBands);

        $unclassified = $this->unclassifiedQuery($criteria)
            ->whereNotIn('users.id', $criteria->excludedUserIds)
            ->with('guardians')
            ->get();

        $targeted = $this->sorted($matching
            ->whereNotIn('users.id', $criteria->excludedUserIds)
            ->with('guardians')
            ->get()
            ->merge($unclassified->whereIn('id', $criteria->includedUnclassifiedIds)));

        [$reachable, $unreachable] = $targeted->partition(fn (User $member): bool => $member->contactEmails() !== []);

        return new Audience(
            members: $reachable->values(),
            unreachable: $unreachable->values(),
            unclassified: $this->sorted($unclassified->whereNotIn('id', $criteria->includedUnclassifiedIds)),
            recipients: $this->recipients($reachable),
            vacantFunctions: array_values(array_filter(
                $this->readableFunctions($criteria),
                fn (AudienceFunction $function): bool => $this->holderIds([$function]) === [],
            )),
        );
    }

    /**
     * The members of the chosen base, with the licence filter read on the very
     * affiliation that places them there: last season's for a former member.
     *
     * @return Builder<User>
     */
    private function baseQuery(AudienceCriteria $criteria): Builder
    {
        $current = Season::current();
        $placingAffiliation = $current === null ? null : $this->placingAffiliation($criteria->base, $current);

        if ($placingAffiliation === null) {
            return User::query()->whereRaw('1 = 0');
        }

        $query = User::query()->where(function (Builder $inBase) use ($placingAffiliation, $criteria): void {
            $inBase->whereHas('subscriptions', function (Builder $subscription) use ($placingAffiliation, $criteria): void {
                $placingAffiliation($subscription);
                $this->whereLicence($subscription, $criteria->licences);
            });

            // Whoever holds a function this season is part of the club, affiliated
            // or not — but holds no licence a licence filter could read.
            if ($criteria->base === AudienceBase::Active && $criteria->licences === []) {
                $inBase->orWhereIn('users.id', $this->holderIds(AudienceFunction::cases()));
            }
        });

        if ($criteria->base === AudienceBase::FormerMembers) {
            // A pending member has already come back.
            $query->whereDoesntHave('subscriptions', fn (Builder $subscription) => $subscription
                ->where('season_id', $current->id)
                ->whereIn('status', ['pending', 'confirmed', 'paid']));
        }

        $this->whereActivity($query, $criteria);

        if ($this->readableFunctions($criteria) !== []) {
            $query->whereIn('users.id', $this->holderIds($criteria->functions));
        }

        return $query;
    }

    /**
     * Who holds any of the functions this season: leads one of its packs or
     * sessions, or captains one of our own teams.
     *
     * @param  list<AudienceFunction>  $functions
     * @return list<int>
     */
    private function holderIds(array $functions): array
    {
        $seasonId = Season::current()?->id;

        $ids = collect($functions)->flatMap(fn (AudienceFunction $function) => match ($function) {
            AudienceFunction::Coaches => DB::table('training_packs')
                ->where('season_id', $seasonId)
                ->pluck('trainer_id')
                ->merge(DB::table('trainings')->where('season_id', $seasonId)->pluck('trainer_id')),
            AudienceFunction::Captains => DB::table('teams')
                ->where('season_id', $seasonId)
                ->where('club_id', Club::own()?->id)
                ->pluck('captain_id'),
        });

        return $ids->filter()->map(fn (mixed $userId): int => (int) $userId)->unique()->values()->all();
    }

    /**
     * Members a communication invited to this activity — the recipients whose
     * message carried its "for whom?" link.
     *
     * @return list<int>
     */
    private function invitedUserIds(AudienceActivityKind $kind, int $id): array
    {
        $invitations = Communication::query()
            ->whereJsonContains('invitation_targets', $kind->value . ':' . $id)
            ->pluck('id');

        return CommunicationRecipient::query()
            ->whereIn('communication_id', $invitations)
            ->pluck('user_ids')
            ->flatten()
            ->map(fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Who takes part: registered for the tournament (a cancellation is not),
     * enrolled in the pack (neither cancelled nor left), coming to the meeting,
     * or on the team sheet.
     *
     * @return list<int>
     */
    private function participantIds(AudienceActivityKind $kind, int $id): array
    {
        $ids = match ($kind) {
            AudienceActivityKind::Tournament => DB::table('tournament_user')
                ->where('tournament_id', $id)
                ->where('registration_status', '!=', 'cancelled')
                ->pluck('user_id'),
            AudienceActivityKind::TrainingPack => DB::table('subscription_training_pack')
                ->join('subscriptions', 'subscriptions.id', '=', 'subscription_training_pack.subscription_id')
                ->where('subscription_training_pack.training_pack_id', $id)
                ->whereNotIn('subscription_training_pack.status', ['cancelled', 'left'])
                ->pluck('subscriptions.user_id'),
            AudienceActivityKind::Meeting => DB::table('meeting_user')
                ->where('meeting_id', $id)
                ->whereIn('status', [MeetingUserStatusEnum::CONFIRMED->value, MeetingUserStatusEnum::ATTENDED->value])
                ->pluck('user_id'),
            AudienceActivityKind::Team => DB::table('team_user')
                ->where('team_id', $id)
                ->pluck('user_id'),
        };

        return $ids->filter()->map(fn (mixed $userId): int => (int) $userId)->unique()->values()->all();
    }

    /**
     * The affiliation that makes a member part of the base, as a constraint on
     * the subscriptions table. Null when the base cannot exist, e.g. former
     * members before any season has ended.
     *
     * @return (Closure(Builder): Builder)|null
     */
    private function placingAffiliation(AudienceBase $base, Season $current): ?Closure
    {
        if ($base === AudienceBase::FormerMembers) {
            $previous = Season::query()
                ->where('start_at', '<', $current->start_at)
                ->orderByDesc('start_at')
                ->first();

            return $previous === null ? null : fn (Builder $subscription) => $subscription
                ->where('season_id', $previous->id)
                ->whereIn('status', ['confirmed', 'paid']);
        }

        $statuses = $base === AudienceBase::Pending ? ['pending'] : ['confirmed', 'paid'];

        return fn (Builder $subscription) => $subscription
            ->where('season_id', $current->id)
            ->whereIn('status', $statuses);
    }

    /**
     * The functions asked for, when they can be read: only among the active
     * members, since a coach does not make anyone a pending or a former member.
     *
     * @return list<AudienceFunction>
     */
    private function readableFunctions(AudienceCriteria $criteria): array
    {
        return $criteria->base === AudienceBase::Active ? $criteria->functions : [];
    }

    /**
     * Each address once, with every member it speaks for — a parent of two
     * children is one address answering for both.
     *
     * @param  Collection<int, User>  $members
     * @return array<string, list<User>>
     */
    private function recipients(Collection $members): array
    {
        $recipients = [];

        foreach ($members as $member) {
            foreach ($member->contactEmails() as $address) {
                $recipients[$address][] = $member;
            }
        }

        return $recipients;
    }

    /**
     * @param  Collection<int, User>  $members
     * @return Collection<int, User>
     */
    private function sorted(Collection $members): Collection
    {
        return $members
            ->sortBy([['last_name', 'asc'], ['first_name', 'asc'], ['id', 'asc']])
            ->values();
    }

    /**
     * Members of the base an age filter cannot place, because no birthdate is
     * on file, and whom nothing else rules out. The gender is never unknown:
     * the column is required.
     *
     * @return Builder<User>
     */
    private function unclassifiedQuery(AudienceCriteria $criteria): Builder
    {
        $query = $this->baseQuery($criteria);

        if ($criteria->ageBands === []) {
            return $query->whereRaw('1 = 0');
        }

        $this->whereGender($query, $criteria->genders);

        return $query->whereNull('birthdate');
    }

    /** @param  Builder<User>  $query */
    private function whereActivity(Builder $query, AudienceCriteria $criteria): void
    {
        if (! $criteria->hasActivity()) {
            return;
        }

        $participants = $this->participantIds($criteria->activityKind, $criteria->activityId);

        if ($criteria->activityMode === AudienceActivityMode::Registered || ! $criteria->activityKind->isInvitable()) {
            $query->whereIn('users.id', $participants);

            return;
        }

        $invited = $this->invitedUserIds($criteria->activityKind, $criteria->activityId);

        match ($criteria->activityMode) {
            AudienceActivityMode::InvitedNotRegistered => $query->whereIn('users.id', $invited)->whereNotIn('users.id', $participants),
            AudienceActivityMode::NotInvited => $query->whereNotIn('users.id', [...$invited, ...$participants]),
        };
    }

    /**
     * @param  Builder<User>  $query
     * @param  list<AudienceAgeBand>  $bands
     */
    private function whereAgeBand(Builder $query, array $bands): void
    {
        if ($bands === []) {
            return;
        }

        $adulthood = now()->subYears(18)->toDateString();
        $veteranCutoff = Season::current()->end_at->copy()->subYears(User::VETERAN_AGE)->toDateString();

        $query->where(function (Builder $anyBand) use ($bands, $adulthood, $veteranCutoff): void {
            foreach ($bands as $band) {
                $anyBand->orWhere(fn (Builder $inBand) => match ($band) {
                    AudienceAgeBand::Youth => $inBand->whereDate('birthdate', '>', $adulthood),
                    AudienceAgeBand::Adult => $inBand->whereDate('birthdate', '<=', $adulthood)
                        ->whereDate('birthdate', '>', $veteranCutoff),
                    AudienceAgeBand::Veteran => $inBand->whereDate('birthdate', '<=', $veteranCutoff),
                });
            }
        });
    }

    /**
     * @param  Builder<User>  $query
     * @param  list<Gender>  $genders
     */
    private function whereGender(Builder $query, array $genders): void
    {
        if ($genders === []) {
            return;
        }

        $query->whereIn('gender', array_map(fn (Gender $gender): string => $gender->value, $genders));
    }

    /** @param  list<AudienceLicence>  $licences */
    private function whereLicence(Builder $subscription, array $licences): void
    {
        if ($licences === []) {
            return;
        }

        $subscription->whereIn('is_competitive', array_map(
            fn (AudienceLicence $licence): bool => $licence === AudienceLicence::Competitive,
            $licences,
        ));
    }
}
