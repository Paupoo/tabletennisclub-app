<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Notifications\CaptainLineupReminderNotification;
use App\Jobs\SendCaptainLineupReminderJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/*
| Problème 8, piste C2 : chaque dimanche à 18 h, un seul e-mail par capitaine
| liste ses rencontres des trois semaines à venir dont la compo n'est pas partie.
| Rien si tout est envoyé — c'est la seule façon de ne plus le recevoir.
*/
beforeEach(function (): void {
    Queue::fake();

    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    $this->ownClub = Club::factory()->ownClub()->create();

    $this->captain = User::factory()->isCompetitor()->create();
    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $this->ownClub->id,
        'captain_id' => $this->captain->id,
    ]);
});

function reminderFixture(Team $team, int $days): Interclub
{
    return Interclub::factory()->create([
        'season_id' => $team->season_id,
        'league_id' => $team->league_id,
        'visited_team_id' => $team->id,
        'total_players' => 2,
        'is_bye' => false,
        'start_date_time' => now()->addDays($days),
    ]);
}

it('reminds a captain of every lineup of the next three weeks not sent yet', function (): void {
    $saved = reminderFixture($this->team, 10);
    $saved->select(User::factory()->isCompetitor()->create());
    $saved->select(User::factory()->isCompetitor()->create());

    $nothing = reminderFixture($this->team, 18);
    reminderFixture($this->team, 30);

    $this->artisan('interclubs:remind-captains')->assertSuccessful();

    Queue::assertPushed(SendCaptainLineupReminderJob::class, 1);
    Queue::assertPushed(SendCaptainLineupReminderJob::class, fn (SendCaptainLineupReminderJob $job): bool => $job->captainId === $this->captain->id
        && $job->interclubIds === [$saved->id, $nothing->id]);
});

it('sends nothing to a captain whose lineups have all gone out', function (): void {
    $sent = reminderFixture($this->team, 10);

    foreach ([User::factory()->isCompetitor()->create(), User::factory()->isCompetitor()->create()] as $player) {
        $sent->users()->attach($player->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);
    }

    $this->artisan('interclubs:remind-captains')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('is scheduled on Sunday at 18:00', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'interclubs:remind-captains'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 18 * * 0');
});

it('lists the matches and links to the selections screen', function (): void {
    $match = reminderFixture($this->team, 10);

    $html = (string) new CaptainLineupReminderNotification(collect([$match]))->toMail($this->captain)->render();

    expect($html)->toContain($match->start_date_time->format('d/m/Y'))
        ->toContain(route('admin.interclubs.captain-selection'))
        ->toContain(__('Only the players added or removed are told of a change.'));
});

it('leaves out a lineup sent between the command and the mail', function (): void {
    Notification::fake();

    $pending = reminderFixture($this->team, 10);
    $sent = reminderFixture($this->team, 12);

    foreach ([User::factory()->isCompetitor()->create(), User::factory()->isCompetitor()->create()] as $player) {
        $sent->users()->attach($player->id, ['is_selected' => true, 'selection_confirmed_at' => now()]);
    }

    new SendCaptainLineupReminderJob($this->captain->id, [$pending->id, $sent->id])->handle();

    Notification::assertSentTo(
        $this->captain,
        CaptainLineupReminderNotification::class,
        fn (CaptainLineupReminderNotification $n): bool => $n->interclubs->pluck('id')->all() === [$pending->id],
    );
});
