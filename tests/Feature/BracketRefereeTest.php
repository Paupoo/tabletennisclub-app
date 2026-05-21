<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Services\TournamentFinalPhaseService;

function makeBracketTournament(): Tournament
{
    return Tournament::factory()->create([
        'status' => TournamentStatusEnum::PENDING,
        'sets_to_win' => 3,
        'has_handicap_points' => false,
        'deuce_enabled' => false,
    ]);
}

function makeBracketMatch(Tournament $t, User $winner, User $loser, string $round = 'quarterfinal', int $order = 1): TournamentMatch
{
    return TournamentMatch::create([
        'tournament_id' => $t->id,
        'round' => $round,
        'player1_id' => $winner->id,
        'player2_id' => $loser->id,
        'winner_id' => $winner->id,
        'status' => 'completed',
        'match_order' => $order,
    ]);
}

function makeScheduledBracketMatch(Tournament $t, User $p1, User $p2, string $round = 'semifinal', int $order = 10): TournamentMatch
{
    return TournamentMatch::create([
        'tournament_id' => $t->id,
        'round' => $round,
        'player1_id' => $p1->id,
        'player2_id' => $p2->id,
        'status' => 'scheduled',
        'match_order' => $order,
    ]);
}

describe('assignBracketReferee', function () {

    it('assigns the loser of the completed match as referee of the next scheduled match', function () {
        $winner = User::factory()->create();
        $loser = User::factory()->create();
        $p3 = User::factory()->create();
        $p4 = User::factory()->create();

        $t = makeBracketTournament();
        $completed = makeBracketMatch($t, $winner, $loser);
        $next = makeScheduledBracketMatch($t, $p3, $p4);

        app(TournamentFinalPhaseService::class)->assignBracketReferee($completed);

        expect($next->fresh()->referee_id)->toBe($loser->id);
    });

    it('picks another loser when the current loser plays in the next match', function () {
        $w1 = User::factory()->create();
        $loser = User::factory()->create();
        $w2 = User::factory()->create();
        $otherLoser = User::factory()->create();

        $t = makeBracketTournament();
        makeBracketMatch($t, $w1, $loser, 'quarterfinal', 1);
        makeBracketMatch($t, $w2, $otherLoser, 'quarterfinal', 2);

        // Next match: loser IS one of the players
        $next = makeScheduledBracketMatch($t, $loser, $w2, 'semifinal', 10);
        $completed = TournamentMatch::where('tournament_id', $t->id)->where('player2_id', $loser->id)->first();

        app(TournamentFinalPhaseService::class)->assignBracketReferee($completed);

        expect($next->fresh()->referee_id)->toBe($otherLoser->id);
    });

    it('picks the candidate with fewest existing referee assignments', function () {
        $w1 = User::factory()->create();
        $loser1 = User::factory()->create(); // will have 1 prior referee duty
        $w2 = User::factory()->create();
        $loser2 = User::factory()->create(); // 0 referee duties
        $p5 = User::factory()->create();
        $p6 = User::factory()->create();

        $t = makeBracketTournament();
        makeBracketMatch($t, $w1, $loser1, 'quarterfinal', 1);
        makeBracketMatch($t, $w2, $loser2, 'quarterfinal', 2);

        // loser1 already refereed a match
        TournamentMatch::create([
            'tournament_id' => $t->id,
            'round' => 'quarterfinal',
            'player1_id' => $p5->id,
            'player2_id' => $p6->id,
            'referee_id' => $loser1->id,
            'status' => 'completed',
            'match_order' => 3,
        ]);

        $next = makeScheduledBracketMatch($t, $p5, $p6, 'semifinal', 10);
        $completed = TournamentMatch::where('tournament_id', $t->id)->where('player2_id', $loser2->id)->first();

        app(TournamentFinalPhaseService::class)->assignBracketReferee($completed);

        // loser2 has 0 duties vs loser1's 1 → loser2 wins
        expect($next->fresh()->referee_id)->toBe($loser2->id);
    });

    it('does nothing when no next scheduled match exists', function () {
        $winner = User::factory()->create();
        $loser = User::factory()->create();

        $t = makeBracketTournament();
        $completed = makeBracketMatch($t, $winner, $loser);

        expect(fn () => app(TournamentFinalPhaseService::class)->assignBracketReferee($completed))
            ->not->toThrow(Throwable::class);
    });

    it('does nothing when every loser candidate is playing in the next match', function () {
        $w1 = User::factory()->create();
        $loser = User::factory()->create();

        $t = makeBracketTournament();
        makeBracketMatch($t, $w1, $loser, 'quarterfinal', 1);

        $next = makeScheduledBracketMatch($t, $loser, $w1, 'semifinal', 10);

        $completed = TournamentMatch::where('tournament_id', $t->id)->where('player2_id', $loser->id)->first();
        app(TournamentFinalPhaseService::class)->assignBracketReferee($completed);

        expect($next->fresh()->referee_id)->toBeNull();
    });

})->group('bracket', 'referee');
