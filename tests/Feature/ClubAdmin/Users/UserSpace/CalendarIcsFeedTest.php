<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
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
        ->assertSee('signature=');
});
