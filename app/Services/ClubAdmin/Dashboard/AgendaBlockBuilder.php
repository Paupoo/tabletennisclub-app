<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Dashboard;

use App\Data\Dashboard\AgendaBlock;
use App\Data\Dashboard\AgendaRow;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Trainings\Models\Training;

/**
 * Builds the dashboard's agenda column, one block per kind of object.
 *
 * Every visibility decision is taken here rather than in the view, so the whole
 * "who sees what" rule is provable without a request: a block the reader may not
 * see is never built, and a block whose management screen is out of reach comes
 * back with no {@see AgendaBlock::$seeAllRoute}.
 */
class AgendaBlockBuilder
{
    /**
     * The blocks this reader may see, in a stable order — public matters first,
     * management after, so a member and an administrator read the same page.
     *
     * @return list<AgendaBlock>
     */
    public function for(User $user): array
    {
        return array_values(array_filter([
            $this->trainings($user),
            $this->interclubs($user),
            $this->tournaments($user),
            $this->meetings($user),
            $this->messages($user),
            $this->newMembers($user),
        ]));
    }

    /**
     * Assembles a block, or nothing when it has no line to show.
     *
     * The exit is kept only when its target route would let this reader through:
     * the permission asked for here is the one that route already declares, so a
     * "voir tout" can never lead to a 403.
     *
     * @param  list<AgendaRow>  $rows
     */
    private function block(string $key, string $label, array $rows, string $seeAllRoute, Permission $seeAllPermission, User $user): ?AgendaBlock
    {
        if ($rows === []) {
            return null;
        }

        return new AgendaBlock(
            key: $key,
            label: $label,
            rows: $rows,
            seeAllRoute: $user->can($seeAllPermission->value) ? $seeAllRoute : null,
        );
    }

    /**
     * "C — C.T.T. Rebecq B": our team, then the opponent named as the results
     * screens name them, so the two readings agree.
     */
    private function fixtureLabel(Interclub $match): string
    {
        $opponent = $match->opponentTeam();

        return trim(($match->ourTeam()?->name ?? '?')
            . ' — '
            . trim(($opponent?->club?->name ?? '') . ' ' . ($opponent?->name ?? '')));
    }

    /**
     * When, and — for a home fixture only — in which hall. The opposing club's
     * hall is of no use to anyone reading a dashboard.
     */
    private function fixtureSub(Interclub $match): string
    {
        $when = $match->start_date_time->translatedFormat('D j M · H:i');

        return $match->isHome() && $match->room?->name
            ? $when . ' · ' . $match->room->name
            : $when;
    }

    /**
     * The club's next fixtures, written from the club's side.
     *
     * Public on purpose: the block exists as much to bring supporters to a home
     * match as to tell a competitor when they play, so it names our team first,
     * the opposing club in full, and says plainly where it is played.
     */
    private function interclubs(User $user): ?AgendaBlock
    {
        if (! Feature::Interclubs->enabled()) {
            return null;
        }

        $rows = Interclub::query()
            ->where('start_date_time', '>', now())
            ->withoutByes()
            ->orderBy('start_date_time')
            // isHome() reads visitedTeam.club, and ourTeam()/opponentTeam() reach
            // for both sides: without these four relations a three-line block
            // raises a LazyLoadingViolation on the second row.
            ->with(['visitedTeam.club', 'visitingTeam.club', 'room'])
            ->take(3)
            ->get()
            ->map(fn (Interclub $match): AgendaRow => new AgendaRow(
                label: $this->fixtureLabel($match),
                sub: $this->fixtureSub($match),
                badge: $match->isHome() ? __('At home') : __('Away game'),
            ))
            ->all();

        $lead = $this->lastResult();

        if ($rows === [] && $lead === null) {
            return null;
        }

        return new AgendaBlock(
            key: 'interclubs',
            label: __('Interclubs'),
            rows: $rows,
            seeAllRoute: $user->can(Permission::InterclubsManage->value) ? route('admin.interclubs.interclubs') : null,
            lead: $lead,
        );
    }

    /**
     * The last fixture the club has a score for.
     *
     * Ordered by when it was played rather than by when it was encoded: a result
     * entered late for an old match is not news.
     */
    private function lastResult(): ?AgendaRow
    {
        $match = Interclub::query()
            ->whereNotNull('result')
            ->whereNotNull('score')
            ->withoutByes()
            ->orderByDesc('start_date_time')
            ->with(['visitedTeam.club', 'visitingTeam.club'])
            ->first();

        return $match === null ? null : new AgendaRow(
            label: $this->fixtureLabel($match),
            sub: $match->start_date_time->translatedFormat('D j M'),
            badge: $match->score,
        );
    }

