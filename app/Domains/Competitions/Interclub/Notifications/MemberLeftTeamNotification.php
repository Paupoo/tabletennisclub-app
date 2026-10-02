<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A member who played in the captain's team has left the club.
 *
 * Sent to the captain alone, once per departure whatever the number of teams
 * and matches: the place in the team is theirs to fill. It names the teams,
 * then the upcoming matches the member was lined up for — in a lineup sent or
 * still a draft — and has now been taken off. The rest of the team hears of a
 * new lineup when the captain sends it.
 *
 * Carries the member's name and the teams' names rather than the models: the
 * rows that tied them together are gone by the time the mail is written.
 *
 * A match that was declared to play with three has lost that declaration and
 * its walkover player with the departure; the mail says so for that match, so
 * the captain knows it is theirs to declare again.
 */
class MemberLeftTeamNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $seasonId  the season the departure was declared for
     * @param  list<string>  $teamNames  the captain's teams the member played in
     * @param  list<int>  $interclubIds  the upcoming matches the member was lined up for
     * @param  list<int>  $shortHandedWithdrawnIds  those of the matches no longer declared to play with three
     */
    public function __construct(
        public readonly string $memberName,
        public readonly int $seasonId,
        public readonly array $teamNames,
        public readonly array $interclubIds = [],
        public readonly array $shortHandedWithdrawnIds = [],
    ) {
        // Declared inside the departure's transaction: a departure rolled back
        // must not have told anybody.
        $this->afterCommit();
    }

    /**
     * A captain declared gone in the same gesture as one of their players is
     * not told: by the time the mail leaves, they have left too.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! $notifiable instanceof User
            || $notifiable->departures()->where('season_id', $this->seasonId)->doesntExist();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __(':name has left the club', ['name' => $this->memberName]),
            'body' => $this->teamLine(),
            'url' => route('admin.interclubs.captain-selection'),
            'category' => 'interclub',
            'icon' => 'o-user-minus',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fixtures = Interclub::query()
            ->with(['visitedTeam.club', 'visitingTeam.club'])
            ->whereKey($this->interclubIds)
            ->orderBy('start_date_time')
            ->orderBy('id')
            ->get();

        $mail = (new MailMessage)
            ->subject(__(':name has left the club', ['name' => $this->memberName]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line($this->teamLine());

        if ($fixtures->count() === 1) {
            $fixture = $fixtures->first();
            $mail->line(__('They have been taken off the lineup of team :team on :date against :opponent.', $this->describe($fixture)));

            if ($this->lostShortHandedDeclaration($fixture)) {
                $mail->line(__('The declaration to play with :n has been withdrawn, along with the walkover player: declare it again if the team still plays with :n.', ['n' => $fixture->minimumPlayers()]));
            }
        } elseif ($fixtures->isNotEmpty()) {
            $mail->line(__('They have been taken off these upcoming lineups:'));

            foreach ($fixtures as $fixture) {
                $line = '• ' . __(':date — team :team against :opponent', $this->describe($fixture));

                if ($this->lostShortHandedDeclaration($fixture)) {
                    $line .= ' ' . __('(declaration to play with :n withdrawn: declare it again if needed)', ['n' => $fixture->minimumPlayers()]);
                }

                $mail->line($line);
            }
        }

        return $mail->action(__('Open the selections'), route('admin.interclubs.captain-selection'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * @return array{team: string, date: string, opponent: string}
     */
    private function describe(Interclub $fixture): array
    {
        return [
            'team' => $fixture->ourTeam()?->name ?? '—',
            'date' => $fixture->start_date_time->format('d/m/Y'),
            'opponent' => $fixture->opponentTeam()?->fullName() ?? '—',
        ];
    }

    private function lostShortHandedDeclaration(Interclub $fixture): bool
    {
        return in_array($fixture->id, $this->shortHandedWithdrawnIds, true);
    }

    /**
     * The news itself: who left, and which teams now have a place free.
     */
    private function teamLine(): string
    {
        if ($this->teamNames === []) {
            return __(':name has left the club', ['name' => $this->memberName]) . '.';
        }

        return trans_choice(
            '{1} :name has left the club: their place in team :teams is free.|[2,*] :name has left the club: their place in teams :teams is free.',
            count($this->teamNames),
            ['name' => $this->memberName, 'teams' => implode(', ', $this->teamNames)],
        );
    }
}
