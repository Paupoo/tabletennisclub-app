<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\Competitions\Interclub\Models\Interclub;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A member who has left the club held a place in a lineup the team had
 * already received.
 *
 * Sent to the captain of the team and to the interclubs duty, once per
 * departure whatever the number of matches. The rest of the team hears of the
 * new lineup when the captain sends it.
 *
 * Carries the member's name rather than the member: the rows that tied them to
 * the matches are gone by the time the mail is written.
 *
 * A match that was declared to play with three has lost that declaration and
 * its walkover player with the departure; the mail says so for that match, so
 * the captain knows it is theirs to declare again.
 */
class MemberLeftLineupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $interclubIds
     * @param  list<int>  $shortHandedWithdrawnIds  those of the matches no longer declared to play with three
     */
    public function __construct(
        public readonly string $memberName,
        public readonly array $interclubIds,
        public readonly array $shortHandedWithdrawnIds = [],
    ) {
        // Declared inside the departure's transaction: a departure rolled back
        // must not have told anybody.
        $this->afterCommit();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __(':name has left the club', ['name' => $this->memberName]),
            'body' => trans_choice('{1} A place is free in :count lineup already sent.|[2,*] A place is free in :count lineups already sent.', count($this->interclubIds), ['count' => count($this->interclubIds)]),
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
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]));

        if ($fixtures->count() === 1) {
            $fixture = $fixtures->first();
            $mail->line(__(':name has left the club: their place in the lineup of team :team on :date against :opponent is free.', $this->describe($fixture)));

            if ($this->lostShortHandedDeclaration($fixture)) {
                $mail->line(__('The declaration to play with :n has been withdrawn, along with the walkover player: declare it again if the team still plays with :n.', ['n' => $fixture->minimumPlayers()]));
            }
        } else {
            $mail->line(__(':name has left the club: their place is free in these lineups:', ['name' => $this->memberName]));

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
     * @return array{name: string, team: string, date: string, opponent: string}
     */
    private function describe(Interclub $fixture): array
    {
        return [
            'name' => $this->memberName,
            'team' => $fixture->ourTeam()?->name ?? '—',
            'date' => $fixture->start_date_time->format('d/m/Y'),
            'opponent' => $fixture->opponentTeam()?->fullName() ?? '—',
        ];
    }

    private function lostShortHandedDeclaration(Interclub $fixture): bool
    {
        return in_array($fixture->id, $this->shortHandedWithdrawnIds, true);
    }
}
