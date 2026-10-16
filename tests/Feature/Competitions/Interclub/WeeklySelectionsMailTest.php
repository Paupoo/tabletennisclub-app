<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Notifications\WeeklySelectionsNotification;
use App\Domains\Competitions\Interclub\Services\WeeklySelections;
use App\Jobs\SendWeeklySelectionsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
| « Sélections de la semaine », le mail (grilling du 2026-10-11) : le jeudi à
| midi, les rencontres des sept jours qui suivent, aux joueurs des équipes du
| club, aux capitaines et au comité — une seule fois par adresse, et rien du
| tout une semaine sans rencontre.
*/
beforeEach(function (): void {
    Queue::fake();

    $this->season = makeActiveSeason();
    $this->ownClub = Club::factory()->ownClub()->create();
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $this->ownClub->id,
        'name' => 'A',
        'captain_id' => null,
    ]);
    $this->opponent = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => Club::factory()->create(['name' => 'CTT Tourinnes'])->id,
        'name' => 'A',
        'captain_id' => null,
    ]);
});

function weeklyMailFixture(Team $home, Team $away, int $days): Interclub
{
    return Interclub::factory()->create([
        'season_id' => $home->season_id,
        'league_id' => $home->league_id,
        'visited_team_id' => $home->id,
        'visiting_team_id' => $away->id,
        'total_players' => 4,
        'is_bye' => false,
        'forfeit' => null,
        'start_date_time' => now()->addDays($days),
    ]);
}

/**
 * @return array<int, string>
 */
function weeklyMailAddresses(): array
{
    return Queue::pushed(SendWeeklySelectionsJob::class)
        ->map(fn (SendWeeklySelectionsJob $job): string => $job->address)
        ->sort()
        ->values()
        ->all();
}

it('writes to the players of our teams, their captains and the committee', function (): void {
    weeklyMailFixture($this->team, $this->opponent, 2);

    $player = User::factory()->create(['email' => 'player@example.test']);
    $this->team->users()->attach($player);
    $captain = User::factory()->create(['email' => 'captain@example.test']);
    $this->team->update(['captain_id' => $captain->id]);
    User::factory()->isCommitteeMember()->create(['email' => 'committee@example.test']);

    $opponentPlayer = User::factory()->create(['email' => 'opponent@example.test']);
    $this->opponent->users()->attach($opponentPlayer);
    $formerTeam = Team::factory()->create([
        'season_id' => Season::factory()->create([
            'is_active' => false,
            'start_at' => now()->subYears(2)->startOfYear(),
            'end_at' => now()->subYears(2)->endOfYear(),
        ])->id,
        'club_id' => $this->ownClub->id,
    ]);
    $formerTeam->users()->attach(User::factory()->create(['email' => 'former@example.test']));
    User::factory()->create(['email' => 'bystander@example.test']);

    $this->artisan('interclubs:send-weekly-selections')->assertSuccessful();

    expect(weeklyMailAddresses())->toBe(['captain@example.test', 'committee@example.test', 'player@example.test']);
});

it('writes once to an address that answers for several seats', function (): void {
    weeklyMailFixture($this->team, $this->opponent, 2);

    $both = User::factory()->isCommitteeMember()->create(['email' => 'both@example.test', 'first_name' => 'Julien']);
    $this->team->users()->attach($both);
    $this->team->update(['captain_id' => $both->id]);

    $parent = Guardian::factory()->create(['email' => 'parent@example.test', 'first_name' => 'Sophie']);
    foreach (range(1, 2) as $ignored) {
        $child = User::factory()->create(['email' => null]);
        $child->guardians()->attach($parent);
        $this->team->users()->attach($child);
    }

    $this->artisan('interclubs:send-weekly-selections')->assertSuccessful();

    expect(weeklyMailAddresses())->toBe(['both@example.test', 'parent@example.test']);
    Queue::assertPushed(SendWeeklySelectionsJob::class, fn (SendWeeklySelectionsJob $job): bool => $job->address === 'both@example.test'
        && $job->names === ['Julien']);
    Queue::assertPushed(SendWeeklySelectionsJob::class, fn (SendWeeklySelectionsJob $job): bool => $job->address === 'parent@example.test'
        && count($job->memberIds) === 2
        && $job->names === ['Sophie']);
});

