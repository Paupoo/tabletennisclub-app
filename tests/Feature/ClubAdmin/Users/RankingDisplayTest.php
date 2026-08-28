<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Ranking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Casting User::$ranking to the enum turns every bare `{{ $user->ranking }}`
 * into "Object of class Ranking could not be converted to string". PHPStan does
 * not analyse resources/views/pages, so these screens are only covered by
 * whatever renders them. This walks the ones the cast touched, with an unranked
 * member — the case that used to print "NA" and now prints "N/A".
 */
it('renders every screen that shows a ranking', function (string $route): void {
    Club::factory()->ownClub()->create();
    $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);

    $admin = User::factory()->isAdmin()->create(['ranking' => Ranking::NA->value]);
    Subscription::factory()->for($admin)->create(['season_id' => $season->id, 'status' => 'confirmed']);

    $member = User::factory()->create(['ranking' => Ranking::NA->value]);
    Subscription::factory()->for($member)->create(['season_id' => $season->id, 'status' => 'confirmed']);

    $team = Team::factory()->create(['season_id' => $season->id]);
    $team->users()->attach([$admin->id, $member->id]);

    $url = str_contains($route, '{user}')
        ? route(str_replace('{user}', '', $route), $admin)
        : route($route, str_contains($route, 'teams.') ? $team : []);

    $this->actingAs($admin)->get($url)->assertSuccessful();
})->with([
    'liste des membres' => 'admin.users.index',
    'effectif' => 'admin.subscriptions.roster',
    'affiliations' => 'admin.users.registrations',
    'équipes' => 'admin.interclubs.teams',
    'fiche équipe' => 'admin.interclubs.teams.show',
    'édition équipe' => 'admin.interclubs.teams.edit',
]);

it('renders the my-space screens that show a ranking', function (string $route): void {
    Club::factory()->ownClub()->create();
    $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);

    $member = User::factory()->create(['ranking' => Ranking::NA->value]);
    Subscription::factory()->for($member)->create(['season_id' => $season->id, 'status' => 'confirmed']);

    $this->actingAs($member)->get(route($route, $member))->assertSuccessful();
})->with([
    'annuaire' => 'admin.user.directory',
    'profil' => 'admin.user.profile',
    'mes équipes' => 'admin.user.teams',
]);

it('shows the unranked member as N/A rather than NA', function (): void {
    Club::factory()->ownClub()->create();
    $season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);

    $admin = User::factory()->isAdmin()->create(['ranking' => Ranking::NA->value]);
    Subscription::factory()->for($admin)->create(['season_id' => $season->id, 'status' => 'confirmed']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertSuccessful()
        ->assertSee(__('N/A'));
});
