<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Notifications\InterclubAvailabilityRequestNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubLineupBroadcastNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubPlayerRemovedNotification;
use App\Domains\Competitions\Interclub\Notifications\InterclubSelectionNotification;
use App\Domains\Competitions\Interclub\Services\InterclubAvailabilityService;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Jobs\SendInterclubAvailabilityRequestJob;
use App\Jobs\SendInterclubLineupBroadcastJob;
use App\Jobs\SendInterclubPlayerRemovedJob;
use App\Jobs\SendInterclubSelectionJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();

    $this->season = Season::factory()->create(['is_active' => true]);
    $this->league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    $this->captain = User::factory()->isCompetitor()->create();
    $this->player1 = User::factory()->isCompetitor()->create();
    $this->player2 = User::factory()->isCompetitor()->create();
    $this->player3 = User::factory()->isCompetitor()->create();

    $club = Club::factory()->ownClub()->create();

    $this->team = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'captain_id' => $this->captain->id,
        'club_id' => $club->id,
    ]);

    $this->team->users()->attach([$this->captain->id, $this->player1->id, $this->player2->id, $this->player3->id]);

    $this->interclub = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(7),
    ]);

    $this->service = app(InterclubAvailabilityService::class);
});

it('sends selection notification to each selected player', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);

    $this->service->confirmSelection($this->interclub, 'Départ à 18h45.');

    Notification::assertSentTo($this->player1, InterclubSelectionNotification::class);
    Notification::assertSentTo($this->player2, InterclubSelectionNotification::class);
    Notification::assertNotSentTo($this->player3, InterclubSelectionNotification::class);
});

it('selection notification carries the captain message', function (): void {
    $this->interclub->select($this->player1);
    $message = 'Rendez-vous à 18h30 au club.';

    $this->service->confirmSelection($this->interclub, $message);

    Notification::assertSentTo(
        $this->player1,
        InterclubSelectionNotification::class,
        fn ($notification): bool => $notification->captainMessage === $message,
    );
});

it('sets selection_confirmed_at on pivot after confirmation', function (): void {
    $this->interclub->select($this->player1);

    $this->service->confirmSelection($this->interclub);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player1->id,
        'is_selected' => true,
    ]);

    $pivot = DB::table('interclub_user')
        ->where('interclub_id', $this->interclub->id)
        ->where('user_id', $this->player1->id)
        ->first();

    expect($pivot->selection_confirmed_at)->not->toBeNull();
});

it('sends availability request only to players who have not responded', function (): void {
    $responded = User::factory()->isCompetitor()->create();
    $this->team->users()->syncWithoutDetaching([$responded->id]);
    $this->interclub->markAvailability($responded, InterclubAvailability::AVAILABLE);

    $this->service->requestAvailability($this->interclub);

    Notification::assertNotSentTo($responded, InterclubAvailabilityRequestNotification::class);
    Notification::assertSentTo($this->player1, InterclubAvailabilityRequestNotification::class);
    Notification::assertSentTo($this->player2, InterclubAvailabilityRequestNotification::class);
});

it('sends broadcast notification to non-selected team members', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);

    $this->service->confirmSelection($this->interclub, 'Rendez-vous à 18h30.');

    Notification::assertSentTo($this->captain, InterclubLineupBroadcastNotification::class);
    Notification::assertSentTo($this->player3, InterclubLineupBroadcastNotification::class);
});

it('selected players do not receive the broadcast notification', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);

    $this->service->confirmSelection($this->interclub);

    Notification::assertNotSentTo($this->player1, InterclubLineupBroadcastNotification::class);
    Notification::assertNotSentTo($this->player2, InterclubLineupBroadcastNotification::class);
});

it('non-selected players do not receive the selection notification', function (): void {
    $this->interclub->select($this->player1);

    $this->service->confirmSelection($this->interclub);

    Notification::assertNotSentTo($this->player2, InterclubSelectionNotification::class);
    Notification::assertNotSentTo($this->player3, InterclubSelectionNotification::class);
});

it('broadcast notification carries the captain message and selected lineup', function (): void {
    $this->interclub->select($this->player1);
    $message = 'Départ depuis le club à 18h.';

    $this->service->confirmSelection($this->interclub, $message);

    Notification::assertSentTo(
        $this->player3,
        InterclubLineupBroadcastNotification::class,
        fn ($notification): bool => $notification->captainMessage === $message
            && $notification->selectedPlayers->contains('id', $this->player1->id),
    );
});