    /**
     * The committee's next meetings.
     *
     * Guarded in content, not merely in its link: this used to share the
     * "Événements" card with the tournaments above, which put the committee's
     * agenda in front of every member of the club.
     */
    private function meetings(User $user): ?AgendaBlock
    {
        if (! Feature::Meetings->enabled() || ! $user->can(Permission::MeetingsView->value)) {
            return null;
        }

        $rows = Meeting::query()
            ->where('scheduled_at', '>', now())
            ->whereNotIn('status', [MeetingStatusEnum::CANCELLED])
            ->orderBy('scheduled_at')
            ->take(3)
            ->get()
            ->map(fn (Meeting $meeting): AgendaRow => new AgendaRow(
                label: $meeting->title,
                sub: $meeting->scheduled_at?->translatedFormat('D j M · H:i') ?? '',
            ))
            ->all();

        return $this->block(
            key: 'meetings',
            label: __('Meetings'),
            rows: $rows,
            seeAllRoute: route('admin.meetings.index'),
            seeAllPermission: Permission::MeetingsView,
            user: $user,
        );
    }

    /**
     * The last messages the public form brought in.
     *
     * The alert pills above already say how many are unread; this block says who
     * wrote, which no count can carry.
     */
    private function messages(User $user): ?AgendaBlock
    {
        if (! Feature::Contacts->enabled() || ! $user->can(Permission::ContactsView->value)) {
            return null;
        }

        $rows = Contact::query()
            ->latest()
            ->take(3)
            ->get()
            ->map(fn (Contact $contact): AgendaRow => new AgendaRow(
                label: trim($contact->first_name . ' ' . $contact->last_name),
                sub: $contact->created_at?->diffForHumans() ?? '',
            ))
            ->all();

        return $this->block(
            key: 'messages',
            label: __('Messages'),
            rows: $rows,
            seeAllRoute: route('admin.website.contacts.index'),
            seeAllPermission: Permission::ContactsView,
            user: $user,
        );
    }

    /**
     * Who joined the club lately — the one nominative line of the page.
     */
    private function newMembers(User $user): ?AgendaBlock
    {
        if (! $user->can(Permission::UsersView->value)) {
            return null;
        }

        $rows = User::query()
            ->latest()
            ->take(3)
            ->get()
            ->map(fn (User $member): AgendaRow => new AgendaRow(
                label: trim($member->first_name . ' ' . $member->last_name),
                sub: $member->created_at?->diffForHumans() ?? '',
            ))
            ->all();

        return $this->block(
            key: 'new_members',
            label: __('New members'),
            rows: $rows,
            seeAllRoute: route('admin.users.index'),
            seeAllPermission: Permission::UsersView,
            user: $user,
        );
    }

    /**
     * The club's next internal tournaments. Public — they are open to members,
     * and used to share a card with committee meetings, which are not.
     */
    private function tournaments(User $user): ?AgendaBlock
    {
        if (! Feature::Tournaments->enabled()) {
            return null;
        }

        $rows = Tournament::query()
            ->where('start_date', '>', now())
            ->where('status', '!=', TournamentStatusEnum::CANCELLED)
            ->orderBy('start_date')
            ->take(3)
            ->get()
            ->map(fn (Tournament $tournament): AgendaRow => new AgendaRow(
                label: $tournament->name,
                sub: $tournament->start_date?->translatedFormat('D j M') ?? '',
            ))
            ->all();

        return $this->block(
            key: 'tournaments',
            label: __('Tournaments'),
            rows: $rows,
            seeAllRoute: route('admin.tournaments.index'),
            seeAllPermission: Permission::TournamentsManage,
            user: $user,
        );
    }

    /**
     * The club's next sessions. Public: the schedule is not a secret, and it is
     * the one block every member came for.
     */
    private function trainings(User $user): ?AgendaBlock
    {
        if (! Feature::Trainings->enabled()) {
            return null;
        }

        $rows = Training::query()
            ->where('start', '>', now())
            ->whereNull('cancelled_at')
            ->orderBy('start')
            ->with('room')
            ->take(3)
            ->get()
            ->map(fn (Training $training): AgendaRow => new AgendaRow(
                label: $training->start->translatedFormat('D j M') . ' · ' . $training->start->format('H:i') . '–' . $training->end->format('H:i'),
                sub: $training->room->name,
            ))
            ->all();

        return $this->block(
            key: 'trainings',
            label: __('Trainings'),
            rows: $rows,
            seeAllRoute: route('admin.trainings.index'),
            seeAllPermission: Permission::TrainingsManage,
            user: $user,
        );
    }
}
