<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Services\EventPostService;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Services\TournamentFinalPhaseService;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Competitions\Tournament\Services\TournamentTableService;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\TournamentObjectiveEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TournamentSeeder extends Seeder
{
    public function __construct(
        private readonly TournamentTableService $tableService,
        private readonly TournamentPoolService $poolService,
        private readonly TournamentMatchService $matchService,
        private readonly TournamentFinalPhaseService $finalPhaseService,
        private readonly TournamentService $tournamentService,
        private readonly EventPostService $eventPostService,
    ) {}

    public function run(): void
    {
        $players = $this->createTournamentPlayers();

        $this->seedDraft();
        $this->seedOpenWithWaitlist($players);
        $this->seedOpenTooFewPlayers($players);
        $this->seedSetupNoHandicap($players);
        $this->seedSetupHandicap($players);
        $this->seedSetupSetSecBestOf7($players);
        $this->seedSetupDoubles($players);
        $this->seedLivePoolsInProgress($players);
        $this->seedLivePoolsDone($players);
        $this->seedClosed($players);
    }

    private function attachPlayers(Tournament $t, Collection $players, string $status = 'confirmed'): void
    {
        foreach ($players as $player) {
            $t->users()->attach($player->id, ['registration_status' => $status]);
        }
        $this->tournamentService->countRegisteredUsers($t);
    }

    private function completeAllPoolMatches(Tournament $t): void
    {
        TournamentMatch::where('tournament_id', $t->id)
            ->whereNotNull('pool_id')
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->get()
            ->each(fn ($m) => $m->recordResult($this->simulateMatch($t->sets_to_win)));
    }

    private function completeHalfPoolMatches(Tournament $t): void
    {
        $matches = TournamentMatch::where('tournament_id', $t->id)
            ->whereNotNull('pool_id')
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->get();

        $matches->take((int) floor($matches->count() / 2))
            ->each(fn ($m) => $m->recordResult($this->simulateMatch($t->sets_to_win)));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createTournamentPlayers(): Collection
    {
        $levels = [Ranking::B0, Ranking::C4, Ranking::D0, Ranking::D4, Ranking::E2];

        return collect($levels)->flatMap(
            fn (Ranking $r) => User::factory()->isCompetitor()->setRanking($r)->count(10)->create()
        );
    }

    private function generatePools(Tournament $t, int $nbPools): void
    {
        $this->poolService->distributePlayersInPools($t, $nbPools);
        $t->refresh()->load('pools');
        foreach ($t->pools as $pool) {
            $this->matchService->generateMatches($pool);
        }
    }

    private function makeTournament(array $overrides): Tournament
    {
        $base = now()->addDays(7)->setTime(9, 0);

        return Tournament::create(array_merge([
            'name' => 'Tournoi',
            'description' => fake()->paragraph(),
            'location' => 'Centre Sportif Jean Demeester, Ottignies',
            'start_date' => $base,
            'end_date' => $base->copy()->addHours(8),
            'max_users' => 32,
            'price' => 0,
            'status' => TournamentStatusEnum::SETUP,
            'match_type' => 'single',
            'has_handicap_points' => false,
            'sets_to_win' => 3,
            'deuce_enabled' => true,
            'pool_size' => 4,
            'nb_pools' => 4,
            'nb_qualifiers_per_pool' => 2,
            'logistics_buffer_minutes' => 3,
            'objective' => TournamentObjectiveEnum::Competitive,
        ], $overrides));
    }

    private function seedClosed(Collection $players): void
    {
        $t = $this->makeTournament([
            'name' => '[CLOSED] Simple — tournoi clôturé',
            'status' => TournamentStatusEnum::PENDING,
            'nb_pools' => 2,
            'nb_qualifiers_per_pool' => 2,
            'start_date' => Carbon::yesterday()->setTime(9, 0),
            'end_date' => Carbon::yesterday()->setTime(17, 0),
        ]);
        $this->setupInfra($t);
        $this->attachPlayers($t, $players->slice(0, 8));
        $this->generatePools($t, 2);
        $this->completeAllPoolMatches($t);

        // Launch bracket: 2 pools × 2 qualifiers = 4 players → round_4
        $this->finalPhaseService->configureKnockoutPhase($t, 'round_4');

        // Complete semis first (propagates winners to final, losers to bronze)
        $semis = TournamentMatch::where('tournament_id', $t->id)
            ->where('round', 'semifinal')
            ->orderBy('match_order')
            ->get();

        foreach ($semis as $semi) {
            $winnerId = $semi->player1_id ?? $semi->player2_id;
            $this->finalPhaseService->completeMatch($semi, $winnerId);
        }

        // Complete bronze and final (players propagated by completeMatch above)
        $bronze = TournamentMatch::where('tournament_id', $t->id)
            ->where('round', 'bronze')
            ->first();

        if ($bronze?->player1_id) {
            $this->finalPhaseService->completeMatch($bronze, $bronze->player1_id);
        }

        $final = TournamentMatch::where('tournament_id', $t->id)
            ->where('round', 'final')
            ->first();

        if ($final?->player1_id) {
            $this->finalPhaseService->completeMatch($final, $final->player1_id);
        }

        $t->update(['status' => TournamentStatusEnum::CLOSED]);
    }

    // ── Scénarios ─────────────────────────────────────────────────────────────

    private function seedDraft(): void
    {
        $this->makeTournament([
            'name' => '[DRAFT] Simple — configuration vide',
            'status' => TournamentStatusEnum::DRAFT,
        ]);
    }

    private function seedLivePoolsDone(Collection $players): void
    {
        $t = $this->makeTournament([
            'name' => '[LIVE] Simple — poules finies · finale à lancer',
            'status' => TournamentStatusEnum::PENDING,
            'nb_pools' => 2,
            'nb_qualifiers_per_pool' => 2,
            'start_date' => now()->setTime(9, 0),
            'end_date' => now()->setTime(17, 0),
        ]);
        $this->setupInfra($t);
        $this->attachPlayers($t, $players->slice(0, 8));
        $this->generatePools($t, 2);
        $this->completeAllPoolMatches($t);
        // Bracket intentionally NOT launched
    }

    private function seedLivePoolsInProgress(Collection $players): void
    {
        $t = $this->makeTournament([
            'name' => '[LIVE] Simple — poules en cours',
            'status' => TournamentStatusEnum::PENDING,
            'nb_pools' => 3,
            'has_handicap_points' => true,
            'price' => 10,
            'start_date' => now()->setTime(9, 0),
            'end_date' => now()->setTime(17, 0),
        ]);
        $this->setupInfra($t);
        $this->attachPlayers($t, $players->slice(0, 12));
        $this->generatePools($t, 3);
        $this->completeHalfPoolMatches($t);
    }

    private function seedOpenTooFewPlayers(Collection $players): void
    {
        $t = $this->makeTournament([
            'name' => '[OPEN] Simple — 3 inscrits (trop peu)',
            'status' => TournamentStatusEnum::PUBLISHED,
            'max_users' => 20,
        ]);
        $this->setupInfra($t);
        $this->attachPlayers($t, $players->slice(20, 3));
    }

    private function seedOpenWithWaitlist(Collection $players): void
    {
        $t = $this->makeTournament([
            'name' => '[OPEN] Simple — inscriptions + liste d\'attente',
            'status' => TournamentStatusEnum::PUBLISHED,
            'max_users' => 16,
            'price' => 10,
        ]);
        $this->setupInfra($t);

        $this->attachPlayers($t, $players->slice(0, 16));

        foreach ($players->slice(16, 4)->values() as $i => $player) {
            $t->users()->attach($player->id, [
                'registration_status' => 'waiting',
                'waitlist_position' => $i + 1,
            ]);
        }
        $this->tournamentService->countRegisteredUsers($t);

        $this->eventPostService->upsert(
            model: $t,
            type: ClubEventTypeEnum::TOURNAMENT,
            title: 'Tournoi du club — inscriptions ouvertes',
            description: 'Rejoignez notre tournoi interne ! Tous niveaux bienvenus. Inscriptions en ligne, places limitées.',
            location: 'Centre Sportif Jean Demeester, Ottignies',
            featured: true,
            status: EventPostStatusEnum::PUBLISHED,
            syncData: [
                'event_date' => $t->start_date->toDateString(),
                'start_time' => $t->start_date->format('H:i:s'),
                'end_time' => $t->end_date?->format('H:i:s'),
                'price' => '10 €',
                'max_participants' => $t->max_users,
                'icon' => '🏆',
            ],
            featuredUntil: $t->start_date->toDateString(),
        );
    }

    private function seedSetupDoubles(Collection $players): void
    {
        $t = $this->makeTournament([
            'name' => '[SETUP] Double — paires composées',
            'match_type' => 'double',
            'nb_pools' => 2,
            'max_users' => 16,
            'price' => 10,
        ]);
        $this->setupInfra($t);

        $doublePlayers = $players->slice(0, 16)->values();

        foreach ($doublePlayers as $player) {
            $t->users()->attach($player->id, ['registration_status' => 'confirmed']);
        }
        $this->tournamentService->countRegisteredUsers($t);

        for ($i = 0; $i < 8; $i++) {
            TournamentPair::create([
                'tournament_id' => $t->id,
                'player1_id' => $doublePlayers[$i * 2]->id,
                'player2_id' => $doublePlayers[$i * 2 + 1]->id,
                'registered_by' => $doublePlayers[$i * 2]->id,
            ]);
        }

        $this->generatePools($t, 2);
    }

    private function seedSetupHandicap(Collection $players): void
    {
        $t = $this->makeTournament([
            'name' => '[SETUP] Simple — handicap AFTT',
            'nb_pools' => 4,
            'has_handicap_points' => true,
            'price' => 10,
        ]);
        $this->setupInfra($t);
        $this->attachPlayers($t, $players->slice(0, 16));
        $this->generatePools($t, 4);
    }

    private function seedSetupNoHandicap(Collection $players): void
    {
        $t = $this->makeTournament([
            'name' => '[SETUP] Simple — sans handicap',
            'nb_pools' => 3,
        ]);
        $this->setupInfra($t);
        $this->attachPlayers($t, $players->slice(0, 12));
        $this->generatePools($t, 3);
    }

    private function seedSetupSetSecBestOf7(Collection $players): void
    {
        $t = $this->makeTournament([
            'name' => '[SETUP] Simple — set sec + best-of-7',
            'nb_pools' => 2,
            'sets_to_win' => 4,
            'deuce_enabled' => false,
        ]);
        $this->setupInfra($t);
        $this->attachPlayers($t, $players->slice(0, 8));
        $this->generatePools($t, 2);
    }

    private function setupInfra(Tournament $t): void
    {
        $t->rooms()->sync([1, 2]);
        $this->tableService->linkAvailableTables($t);
    }

    private function simulateMatch(int $setsToWin = 3): array
    {
        $sets = [];
        $p1Wins = 0;
        $p2Wins = 0;

        while ($p1Wins < $setsToWin && $p2Wins < $setsToWin) {
            $winnerScore = max(fake()->numberBetween(0, 15), 11);
            $loserScore = $winnerScore > 11
                ? $winnerScore - 2
                : fake()->numberBetween(0, 9);

            if (rand(0, 1)) {
                $sets[] = ['player1_score' => $winnerScore, 'player2_score' => $loserScore];
                $p1Wins++;
            } else {
                $sets[] = ['player1_score' => $loserScore, 'player2_score' => $winnerScore];
                $p2Wins++;
            }
        }

        return $sets;
    }
}