// ── notifySelectionChange ───────────────────────────────────────────────────

it('notifies a removed player even when the resulting selection is incomplete', function (): void {
    // total_players = 4, only player2 remains selected after removing player1 — incomplete.
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->users()->updateExistingPivot($this->player1->id, ['selection_confirmed_at' => now()]);
    $this->interclub->users()->updateExistingPivot($this->player2->id, ['selection_confirmed_at' => now()]);
    $this->interclub->deselect($this->player1);

    $this->service->notifySelectionChange($this->interclub, addedUserIds: [], removedUserIds: [$this->player1->id]);

    Notification::assertSentTo($this->player1, InterclubPlayerRemovedNotification::class);

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $this->player1->id,
        'selection_confirmed_at' => null,
    ]);
});

it('does not notify added players or broadcast the team when the selection is incomplete', function (): void {
    // total_players = 4, only 1 player selected after the change — incomplete.
    $this->interclub->select($this->player1);

    $this->service->notifySelectionChange($this->interclub, addedUserIds: [$this->player1->id], removedUserIds: []);

    Notification::assertNotSentTo($this->player1, InterclubSelectionNotification::class);
    Notification::assertNotSentTo($this->captain, InterclubLineupBroadcastNotification::class);
});

it('notifies added players and broadcasts an update when the selection becomes complete again', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->select($this->player3);
    $newPlayer = User::factory()->isCompetitor()->create();
    $this->team->users()->attach($newPlayer->id);
    $this->interclub->select($newPlayer);

    $this->service->notifySelectionChange($this->interclub, addedUserIds: [$newPlayer->id], removedUserIds: []);

    Notification::assertSentTo($newPlayer, InterclubSelectionNotification::class);
    Notification::assertSentTo(
        $this->captain,
        InterclubLineupBroadcastNotification::class,
        fn ($notification): bool => $notification->isUpdate === true,
    );

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $this->interclub->id,
        'user_id' => $newPlayer->id,
    ]);
    $pivot = DB::table('interclub_user')
        ->where('interclub_id', $this->interclub->id)
        ->where('user_id', $newPlayer->id)
        ->first();
    expect($pivot->selection_confirmed_at)->not->toBeNull();
});

it('does not notify unchanged players when the selection is updated', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->select($this->player3);
    $newPlayer = User::factory()->isCompetitor()->create();
    $this->team->users()->attach($newPlayer->id);
    $this->interclub->select($newPlayer);

    $this->service->notifySelectionChange($this->interclub, addedUserIds: [$newPlayer->id], removedUserIds: []);

    Notification::assertNotSentTo($this->player1, InterclubSelectionNotification::class);
    Notification::assertNotSentTo($this->player2, InterclubSelectionNotification::class);
});

// ── mail rendering ───────────────────────────────────────────────────────────
// Notification::fake() does NOT render the mail, so these render the blade views directly.

it('renders the removed-player mail with the match details', function (): void {
    $html = (string) app(Markdown::class)->render('mail.interclub.removed', [
        'notifiable' => $this->player1,
        'ourTeamName' => 'CTT Ottignies-Blocry A',
        'opponent' => 'ARC EN CIEL CTT F',
        'dateStr' => '12/09/2026 at 19:00',
        'address' => 'Rue du Sport 1',
        'venue' => 'Home',
        'url' => 'https://example.test/match',
    ]);

    expect($html)
        ->toContain("n'êtes plus sélectionné")
        ->toContain('CTT Ottignies-Blocry A')
        ->toContain('ARC EN CIEL CTT F');
});

it('renders the lineup mail with an updated heading when isUpdate is true', function (): void {
    $html = (string) app(Markdown::class)->render('mail.interclub.lineup', [
        'notifiable' => $this->player1,
        'ourTeamName' => 'CTT Ottignies-Blocry A',
        'opponent' => 'ARC EN CIEL CTT F',
        'dateStr' => '12/09/2026 at 19:00',
        'address' => 'Rue du Sport 1',
        'venue' => 'Home',
        'url' => 'https://example.test/match',
        'selectedPlayers' => collect([$this->player1]),
        'category' => 'MEN',
        'captainMessage' => '',
        'isUpdate' => true,
    ]);

    expect($html)->toContain('mise à jour');
});

