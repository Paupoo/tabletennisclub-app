<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\Ranking;
use Database\Seeders\InterclubSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The demo teams mirror a real club: team A holds the strongest players, the
 * last team the weakest, and each category only fields players it admits.
 */
beforeEach(function (): void {
    // Le matricule réel du club, celui de ClubSeeder. Tiré au hasard, il
    // tombait parfois sur celui d'un adversaire du seeder, qui prenait alors
    // notre club pour cet adversaire et lui créait une équipe en double.
    Club::factory()->ownClub()->create(['licence' => 'BBW214']);
    $this->aurelien = User::factory()->create(['email' => 'aurelien.paulus@gmail.com', 'ranking' => Ranking::E4]);

    $this->seed(InterclubSeeder::class);

    $this->ourTeams = fn (string $category): Collection => Team::query()
        ->whereHas('club', fn ($query) => $query->where('is_own_club', true))
        ->whereHas('league', fn ($query) => $query->where('category', $category))
        ->where('season_id', Season::current()->id)
        ->with('users')
        ->orderBy('name')
        ->get();
});

function rankingStrength(Ranking $ranking): int
{
    return (int) array_search($ranking, Ranking::cases(), true);
}

it('ranks every team below the one before it', function (string $category, int $teamCount): void {
    $teams = ($this->ourTeams)($category);

    expect($teams)->toHaveCount($teamCount);

    $teams->sliding(2)->each(function ($pair): void {
        [$stronger, $weaker] = $pair->values()->all();

        $weakestOfStronger = $stronger->users->max(fn (User $user): int => rankingStrength($user->ranking));
        $strongestOfWeaker = $weaker->users->min(fn (User $user): int => rankingStrength($user->ranking));

        expect($weakestOfStronger)->toBeLessThanOrEqual($strongestOfWeaker, "{$stronger->name} vs {$weaker->name}");
    });

    $teams->each(fn (Team $team) => expect($team->users)->toHaveCount(7));
})->with([
    'men' => ['MEN', 5],
    'veterans' => ['VETERANS', 3],
]);

it('fields C players in the men A and no stronger than E in the men E', function (): void {
    $teams = ($this->ourTeams)('MEN')->keyBy('name');

    expect($teams['A']->users->every(fn (User $user): bool => str_starts_with($user->ranking->value, 'C')))->toBeTrue()
        ->and($teams['E']->users->every(fn (User $user): bool => str_starts_with($user->ranking->value, 'E') || $user->ranking === Ranking::NC))->toBeTrue();
});

it('never fields an unaffiliated player', function (): void {
    $players = collect(['MEN', 'VETERANS', 'WOMEN'])
        ->flatMap(fn (string $category) => ($this->ourTeams)($category)->flatMap->users);

    expect($players)->not->toBeEmpty()
        ->and($players->contains(fn (User $user): bool => $user->ranking === Ranking::NA))->toBeFalse();
});

it('keeps the account owner as a non-playing captain of both A teams', function (): void {
    foreach (['MEN', 'VETERANS'] as $category) {
        $teamA = ($this->ourTeams)($category)->firstWhere('name', 'A');

        expect($teamA->captain_id)->toBe($this->aurelien->id)
            ->and($teamA->users->contains($this->aurelien))->toBeFalse();
    }

    expect($this->aurelien->fresh()->ranking)->toBe(Ranking::E4);
});

it('fields only women in the women team and a few in the men teams', function (): void {
    $women = ($this->ourTeams)('WOMEN')->flatMap->users;
    $men = ($this->ourTeams)('MEN')->flatMap->users;

    expect($women)->toHaveCount(7)
        ->and($women->every(fn (User $user): bool => $user->gender === Gender::WOMEN))->toBeTrue()
        ->and($men->filter(fn (User $user): bool => $user->gender === Gender::WOMEN))->toHaveCount(3);
});

it('fields only veterans in the veterans teams', function (): void {
    $veterans = ($this->ourTeams)('VETERANS')->flatMap->users;

    expect($veterans->every(fn (User $user): bool => $user->isVeteran()))->toBeTrue();
});

it('keeps a player in a single pool', function (): void {
    $playerIds = collect(['MEN', 'VETERANS', 'WOMEN'])
        ->flatMap(fn (string $category) => ($this->ourTeams)($category)->flatMap->users->pluck('id'));

    expect($playerIds->duplicates())->toBeEmpty();
});

it('reuses its demo players on a second run', function (): void {
    $usersAfterFirstRun = User::count();

    $this->seed(InterclubSeeder::class);

    expect(User::count())->toBe($usersAfterFirstRun);
});
