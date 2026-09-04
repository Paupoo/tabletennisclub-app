<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('serves a valid ICS calendar for a signed URL, without authentication', function (): void {
    $season = makeActiveSeason();
    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);
    $user = User::factory()->isCompetitor()->create();

    $club = Club::factory()->create(['is_own_club' => true]);
    $team = Team::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => $club->id,
    ]);
    $team->users()->attach($user->id);

    Interclub::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'start_date_time' => now()->addDays(7),
    ]);

    $response = $this->get(URL::signedRoute('admin.user.calendar.ics', ['user' => $user]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $body = $response->getContent();
    expect($body)->toContain('BEGIN:VCALENDAR')
        ->toContain('BEGIN:VEVENT')
        ->toContain('CATEGORIES:INTERCLUB')
        ->toContain('END:VCALENDAR');
});

it('rejects a tampered or unsigned feed URL', function (): void {
    $user = User::factory()->create();

    $this->get(route('admin.user.calendar.ics', ['user' => $user]))
        ->assertForbidden();

    $other = User::factory()->create();
    $signedForOther = URL::signedRoute('admin.user.calendar.ics', ['user' => $other]);
    $tampered = str_replace('/' . $other->id . '/', '/' . $user->id . '/', $signedForOther);

    $this->get($tampered)->assertForbidden();
});

it('shows the subscribe modal with the personal signed link on the calendar page', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.calendar', ['user' => $user])
        // Le lien signé vit dans la modale d'abonnement : depuis 87ddb05a une
        // modale fermée ne rend plus son corps, il faut l'ouvrir comme le ferait
        // le bouton « S'abonner à mon calendrier ».
        ->set('icsModal', true)
        ->assertSee(__('Subscribe (Google/Apple)'))
        ->assertSee('signature=')
        // Le délai de relecture appartient aux agendas, pas à nous : le dire
        // évite qu'un membre prenne pour une panne ce qui est leur cadence.
        ->assertSee(__('Changes can take up to 24 h to appear in your calendar.'));
});

/*
 * Les cinq cas suivants viennent d'un abonnement Google qui ne montrait pas une
 * rencontre remplacée. Le flux, lui, servait la bonne donnée : le délai venait
 * du sondage de Google, hors de notre portée. Mais l'inspection du flux a mis
 * au jour quatre défauts qui, eux, sont à nous.
 */

/**
 * @return array<int, string> les lignes du VEVENT dont le SUMMARY correspond
 */
function veventFor(string $body, string $summary): array
{
    $blocks = [];

    foreach (explode("BEGIN:VEVENT\r\n", $body) as $block) {
        $lines = explode("\r\n", explode('END:VEVENT', $block)[0]);

        if (in_array('SUMMARY:' . $summary, $lines, true)) {
            $blocks[] = $lines;
        }
    }

    expect($blocks)->toHaveCount(1, "un seul VEVENT attendu pour « {$summary} »");

    return $blocks[0];
}

function icsFeedFor(User $user): string
{
    return (string) test()->get(URL::signedRoute('admin.user.calendar.ics', ['user' => $user]))
        ->assertOk()
        ->getContent();
}

/*
 * L'UID valait sha256(type | titre | heure) : l'identité était le contenu, donc
 * déplacer une rencontre ne la mettait pas à jour, ça la détruisait et en créait
 * une autre — le membre perdait ses rappels au passage.
 */
it('keeps the same UID when an event moves or is renamed', function (): void {
    $season = makeActiveSeason();
    $user = User::factory()->create();

    $meeting = Meeting::factory()->confirmed()->create([
        'title' => 'AG de début de saison',
        'scheduled_at' => now()->addDays(7)->setTime(20, 0),
        'ends_at' => now()->addDays(7)->setTime(22, 30),
    ]);
    $meeting->users()->attach($user->id);

    $before = veventFor(icsFeedFor($user), 'AG de début de saison');

    // SEQUENCE vaut le timestamp d'`updated_at`, donc à la seconde : deux
    // écritures dans la même seconde donnent la même valeur. Sans importance en
    // vrai (un agenda sonde des heures plus tard), mais le test doit avancer.
    $this->travel(1)->minute();

    $meeting->update([
        'title' => 'AG reportée',
        'scheduled_at' => now()->addDays(9)->setTime(19, 0),
        'ends_at' => now()->addDays(9)->setTime(21, 30),
    ]);

    $after = veventFor(icsFeedFor($user), 'AG reportée');

    $uid = fn (array $lines): string => collect($lines)->first(fn (string $l): bool => str_starts_with($l, 'UID:'));
    $sequence = fn (array $lines): int => (int) substr(
        collect($lines)->first(fn (string $l): bool => str_starts_with($l, 'SEQUENCE:')), 9
    );

    expect($uid($after))->toBe($uid($before), 'un événement déplacé garde son identité')
        ->and($uid($before))->toBe('UID:meeting-' . $meeting->id . '@ctt-ottignies-blocry')
        ->and($sequence($after))->toBeGreaterThan($sequence($before), 'sans SEQUENCE qui monte, un agenda ignore la mise à jour');
});