it('renders the lineup as a table ordered by force index rather than inline parentheses', function (): void {
    $this->player1->update(['first_name' => 'Alice', 'last_name' => 'Zulu', 'ranking' => 'B4', 'force_list' => 3]);
    $this->player2->update(['first_name' => 'Bob', 'last_name' => 'Alpha', 'ranking' => 'C2', 'force_list' => 12]);

    $html = (string) app(Markdown::class)->render('mail.interclub.selection', [
        'notifiable' => $this->player1,
        'ourTeamName' => 'CTT Ottignies-Blocry A',
        'opponent' => 'ARC EN CIEL CTT F',
        'dateStr' => '12/09/2026 at 19:00',
        'address' => 'Rue du Sport 1',
        'venue' => 'Home',
        'url' => 'https://example.test/match',
        'selectedPlayers' => collect([$this->player2, $this->player1]),
        'category' => 'MEN',
        'captainMessage' => '',
    ]);

    expect($html)
        ->toContain('Liste de force')
        ->toContain('#3')
        ->toContain('#12')
        ->toContain('B4')
        ->not->toContain('Alice Zulu (3)');

    // Lowest force index first: Alice (#3) is listed before Bob (#12).
    expect(strpos($html, 'Alice'))->toBeLessThan(strpos($html, 'Bob'));
});

it('marks the recipient in the selection lineup and leaves the others unmarked', function (): void {
    $this->player1->update(['first_name' => 'Alice', 'last_name' => 'Zulu', 'force_list' => 3]);
    $this->player2->update(['first_name' => 'Bob', 'last_name' => 'Alpha', 'force_list' => 12]);

    $html = (string) app(Markdown::class)->render('mail.interclub.selection', [
        'notifiable' => $this->player1,
        'ourTeamName' => 'CTT Ottignies-Blocry A',
        'opponent' => 'ARC EN CIEL CTT F',
        'dateStr' => '12/09/2026 at 19:00',
        'address' => 'Rue du Sport 1',
        'venue' => 'Home',
        'url' => 'https://example.test/match',
        'selectedPlayers' => collect([$this->player1, $this->player2]),
        'category' => 'MEN',
        'captainMessage' => '',
    ]);

    expect(substr_count($html, '>vous<'))->toBe(1);
});

it('falls back to a dash and drops the force column when nobody is ranked in the category', function (): void {
    $this->player1->update(['force_list' => null, 'force_list_women' => null]);

    $html = (string) app(Markdown::class)->render('mail.interclub.selection', [
        'notifiable' => $this->player1,
        'ourTeamName' => 'CTT Ottignies-Blocry A',
        'opponent' => 'ARC EN CIEL CTT F',
        'dateStr' => '12/09/2026 at 19:00',
        'address' => 'Rue du Sport 1',
        'venue' => 'Home',
        'url' => 'https://example.test/match',
        'selectedPlayers' => collect([$this->player1]),
        'category' => 'MEN',
        'captainMessage' => '',
    ]);

    expect($html)
        ->toContain($this->player1->full_name)
        ->not->toContain('Liste de force');
});

it('sends the availability request to our own team when we play away', function (): void {
    $opponentClub = Club::factory()->create(['is_own_club' => false]);
    $opponentTeam = Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'club_id' => $opponentClub->id,
    ]);

    $away = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $this->league->id,
        'visited_team_id' => $opponentTeam->id,
        'visiting_team_id' => $this->team->id,
        'total_players' => 4,
        'start_date_time' => now()->addDays(7),
    ]);

    $this->service->requestAvailability($away);

    Notification::assertSentTo($this->player1, InterclubAvailabilityRequestNotification::class);
    Notification::assertSentTo($this->player2, InterclubAvailabilityRequestNotification::class);
    Notification::assertSentTo($this->player3, InterclubAvailabilityRequestNotification::class);
});

