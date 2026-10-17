<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Services;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentFinalPhaseService
{
    public function __construct(
        private readonly TournamentMatchService $tournamentMatchService,
        private readonly TournamentPoolService $tournamentPoolService,
    ) {}

    /**
     * After a bracket match completes, assign referees to ALL bracket matches
     * that are now ready (both players set, no referee yet). Handles the case
     * where final and bronze become ready simultaneously after the second semi.
     */
    public function assignBracketReferee(TournamentMatch $completedMatch): void
    {
        $unassigned = TournamentMatch::where('tournament_id', $completedMatch->tournament_id)
            ->whereNotNull('round')
            ->whereNotIn('round', ['final', 'bronze'])
            ->where('status', 'scheduled')
            ->whereNull('referee_id')
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->orderBy('match_order')
            ->with(['pair1', 'pair2'])
            ->get();

        foreach ($unassigned as $target) {
            $this->tryAssignBracketReferee($completedMatch->tournament_id, $target);
        }
    }

    /**
     * Assign referees to the initial bracket matches (first round) using pool
     * non-qualifiers as candidates. Distributes assignments evenly.
     */
    public function assignInitialBracketReferees(Tournament $tournament): void
    {
        // All players who participated in pool matches
        $poolPlayerIds = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('pool_id')
            ->with(['pair1', 'pair2'])
            ->get()
            ->flatMap(fn (TournamentMatch $m): array => $m->sidePlayerIds(1)->merge($m->sidePlayerIds(2))->all())
            ->filter()
            ->unique()
            ->values();

        // Players who qualified (present in bracket matches)
        $qualifiedIds = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('round')
            ->whereNotNull('player1_id')
            ->with(['pair1', 'pair2'])
            ->get()
            ->flatMap(fn (TournamentMatch $m): array => $m->sidePlayerIds(1)->merge($m->sidePlayerIds(2))->all())
            ->filter()
            ->unique()
            ->values();

        // Non-qualifiers are the referee candidates
        $candidates = $poolPlayerIds->diff($qualifiedIds)->values();

        if ($candidates->isEmpty()) {
            return;
        }

        $bracketMatches = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('round')
            ->whereNotIn('round', ['final', 'bronze'])
            ->where('status', 'scheduled')
            ->whereNull('referee_id')
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->orderBy('match_order')
            ->with(['pair1', 'pair2'])
            ->get();

        if ($bracketMatches->isEmpty()) {
            return;
        }

        // Track local assignment counts so we distribute evenly within this batch
        $refereeCounts = array_fill_keys($candidates->toArray(), 0);

        foreach ($bracketMatches as $match) {
            $playingIds = $match->sidePlayerIds(1)->merge($match->sidePlayerIds(2));
            $eligible = $candidates->diff($playingIds)->values();

            if ($eligible->isEmpty()) {
                continue;
            }

            $ranked = $eligible
                ->map(fn (int $id): array => ['id' => $id, 'count' => $refereeCounts[$id] ?? 0])
                ->sortBy('count');

            $minCount = $ranked->first()['count'];
            $pool = $ranked->filter(fn (array $c): bool => $c['count'] === $minCount)->values()->all();
            $chosen = $pool[random_int(0, count($pool) - 1)]['id'];
            $match->update(['referee_id' => $chosen]);
            $refereeCounts[$chosen]++;
        }
    }

    public function checkIfAllPoolsAreFinished(Tournament $tournament): bool
    {
        $result = true;
        foreach ($tournament->pools as $pool) {
            if ($this->tournamentPoolService->isPoolFinished($pool) === false) {
                $result = false;
            }
        }

        return $result;
    }

    public function checkIfAllPoolsContainsMatches(Tournament $tournament): bool
    {
        $result = true;
        foreach ($tournament->pools as $pool) {
            if ($pool->tournamentMatches()->count() === 0) {
                $result = false;
            }
        }

        return $result;
    }

    public function cleanNextMatch(TournamentMatch $match): void
    {
        // If there's a next match with one of our players, reset it
        if ($match->next_match_id) {
            $nextMatch = TournamentMatch::find($match->next_match_id);

            if ($nextMatch) {
                if ($nextMatch->player1_id === $match->player1_id || $nextMatch->player1_id === $match->player2_id) {
                    $nextMatch->update(['player1_id' => null]);
                }

                if ($nextMatch->player2_id === $match->player1_id || $nextMatch->player2_id === $match->player2_id) {
                    $nextMatch->update(['player2_id' => null]);
                }
            }
        }
    }

    /**
     * Update match with winner and progress to next round
     */
    public function completeMatch(TournamentMatch $match, int $winnerId): bool
    {
        $match->update([
            'winner_id' => $winnerId,
            'status' => 'completed',
        ]);

        // If there's a next match with one of our players, reset it
        $this->cleanNextMatch($match);

        // Propagate winner (and their pair for doubles) to the next bracket match
        if ($match->next_match_id) {
            $nextMatch = TournamentMatch::find($match->next_match_id);
            if ($nextMatch) {
                $slot = $nextMatch->player1_id ? 2 : 1;
                $winnerPairId = $match->player1_id === $winnerId ? $match->pair1_id : $match->pair2_id;

                $update = ["player{$slot}_id" => $winnerId];
                if ($winnerPairId) {
                    $update["pair{$slot}_id"] = $winnerPairId;
                }
                $nextMatch->update($update);
            }
        }

        // If this is a semifinal, propagate loser (and their pair) to the bronze match
        if ($match->round === 'semifinal' && $match->bronze_match_id) {
            $bronzeMatch = TournamentMatch::find($match->bronze_match_id);
            $loserId = $match->player1_id === $winnerId ? $match->player2_id : $match->player1_id;
            $loserPairId = $match->player1_id === $winnerId ? $match->pair2_id : $match->pair1_id;

            if ($bronzeMatch) {
                $slot = $bronzeMatch->player1_id !== null ? 2 : 1;

                $update = ["player{$slot}_id" => $loserId];
                if ($loserPairId) {
                    $update["pair{$slot}_id"] = $loserPairId;
                }
                $bronzeMatch->update($update);
            }
        }

        // Un match qui vient de recevoir son second joueur part avec son
        // handicap, comme en poule.
        foreach (array_filter([$match->next_match_id, $match->round === 'semifinal' ? $match->bronze_match_id : null]) as $readyId) {
            $this->applyHandicap(TournamentMatch::find($readyId));
        }

        // Assign referee AFTER next-match players are set, so the query can
        // exclude candidates who are playing in that now-complete match slot.
        $this->assignBracketReferee($match);

        return true;
    }

    /**
     * Configure knockout phase
     *
     * @throws Exception
     */
    public function configureKnockoutPhase(Tournament $tournament, string $startingRound): bool
    {
        if ($tournament->pools->isEmpty()) {
            throw new Exception(__('Pool found without match.'));
        }

        if ($this->checkIfAllPoolsContainsMatches($tournament) === false) {
            throw new Exception(__('Pool found without match.'));
        }

        if ($this->checkIfAllPoolsAreFinished($tournament) === false) {
            throw new Exception(__('At least one pool is still open. Please encode all matches first'));
        }

        // Delete existing knockout matches if any
        TournamentMatch::where('tournament_id', $tournament->id)->fromBracket()->delete();

        // Get qualified players based on pool standings
        $qualifiedPlayers = $this->getQualifiedPlayers($tournament, $startingRound);

        // Create bracket structure
        $result = $this->createBracket($tournament, $qualifiedPlayers, $startingRound);

        foreach (TournamentMatch::where('tournament_id', $tournament->id)->fromBracket()->get() as $bracketMatch) {
            $this->applyHandicap($bracketMatch);
        }

        // Assign referees to the first-round bracket matches using pool non-qualifiers
        $this->assignInitialBracketReferees($tournament);

        return $result;
    }

    /**
     * Correct the score of a bracket match already played.
     *
     * Allowed while what it fed — the next match, and for a semi-final the
     * match for third place — has not started: then a new winner replaces the
     * old one in the next match, a new loser the old one in the match for
     * third place, and their starting points follow.
     *
     * @param  array<int, array{player1_score: int, player2_score: int}>  $setResults
     *
     * @throws \LogicException once a match it fed has started
     */
    public function correctMatch(TournamentMatch $match, array $setResults): void
    {
        $fed = collect([$match->next_match_id, $match->round === 'semifinal' ? $match->bronze_match_id : null])
            ->filter()
            ->map(fn (int $id): ?TournamentMatch => TournamentMatch::find($id))
            ->filter();

        if ($fed->contains(fn (TournamentMatch $next): bool => $next->status !== 'scheduled')) {
            throw new \LogicException('A match this one fed has already started.');
        }

        $oldWinner = $match->winner_id;
        $oldLoser = $oldWinner === $match->player1_id ? $match->player2_id : $match->player1_id;

        DB::transaction(function () use ($match, $setResults, $oldWinner, $oldLoser): void {
            $match->recordResult($setResults);
            $match->update(['is_forfeit' => false]);

            if ($match->winner_id === $oldWinner) {
                return;
            }

            // The winner changed: the two sides swap places downstream.
            $newWinnerPair = $match->winner_id === $match->player1_id ? $match->pair1_id : $match->pair2_id;
            $newLoserPair = $match->winner_id === $match->player1_id ? $match->pair2_id : $match->pair1_id;

            if ($match->next_match_id !== null) {
                $this->replaceEntrant(TournamentMatch::find($match->next_match_id), $oldWinner, $match->winner_id, $newWinnerPair);
            }

            if ($match->round === 'semifinal' && $match->bronze_match_id !== null) {
                $this->replaceEntrant(TournamentMatch::find($match->bronze_match_id), $oldLoser, $oldWinner, $newLoserPair);
            }
        });
    }

    /**
     * Create the knockout bracket
     */
    public function createBracket(Tournament $tournament, Collection $qualifiedPlayers, string $startingRound): bool
    {
        $totalPlayers = $this->getRoundPlayerCount($startingRound);

        // Seed players in the bracket (1st from pool A vs 2nd from pool B, etc.)
        $seededPlayers = $this->seedPlayers($qualifiedPlayers);

        // Create rounds starting from final and working backwards
        $finalMatch = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'final',
            'match_order' => 1,
            'status' => 'scheduled',
        ]);

        $bronzeMatch = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'bronze',
            'match_order' => 1,
            'status' => 'scheduled',
            'is_bronze_match' => true,
        ]);

        $semifinalMatches = $this->createRoundMatches($tournament, 'semifinal', 2, [$finalMatch->id], $bronzeMatch->id);

        if ($startingRound === 'round_4' || $totalPlayers <= 4) {
            for ($i = 0; $i < min(count($seededPlayers), 4); $i++) {
                $semifinalMatches[intdiv($i, 2)]->update($this->buildMatchAssignment($i, $seededPlayers[$i]));
            }

            return true;
        }

        $quarterMatches = $this->createRoundMatches($tournament, 'quarterfinal', 4, $semifinalMatches->pluck('id')->toArray());

        if ($startingRound === 'round_8' || $totalPlayers <= 8) {
            for ($i = 0; $i < min(count($seededPlayers), 8); $i++) {
                $quarterMatches[intdiv($i, 2)]->update($this->buildMatchAssignment($i, $seededPlayers[$i]));
            }

            return true;
        }

        if ($startingRound === 'round_16') {
            $round16Matches = $this->createRoundMatches($tournament, 'round_16', 8, $quarterMatches->pluck('id')->toArray());

            for ($i = 0; $i < min(count($seededPlayers), 16); $i++) {
                $round16Matches[intdiv($i, 2)]->update($this->buildMatchAssignment($i, $seededPlayers[$i]));
            }
        }

        return true;
    }

    /**
     * Get all knockout matches organized by rounds
     */
    public function getKnockoutMatches(Tournament $tournament): array
    {
        // Get all matches
        $matches = TournamentMatch::where('tournament_id', $tournament->id)
            ->fromBracket()
            ->with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'sets', 'winner', 'referee'])
            ->orderBy('match_order')
            ->get();

        // Define the order of rounds
        $roundOrder = [
            'round_16' => 1,
            'quarterfinal' => 2,
            'semifinal' => 3,
            'final' => 4,
            'bronze' => 5,
        ];

        // Sort the collection using the defined order
        $sortedMatches = $matches->sort(function (TournamentMatch $a, TournamentMatch $b) use ($roundOrder): int {
            // First sort by round
            if ($roundOrder[$a->round] !== $roundOrder[$b->round]) {
                return $roundOrder[$a->round] <=> $roundOrder[$b->round];
            }

            // If rounds are the same, sort by match_order
            return $a->match_order <=> $b->match_order;
        });

        // Group the sorted matches by round
        $rounds = [
            'round_16' => $sortedMatches->where('round', 'round_16')->values(),
            'quarterfinal' => $sortedMatches->where('round', 'quarterfinal')->values(),
            'semifinal' => $sortedMatches->where('round', 'semifinal')->values(),
            'final' => $sortedMatches->where('round', 'final')->values(),
            'bronze' => $sortedMatches->where('round', 'bronze')->values(),
        ];

        return $rounds;
    }

    /**
     * Who goes to the bracket.
     *
     * The first `nb_qualifiers_per_pool` of every pool go straight in. The
     * places left in the bracket go to the best of the rank just below — never
     * further down — compared on averages: share of matches won, then of sets,
     * then of points, then a draw that stays the same. Raw totals would favour
     * whoever played in the biggest pool.
     */
    public function getQualifiedPlayers(Tournament $tournament, string $startingRound): Collection
    {
        $bracketSize = $this->getRoundPlayerCount($startingRound);
        $pools = $tournament->pools;
        $direct = max(1, $tournament->nb_qualifiers_per_pool);
        $qualifiedPlayers = collect();
        $candidates = collect();

        foreach ($pools as $pool) {
            $standings = $this->tournamentMatchService->calculatePoolStandings($pool)->values();

            for ($i = 0; $i < $direct && $i < $standings->count(); $i++) {
                $qualifiedPlayers->push($this->buildQualifiedEntry($standings[$i], $pool, $i + 1));
            }

            if (isset($standings[$direct]) && ! $standings[$direct]['no_show']) {
                $candidates->push($this->buildQualifiedEntry($standings[$direct], $pool, $direct + 1));
            }
        }

        $spotsLeft = max(0, $bracketSize - $qualifiedPlayers->count());

        $repechage = $candidates
            ->sort(function (array $a, array $b) use ($tournament): int {
                return $this->repechageKey($b, $tournament) <=> $this->repechageKey($a, $tournament);
            })
            ->take($spotsLeft);

        return $qualifiedPlayers->concat($repechage)->take($bracketSize)->values();
    }

    /**
     * Create matches for a specific round
     */
    protected function createRoundMatches(Tournament $tournament, string $round, int $matchCount, ?array $nextMatchIds, ?int $bronzeMatchId = null): Collection
    {
        $matches = collect();

        for ($i = 0; $i < $matchCount; $i++) {
            $nextMatchId = null;
            if (is_array($nextMatchIds)) {
                $nextMatchIndex = intdiv($i, 2);
                if (isset($nextMatchIds[$nextMatchIndex])) {
                    $nextMatchId = $nextMatchIds[$nextMatchIndex];
                }
            } else {
                $nextMatchId = $nextMatchIds;
            }

            $matchData = [
                'tournament_id' => $tournament->id,
                'round' => $round,
                'match_order' => $i + 1,
                'status' => 'scheduled',
                'next_match_id' => $nextMatchId,
            ];

            // Pour les demi-finales, ajouter la référence au match bronze directement
            if ($round === 'semifinal' && $bronzeMatchId) {
                $matchData['bronze_match_id'] = $bronzeMatchId;
            }

            $match = TournamentMatch::create($matchData);
            $matches->push($match);
        }

        return $matches;
    }

    /**
     * Get number of players needed for a specific round
     */
    protected function getRoundPlayerCount(string $round): int
    {
        return match ($round) {
            'round_8' => 8,
            'round_4' => 4,
            default => 16,
        };
    }

    /**
     * Seed players so that rank-N players face rank-(N+1) players from a different pool.
     * Players from the same pool are placed in opposite bracket halves.
     *
     * For 4 pools with 2 qualifiers each the result is:
     *   [A#1, C#2, B#1, D#2, C#1, A#2, D#1, B#2]
     * giving QF pairs: A#1-C#2, B#1-D#2, C#1-A#2, D#1-B#2.
     */
    protected function seedPlayers(Collection $qualifiedPlayers): Collection
    {
        $seededPlayers = collect();
        $poolGroups = $qualifiedPlayers->groupBy('pool.id');

        if ($poolGroups->count() < 2) {
            return $this->seedSinglePool($qualifiedPlayers->sortBy('position')->values());
        }

        $orderedPoolIds = $poolGroups->keys()->sort()->values();
        $maxPosition = $qualifiedPlayers->max('position');

        // Build one ordered group per rank (pool order is consistent within each rank)
        $rankGroups = collect();
        for ($rank = 1; $rank <= $maxPosition; $rank++) {
            $group = collect();
            foreach ($orderedPoolIds as $poolId) {
                foreach ($poolGroups[$poolId] as $player) {
                    if ($player['position'] === $rank) {
                        $group->push($player);
                        break;
                    }
                }
            }
            if ($group->isNotEmpty()) {
                $rankGroups->push($group);
            }
        }

        // Cross-seed consecutive rank pairs: rank1 vs rank2, rank3 vs rank4, …
        // Shift the lower-rank group by half its size so rank1[i] faces rank2[i + N/2].
        for ($pairIdx = 0; $pairIdx + 1 < $rankGroups->count(); $pairIdx += 2) {
            $higher = $rankGroups[$pairIdx];
            $lower = $rankGroups[$pairIdx + 1];
            $count = max($higher->count(), $lower->count());
            $offset = intdiv($count, 2);

            for ($i = 0; $i < $count; $i++) {
                if ($i < $higher->count()) {
                    $seededPlayers->push($higher[$i]);
                }
                $lowerIdx = ($i + $offset) % $count;
                if ($lowerIdx < $lower->count()) {
                    $seededPlayers->push($lower[$lowerIdx]);
                }
            }
        }

        // If odd number of rank groups, append the last unpaired group as-is
        if ($rankGroups->count() % 2 === 1) {
            foreach ($rankGroups->last() as $player) {
                $seededPlayers->push($player);
            }
        }

        // Append any repêchage or overflow players not yet placed
        foreach ($qualifiedPlayers as $player) {
            if (! $seededPlayers->contains(fn (array $v): bool => $v['player']->id === $player['player']->id)) {
                $seededPlayers->push($player);
            }
        }

        return $seededPlayers;
    }

    /**
     * Give a bracket match the starting points its two sides call for.
     *
     * The bracket was built at 0-0: a tournament played with handicap lost it
     * from the quarter-finals on, without a word.
     */
    private function applyHandicap(?TournamentMatch $match): void
    {
        if (! $match instanceof TournamentMatch || $match->status === 'completed') {
            return;
        }

        $handicap = $this->tournamentMatchService->handicapFor($match->fresh());

        $match->update([
            'player1_handicap_points' => $handicap['player1'],
            'player2_handicap_points' => $handicap['player2'],
        ]);
    }

    /** @param  array<string, mixed>  $seededPlayer */
    private function buildMatchAssignment(int $i, array $seededPlayer): array
    {
        $playerField = $i % 2 === 0 ? 'player1_id' : 'player2_id';
        $data = [$playerField => $seededPlayer['player']->id];

        if (isset($seededPlayer['pair'])) {
            $pairField = $i % 2 === 0 ? 'pair1_id' : 'pair2_id';
            $data[$pairField] = $seededPlayer['pair']->id;
        }

        return $data;
    }

    /** @param  array<string, mixed>  $standing */
    private function buildQualifiedEntry(array $standing, mixed $pool, int $position): array
    {
        $entry = [
            'player' => $standing['player'],
            'pool' => $pool,
            'position' => $position,
            'stats' => $standing,
        ];

        if (isset($standing['pair'])) {
            $entry['pair'] = $standing['pair'];
        }

        return $entry;
    }

    /**
     * The averages a repêchage compares, highest first; the draw last, reversed
     * so that it sorts the same way as the rest.
     *
     * @param  array<string, mixed>  $entry
     * @return list<float|int>
     */
    private function repechageKey(array $entry, Tournament $tournament): array
    {
        $stats = $entry['stats'];

        return [
            $stats['matches_played'] > 0 ? $stats['matches_won'] / $stats['matches_played'] : 0.0,
            TournamentMatchService::ratio($stats['sets_won'], $stats['sets_lost']),
            TournamentMatchService::ratio($stats['total_points'], $stats['points_lost']),
            -TournamentMatchService::stableDraw($tournament->id, $entry['player']->id),
        ];
    }

    /**
     * In a match fed by a corrected one, put the new entrant where the old one stood.
     */
    private function replaceEntrant(?TournamentMatch $target, ?int $oldId, ?int $newId, ?int $newPairId): void
    {
        if (! $target instanceof TournamentMatch || $oldId === null) {
            return;
        }

        foreach ([1, 2] as $slot) {
            if ($target->{"player{$slot}_id"} === $oldId) {
                $target->update(array_filter([
                    "player{$slot}_id" => $newId,
                    "pair{$slot}_id" => $newPairId,
                ], fn (?int $value): bool => $value !== null));
            }
        }

        $this->applyHandicap($target->fresh());
    }

    /**
     * One pool feeding the bracket: the usual draw, so the first meets the last
     * and the two best can only meet in the final.
     *
     * Kept in ranking order, the first and the second met in the first round.
     */
    private function seedSinglePool(Collection $ranked): Collection
    {
        $slots = [1, 2];

        while (count($slots) < $ranked->count()) {
            $size = count($slots) * 2;
            $slots = collect($slots)->flatMap(fn (int $seed): array => [$seed, $size + 1 - $seed])->all();
        }

        return collect($slots)
            ->map(fn (int $seed): ?array => $ranked->get($seed - 1))
            ->filter()
            ->values();
    }

    private function tryAssignBracketReferee(int $tournamentId, TournamentMatch $target): void
    {
        // All players who have lost a bracket match in this tournament
        $loserIds = TournamentMatch::where('tournament_id', $tournamentId)
            ->whereNotNull('round')
            ->whereNotNull('winner_id')
            ->with(['pair1', 'pair2'])
            ->get()
            ->flatMap(fn (TournamentMatch $m): array => $m->sidePlayerIds($m->player1_id === $m->winner_id ? 2 : 1)->all())
            ->filter()
            ->unique()
            ->values();

        if ($loserIds->isEmpty()) {
            return;
        }

        $playingIds = $target->sidePlayerIds(1)->merge($target->sidePlayerIds(2));
        $candidates = $loserIds->diff($playingIds)->values();

        if ($candidates->isEmpty()) {
            return;
        }

        $refereeCounts = TournamentMatch::where('tournament_id', $tournamentId)
            ->whereIn('referee_id', $candidates)
            ->selectRaw('referee_id, COUNT(*) as total')
            ->groupBy('referee_id')
            ->pluck('total', 'referee_id')
            ->toArray();

        $ranked = $candidates
            ->map(fn (int $id): array => ['id' => $id, 'count' => $refereeCounts[$id] ?? 0])
            ->sortBy('count');

        $minCount = $ranked->first()['count'];
        $pool = $ranked->filter(fn (array $c): bool => $c['count'] === $minCount)->values()->all();
        $target->update(['referee_id' => $pool[random_int(0, count($pool) - 1)]['id']]);
    }
}
