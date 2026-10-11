<?php

declare(strict_types=1);

namespace App\Console\Commands\Interclub;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\WeeklySelections;
use App\Domains\Shared\Enums\Role;
use App\Jobs\SendWeeklySelectionsJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * « Sélections de la semaine » (grilling du 2026-10-11) : le jeudi à midi, les
 * rencontres des sept jours qui suivent, aux joueurs des équipes du club de la
 * saison, à leurs capitaines et au comité.
 *
 * Une seule fois par adresse : un capitaine qui joue et siège au comité reçoit
 * un mail, un parent de deux joueurs aussi. Rien une semaine sans rencontre, et
 * jamais de second envoi : la page est à jour en continu.
 */
#[Signature('interclubs:send-weekly-selections')]
#[Description('Mail the fixtures of the next seven days, and who plays them, to the players, captains and committee.')]
class SendWeeklySelectionsCommand extends Command
{
    public const int WINDOW_DAYS = 7;

    public function handle(WeeklySelections $selections): int
    {
        $from = now();
        $to = now()->addDays(self::WINDOW_DAYS);

        if ($selections->between($from, $to)->isEmpty()) {
            $this->info('No fixture in the next seven days: nothing sent.');

            return self::SUCCESS;
        }

        $recipients = $this->recipients();

        foreach ($recipients as $address => $recipient) {
            SendWeeklySelectionsJob::dispatch($address, $recipient['members'], $from->toIso8601String(), $to->toIso8601String(), $recipient['names']);
        }

        $this->info('Weekly selections queued for ' . count($recipients) . ' address(es).');

        return self::SUCCESS;
    }

    private function readerName(User $member, string $address): ?string
    {
        if (mb_strtolower(trim((string) $member->email)) === $address) {
            return $member->first_name;
        }

        return $member->guardians
            ->first(fn (Guardian $guardian): bool => mb_strtolower(trim((string) $guardian->email)) === $address)
            ?->first_name;
    }

    /**
     * Each address once, with the members it speaks for and the first names of
     * whoever reads it: the member's own, or the guardian's when the address is
     * a parent's.
     *
     * @return array<string, array{members: array<int, int>, names: array<int, string>}>
     */
    private function recipients(): array
    {
        $teams = Team::query()
            ->inClub()
            ->where('season_id', Season::current()?->id)
            ->with('users')
            ->get();

        $memberIds = $teams->flatMap(fn (Team $team): Collection => $team->users->pluck('id'))
            ->merge($teams->pluck('captain_id')->filter())
            ->merge(User::role(Role::COMMITTEE->value)->pluck('id'))
            ->unique();

        $members = User::query()->whereIn('id', $memberIds)->with('guardians')->orderBy('id')->get();

        $recipients = [];

        foreach ($members as $member) {
            foreach ($member->contactEmails() as $address) {
                $recipients[$address]['members'][] = $member->id;
                $recipients[$address]['names'][] = $this->readerName($member, $address);
            }
        }

        ksort($recipients);

        return array_map(fn (array $recipient): array => [
            'members' => $recipient['members'],
            'names' => array_values(array_unique(array_filter($recipient['names']))),
        ], $recipients);
    }
}