/*
|--------------------------------------------------------------------------
| Sending leaves the request
|--------------------------------------------------------------------------
|
| A lineup is twelve recipients. Sent inline, that is twelve blocking SMTP
| round trips inside the Livewire request — the screen froze for fifteen
| seconds with nothing to say for itself, and captains clicked again.
|
*/
it('queues one job per recipient instead of mailing inside the request', function (): void {
    Queue::fake();

    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);

    $this->service->confirmSelection($this->interclub, 'Départ à 18h45.');

    Queue::assertPushed(SendInterclubSelectionJob::class, 2);
    Queue::assertPushed(SendInterclubLineupBroadcastJob::class, 2);
});

it('queues the removal notice and the availability request too', function (): void {
    Queue::fake();

    $this->interclub->select($this->player1);
    $this->service->notifySelectionChange($this->interclub, [], [$this->player1->id]);
    $this->service->requestAvailability($this->interclub);

    Queue::assertPushed(SendInterclubPlayerRemovedJob::class, 1);
    Queue::assertPushed(SendInterclubAvailabilityRequestJob::class, 4);
});

/*
| Jouer à 3 : la convocation le dit, et l'annonce au noyau devient un appel.
| Sans cela, les trois convoqués cherchent un quatrième qu'on n'attend pas, et
| les autres ignorent qu'une place est encore à prendre.
*/
it('tells the convoked players the team plays short-handed', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->select($this->player3);
    $this->interclub->update(['short_handed_confirmed_at' => now(), 'short_handed_confirmed_by' => $this->captain->id]);

    $html = (string) new InterclubSelectionNotification($this->interclub->fresh())->toMail($this->player1)->render();

    expect($html)->toContain('Nous jouerons à 3 sur 4')
        ->toContain('Les matchs du joueur manquant seront perdus');
});

it('asks the rest of the team to step in when the team plays short-handed', function (): void {
    $this->interclub->select($this->player1);
    $this->interclub->select($this->player2);
    $this->interclub->select($this->player3);
    $this->interclub->update(['short_handed_confirmed_at' => now(), 'short_handed_confirmed_by' => $this->captain->id]);

    $interclub = $this->interclub->fresh();
    $html = (string) new InterclubLineupBroadcastNotification($interclub, $interclub->getSelectedPlayers())->toMail($this->captain)->render();

    expect($html)->toContain('Il nous manque un joueur')
        ->toContain('prévenez votre capitaine');
});

/*
| Le WO se note sur la feuille de match : le mail le nomme, pour que personne
| ne l'attende à la table et que le capitaine le recopie tel quel.
*/
it('names the walkover player in both lineup mails', function (): void {
    foreach ([$this->player1, $this->player2, $this->captain, $this->player3] as $player) {
        $this->interclub->select($player);
    }

    $this->interclub->users()->updateExistingPivot($this->player3->id, ['is_walkover' => true]);
    $this->interclub->update(['short_handed_confirmed_at' => now(), 'short_handed_confirmed_by' => $this->captain->id]);

    $interclub = $this->interclub->fresh();
    $selection = (string) new InterclubSelectionNotification($interclub)->toMail($this->player1)->render();
    // Le job de diffusion recharge les joueurs sans le pivot : c'est ainsi que
    // les non-sélectionnés les reçoivent, et la marque doit y être aussi.
    $broadcast = (string) new InterclubLineupBroadcastNotification($interclub, User::whereIn('id', $interclub->getSelectedPlayers()->pluck('id'))->get())->toMail($this->player1)->render();

    foreach ([$selection, $broadcast] as $html) {
        expect($html)->toContain('à 3 sur 4')
            // La marque dans le tableau suffit : pas de phrase qui la répète.
            ->not->toContain('est inscrit WO');

        // Dans le tableau, la ligne du WO porte la marque, et elle seule.
        $rows = collect(explode('</tr>', $html));

        expect($rows->first(fn (string $row): bool => str_contains($row, $this->player3->full_name . '</span>')))->toContain('>WO</span>')
            ->and($rows->filter(fn (string $row): bool => str_contains($row, '>WO</span>')))->toHaveCount(1);
    }
});

it('says nothing of the sort for a full lineup', function (): void {
    $this->interclub->select($this->player1);

    $interclub = $this->interclub->fresh();

    expect((string) new InterclubSelectionNotification($interclub)->toMail($this->player1)->render())->not->toContain('Nous jouerons à')
        ->and((string) new InterclubLineupBroadcastNotification($interclub, $interclub->getSelectedPlayers())->toMail($this->captain)->render())->not->toContain('Il nous manque');
});
