<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubImport;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    afttClubTeams('get-club-teams-bbw214-two-divisions.xml');
    afttFaultOn(reset: true);
    fakeTabt();

    $this->season = Season::factory()->create(['name' => '2026-2027']);
    $this->ownClub = Club::factory()->create(['is_own_club' => true, 'licence' => 'BBW214']);

    knownOpponents();
});

it('imports the season the federation says is running', function (): void {
    $this->artisan('interclubs:import-aftt')
        ->expectsOutputToContain('2026-2027')
        ->assertSuccessful();

    expect(Interclub::where('season_id', $this->season->id)->count())->toBeGreaterThan(0)
        ->and(InterclubImport::count())->toBe(1);
});

it('stops when the club has no season by that name', function (): void {
    $this->season->update(['name' => '2019-2020']);

    $this->artisan('interclubs:import-aftt')
        ->expectsOutputToContain('2026-2027')
        ->assertFailed();

    expect(Interclub::count())->toBe(0)
        ->and(InterclubImport::count())->toBe(0);
});

it('stops when the club has no federation licence to import against', function (): void {
    $this->ownClub->update(['licence' => '']);

    $this->artisan('interclubs:import-aftt')->assertFailed();

    expect(Interclub::count())->toBe(0);
});

it('refuses to rebuild a season that members have already answered on', function (): void {
    $this->artisan('interclubs:import-aftt')->assertSuccessful();

    Interclub::first()->users()->attach(User::factory()->create()->id, ['availability' => 'yes']);

    $before = Interclub::count();

    $this->artisan('interclubs:import-aftt --fresh')
        ->expectsOutputToContain('--force')
        ->assertFailed();

    expect(Interclub::count())->toBe($before);
});

it('rebuilds anyway when told to, and says what it is about to destroy', function (): void {
    $this->artisan('interclubs:import-aftt')->assertSuccessful();

    Interclub::first()->users()->attach(User::factory()->create()->id, ['availability' => 'yes']);

    $this->artisan('interclubs:import-aftt --fresh --force')->assertSuccessful();

    expect(InterclubImport::latest('id')->first()->is_fresh)->toBeTrue();
});

it('asks before rebuilding a season nobody has touched', function (): void {
    $this->artisan('interclubs:import-aftt --fresh')
        ->expectsConfirmation('Delete them and rebuild from the federation?', 'no')
        ->assertFailed();

    expect(Interclub::count())->toBe(0)
        ->and(League::count())->toBe(0)
        ->and(Team::count())->toBe(0);
});

it('rebuilds once the confirmation is given', function (): void {
    $this->artisan('interclubs:import-aftt --fresh')
        ->expectsConfirmation('Delete them and rebuild from the federation?', 'yes')
        ->assertSuccessful();

    expect(Interclub::count())->toBeGreaterThan(0);
});

it('takes a season told to it rather than the one the federation calls current', function (): void {
    $other = Season::factory()->create(['name' => '2025-2026']);

    $this->artisan('interclubs:import-aftt --season=2025-2026')
        ->expectsOutputToContain('2025-2026')
        ->assertSuccessful();

    expect(InterclubImport::first()->season_id)->toBe($other->id)
        ->and(Interclub::where('season_id', $this->season->id)->exists())->toBeFalse();
});

it('stops when the federation has never heard of the season asked for', function (): void {
    Season::factory()->create(['name' => '2043-2044']);

    $this->artisan('interclubs:import-aftt --season=2043-2044')->assertFailed();

    expect(InterclubImport::count())->toBe(0);
});

/**
 * A database that has not been migrated is the likeliest way for this command to
 * fail on a real machine, and it must not be reported as a federation problem:
 * the message is what somebody reads at 23:00 before deciding what to go and fix.
 */
it('stops before touching anything when the schema is not migrated', function (): void {
    Schema::table('interclubs', function (Blueprint $table): void {
        $table->dropUnique('interclubs_season_aftt_match_unique');
        $table->dropColumn('aftt_match_id');
    });

    $this->artisan('interclubs:import-aftt --fresh --force')
        ->expectsOutputToContain('php artisan migrate')
        ->assertFailed();

    // It must refuse before the wipe, not during it.
    expect(Season::whereKey($this->season->id)->exists())->toBeTrue();
});