/*
 * Le contrôleur ignorait `endDate` : tout tournoi partait en bloc de 2 h le
 * premier jour, quelle que soit sa durée réelle.
 */
it('gives a tournament its real span instead of a two-hour block', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::PUBLISHED,
        'name' => 'Tournoi de trois jours',
        'start_date' => now()->addDays(10)->setTime(9, 0),
        'start_time' => '09:00',
        'end_date' => now()->addDays(12)->setTime(18, 0),
    ]);
    $tournament->users()->attach($user->id, ['registration_status' => 'registered']);

    $lines = veventFor(icsFeedFor($user), 'Tournoi de trois jours');

    $end = collect($lines)->first(fn (string $l): bool => str_starts_with($l, 'DTEND:'));

    expect($end)->toBe('DTEND:' . now()->addDays(12)->setTime(18, 0)->utc()->format('Ymd\THis\Z'));
});

/* `meetings.ends_at` existait et la ligne du calendrier ne l'exposait pas. */
it('gives a meeting its real end time', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    $meeting = Meeting::factory()->confirmed()->create([
        'title' => 'Réunion du comité',
        'scheduled_at' => now()->addDays(5)->setTime(19, 0),
        'ends_at' => now()->addDays(5)->setTime(22, 0),
    ]);
    $meeting->users()->attach($user->id);

    $lines = veventFor(icsFeedFor($user), 'Réunion du comité');

    expect(collect($lines)->first(fn (string $l): bool => str_starts_with($l, 'DTEND:')))
        ->toBe('DTEND:' . now()->addDays(5)->setTime(22, 0)->utc()->format('Ymd\THis\Z'));
});

/*
 * La table `interclubs` n'a pas de colonne de fin : une rencontre de seize
 * matchs dure environ trois heures, et deux heures montraient le membre libre
 * alors qu'il joue encore.
 */
it('blocks three hours for an interclub match, which has no end in the database', function (): void {
    $season = makeActiveSeason();
    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);
    $user = User::factory()->isCompetitor()->create();

    $club = Club::factory()->create(['is_own_club' => true]);
    $team = Team::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => $club->id,
        'name' => 'C',
    ]);
    $team->users()->attach($user->id);

    $start = now()->addDays(7)->setTime(19, 45);

    $interclub = Interclub::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'start_date_time' => $start,
    ]);

    $body = icsFeedFor($user);
    $summary = collect(explode("\r\n", $body))
        ->first(fn (string $l): bool => str_starts_with($l, 'SUMMARY:C vs '));
    $lines = veventFor($body, substr($summary, 8));

    expect(collect($lines)->first(fn (string $l): bool => str_starts_with($l, 'DTEND:')))
        ->toBe('DTEND:' . $start->copy()->addHours(3)->utc()->format('Ymd\THis\Z'))
        ->and(collect($lines)->first(fn (string $l): bool => str_starts_with($l, 'UID:')))
        ->toBe('UID:interclub-' . $interclub->id . '@ctt-ottignies-blocry');
});

/*
 * Le flux traversait le groupe `web` : chaque sondage d'un agenda ouvrait une
 * session et repartait avec deux cookies, pour un fichier lu par une machine.
 */
it('serves the feed without opening a session', function (): void {
    makeActiveSeason();
    $user = User::factory()->create();

    $response = $this->get(URL::signedRoute('admin.user.calendar.ics', ['user' => $user]));

    $response->assertOk();

    expect($response->headers->getCookies())->toBeEmpty('un abonnement n’a pas besoin de cookie')
        ->and($response->headers->has('Content-Disposition'))->toBeFalse('un abonnement n’est pas un téléchargement');
});