it('sends nothing in a week without a fixture', function (): void {
    $this->team->users()->attach(User::factory()->create());
    weeklyMailFixture($this->team, $this->opponent, 9);

    $this->artisan('interclubs:send-weekly-selections')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('is scheduled on Thursday at noon', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'interclubs:send-weekly-selections'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 12 * * 4');
});

it('mails the tiles by day, sets apart the reader\'s own, and links the page', function (): void {
    $sent = weeklyMailFixture($this->team, $this->opponent, 1);
    $pending = weeklyMailFixture($this->opponent, $this->team, 3);
    $reader = User::factory()->create(['first_name' => 'Thomas', 'last_name' => 'Lefèvre', 'force_list' => 3, 'ranking' => 'C2']);
    $sent->users()->attach($reader->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);
    $pending->users()->attach(User::factory()->create(['first_name' => 'Loïc', 'last_name' => 'Dumont'])->id, ['is_selected' => true]);

    $from = now();
    $to = now()->addDays(7);
    $days = app(WeeklySelections::class)->between($from, $to);
    $mail = new WeeklySelectionsNotification($days, [$reader->id], $from, $to, ['Thomas'])->toMail(new User);
    $html = (string) $mail->render();

    expect($mail->subject)->toBe(__('Weekly selections') . ' · ' . __('from :from to :to', ['from' => $from->translatedFormat('j F'), 'to' => $to->translatedFormat('j F')]))
        ->and($html)->toContain(e(__('Hello :name,', ['name' => 'Thomas'])))
        ->toMatch('/>C2<\/span><span[^>]*>#3<\/span><strong[^>]*>Lefèvre<\/strong> Thomas/u')
        ->not->toContain('Loïc')
        ->toContain('CTT Tourinnes A')
        ->toContain(e(__('Lineup not sent yet')))
        ->toContain('data-mine="' . $sent->id . '"')
        ->not->toContain('data-mine="' . $pending->id . '"')
        ->toContain(route('admin.interclubs.my-match', $sent->id))
        ->toContain(route('admin.user.weekly-selections'));
});

it('reads the fixtures again when the job runs', function (): void {
    Notification::fake();
    weeklyMailFixture($this->team, $this->opponent, 2);
    $player = User::factory()->create(['email' => 'player@example.test']);

    new SendWeeklySelectionsJob('player@example.test', [$player->id], now()->toIso8601String(), now()->addDays(7)->toIso8601String(), [$player->first_name])->handle();

    Notification::assertSentOnDemand(
        WeeklySelectionsNotification::class,
        fn (WeeklySelectionsNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'player@example.test'
            && $notification->days->flatten()->count() === 1,
    );
});

it('names the competition week the fixtures belong to', function (): void {
    weeklyMailFixture($this->team, $this->opponent, -10)->update(['week_number' => 40]);
    weeklyMailFixture($this->opponent, $this->team, -3)->update(['week_number' => 41]);
    weeklyMailFixture($this->team, $this->opponent, 2)->update(['week_number' => 42]);

    $from = now();
    $to = now()->addDays(7);
    $period = __('from :from to :to', ['from' => $from->translatedFormat('j F'), 'to' => $to->translatedFormat('j F')]);
    $days = app(WeeklySelections::class)->between($from, $to);

    $html = (string) new WeeklySelectionsNotification($days, [], $from, $to)->toMail(new User)->render();

    expect($html)->toContain(e(__('Here are the selections for the matches of competition week :weeks, :period.', ['weeks' => 3, 'period' => $period])));
});

it('greets every reader of a shared mailbox', function (): void {
    weeklyMailFixture($this->team, $this->opponent, 2);
    $days = app(WeeklySelections::class)->between(now(), now()->addDays(7));

    $html = (string) new WeeklySelectionsNotification($days, [], now(), now()->addDays(7), ['Sophie', 'Marc'])->toMail(new User)->render();

    expect($html)->toContain(e(__('Hello :names,', ['names' => 'Sophie ' . __('and') . ' Marc'])));
});

/*
| Les byes (grilling du 2026-10-15) : nommés à part, après les jours, sans
| jour ni heure — mais jamais à eux seuls une raison d'écrire.
*/
function weeklyMailBye(Team $ours, int $days): Interclub
{
    return Interclub::factory()->create([
        'season_id' => $ours->season_id,
        'league_id' => $ours->league_id,
        'visited_team_id' => $ours->id,
        'visiting_team_id' => null,
        'total_players' => 4,
        'is_bye' => true,
        'forfeit' => null,
        'start_date_time' => now()->addDays($days),
    ]);
}

it('names the teams on a bye after the days, before the button', function (): void {
    weeklyMailFixture($this->team, $this->opponent, 2);
    weeklyMailBye(Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $this->ownClub->id,
        'name' => 'E',
    ]), 3);

    Notification::fake();
    $player = User::factory()->create(['email' => 'player@example.test']);
    new SendWeeklySelectionsJob('player@example.test', [$player->id], now()->toIso8601String(), now()->addDays(7)->toIso8601String())->handle();

    Notification::assertSentOnDemand(WeeklySelectionsNotification::class, function (WeeklySelectionsNotification $notification): bool {
        $html = (string) $notification->toMail(new User)->render();

        expect($html)->toMatch('/CTT Tourinnes A.*' . preg_quote(e(__('Resting this week (bye)')), '/') . '.*Hommes E.*' . preg_quote(e(__('See on the website')), '/') . '/s');

        return true;
    });
});

it('sends nothing in a week with only teams on a bye', function (): void {
    $this->team->users()->attach(User::factory()->create());
    weeklyMailBye($this->team, 2);

    $this->artisan('interclubs:send-weekly-selections')->assertSuccessful();

    Queue::assertNothingPushed();
});
