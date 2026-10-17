<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Shared\Enums\Ranking;
use App\Jobs\SendTournamentPairNoticeJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * How the committee composes the pairs of a doubles tournament.
 *
 * A pair always holds two players: whoever is registered and in no pair sits
 * on the bench, and every move keeps that true.
 *
 * Once the pairs are published, every change tells the players whose partner
 * it changed — and only them.
 */
class TournamentPairingService
{
    /**
     * Registered players who are in no pair yet.
     *
     * @return Collection<int, User>
     */
    public function bench(Tournament $tournament): Collection
    {
        $pairedIds = $tournament->pairs()
            ->get(['player1_id', 'player2_id'])
            ->flatMap(fn (TournamentPair $pair): array => [$pair->player1_id, $pair->player2_id]);

        return $tournament->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed'])
            ->whereNotIn('users.id', $pairedIds)
            ->get();
    }

    /**
     * What the committee sees on the board: the pairs, then the bench.
     *
     * The age is the one each player has on the tournament day.
     *
     * @return array{pairs: list<array{id: int, players: list<array{id: int, name: string, ranking: string, age: int|null}>}>, bench: list<array{id: int, name: string, ranking: string, age: int|null}>}
     */
    public function board(Tournament $tournament): array
    {
        $describe = fn (User $player): array => [
            'id' => $player->id,
            'name' => $player->full_name,
            'ranking' => $player->ranking->getLabel(),
            'age' => $player->birthdate === null
                ? null
                : (int) Carbon::parse($player->birthdate)->diffInYears($tournament->start_date ?? now()),
        ];

        $pairs = $tournament->pairs()->with(['player1', 'player2'])->orderBy('id')->get()
            ->map(fn (TournamentPair $pair): array => [
                'id' => $pair->id,
                'players' => [$describe($pair->player1), $describe($pair->player2)],
            ])
            ->all();

        $bench = $this->strongestFirst($this->bench($tournament))->map($describe)->all();

        return ['pairs' => $pairs, 'bench' => $bench];
    }

    /**
     * Drop a player somewhere on the board.
     *
     * On a player of another pair, the two swap places. On a player of the
     * bench, the two form a new pair and the mover's partner, if any, goes to
     * the bench. On the bench itself (`$target` null), the mover's pair breaks.
     */
    public function move(Tournament $tournament, User $player, ?User $target, User $by): void
    {
        $this->ensureStillOpen($tournament);
        $before = $this->partners($tournament);

        $this->rearrange($tournament, $player, $target, $by);

        $this->settle($tournament, $before);
    }

    /**
     * Pair the bench, strongest with weakest; pairs already formed stay as they are.
     */
    public function propose(Tournament $tournament, User $by): void
    {
        $this->ensureStillOpen($tournament);
        $before = $this->partners($tournament);

        $players = $this->strongestFirst($this->bench($tournament));

        while ($players->count() >= 2) {
            $this->pair($tournament, $players->shift(), $players->pop(), $by);
        }

        $this->settle($tournament, $before);
    }

    /**
     * Show the pairs to the players: each paired player is told who they play with.
     */
    public function publish(Tournament $tournament): void
    {
        $tournament->update(['pairs_published_at' => now()]);

        foreach (array_keys($this->partners($tournament)) as $playerId) {
            SendTournamentPairNoticeJob::dispatch($tournament->id, $playerId);
        }
    }

    /**
     * A player leaves the tournament: their pair breaks and the partner goes to the bench.
     *
     * Before the launch a drawn pool would keep a pair that no longer exists,
     * so the draw goes with it. Once launched the pair stays where it is — the
     * day itself settles it, with a walkover.
     *
     * @return User|null The partner sent back to the bench, if any.
     */
    public function release(Tournament $tournament, User $player): ?User
    {
        $pair = $this->pairOf($tournament, $player);

        if (! $pair instanceof TournamentPair || $tournament->state()->hasBeenLaunched()) {
            return null;
        }

        $this->eraseDraw($tournament);

        $before = $this->partners($tournament);
        $partner = $pair->partnerOf($player);
        $pair->delete();

        // Celui qui se désiste a déjà son mail d'annulation : seul le partenaire
        // apprend qu'il est seul.
        $this->noticeChanges($tournament, $before, except: $player);

        return $partner;
    }

    /**
     * Break every pair: the whole field goes back to the bench.
     */
    public function resetAll(Tournament $tournament): void
    {
        $this->ensureStillOpen($tournament);
        $before = $this->partners($tournament);

        $tournament->pairs()->delete();

        $this->settle($tournament, $before);
    }

    /**
     * Once the tournament is launched, the pairs are on the tables.
     *
     * Drawn pools do not freeze them: a change before the launch erases the
     * draw, and the pools are drawn again — which needs an empty bench.
     */
    private function ensureStillOpen(Tournament $tournament): void
    {
        if ($tournament->state()->hasBeenLaunched()) {
            throw new LogicException('The tournament is launched: pairs can no longer change.');
        }
    }

    /**
     * Pools drawn on pairs that are about to change are wrong: they go, with
     * their matches, and the committee draws again once the pairs are set.
     *
     * Kept in the database rather than flagged on the screen, so that no reload
     * can bring back a launch on matches whose pair is gone.
     */
    private function eraseDraw(Tournament $tournament): void
    {
        $tournament->pools()->delete();
    }

    /**
     * Once the pairs are published, tell each player whose partner changed.
     *
     * @param  array<int, int>  $before
     */
    private function noticeChanges(Tournament $tournament, array $before, ?User $except = null): void
    {
        if ($tournament->pairs_published_at === null) {
            return;
        }

        $after = $this->partners($tournament);

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $playerId) {
            if (($before[$playerId] ?? null) !== ($after[$playerId] ?? null) && $playerId !== $except?->id) {
                SendTournamentPairNoticeJob::dispatch($tournament->id, $playerId);
            }
        }
    }

    private function pair(Tournament $tournament, User $first, User $second, User $by): TournamentPair
    {
        return $tournament->pairs()->create([
            'player1_id' => $first->id,
            'player2_id' => $second->id,
            'registered_by' => $by->id,
        ]);
    }

    private function pairOf(Tournament $tournament, User $player): ?TournamentPair
    {
        return $tournament->pairs()
            ->where(fn ($query) => $query->where('player1_id', $player->id)->orWhere('player2_id', $player->id))
            ->first();
    }

    /**
     * Who plays with whom, by player id.
     *
     * @return array<int, int>
     */
    private function partners(Tournament $tournament): array
    {
        $partners = [];

        foreach ($tournament->pairs()->get(['player1_id', 'player2_id']) as $pair) {
            $partners[$pair->player1_id] = $pair->player2_id;
            $partners[$pair->player2_id] = $pair->player1_id;
        }

        return $partners;
    }

    /**
     * The lower, the stronger: {@see Ranking::cases()} runs from B0 down to NC.
     */
    private function rankingWeight(User $player): int
    {
        return (int) array_search($player->ranking, Ranking::cases(), true);
    }

    private function rearrange(Tournament $tournament, User $player, ?User $target, User $by): void
    {
        $playerPair = $this->pairOf($tournament, $player);

        if (! $target instanceof User) {
            $playerPair?->delete();

            return;
        }

        $partner = $playerPair?->partnerOf($player);

        if ($target->is($player) || $target->is($partner)) {
            return;
        }

        $targetPair = $this->pairOf($tournament, $target);

        DB::transaction(function () use ($tournament, $player, $target, $by, $playerPair, $partner, $targetPair): void {
            $playerPair?->delete();
            $targetPair?->delete();

            if ($targetPair instanceof TournamentPair) {
                $this->pair($tournament, $player, $targetPair->partnerOf($target), $by);

                if ($partner instanceof User) {
                    $this->pair($tournament, $target, $partner, $by);
                }

                return;
            }

            $this->pair($tournament, $player, $target, $by);
        });
    }

    /**
     * After a change on the board: erase a draw it made wrong, and tell the
     * players whose partner changed.
     *
     * @param  array<int, int>  $before
     */
    private function settle(Tournament $tournament, array $before): void
    {
        $after = $this->partners($tournament);
        ksort($after);
        ksort($before);

        if ($after !== $before) {
            $this->eraseDraw($tournament);
        }

        $this->noticeChanges($tournament, $before);
    }

    /**
     * By ranking, then from eldest to youngest within a ranking.
     *
     * A player whose birthdate is unknown goes to the middle of their ranking,
     * so a missing date never makes them the eldest or the youngest.
     *
     * @param  Collection<int, User>  $players
     * @return Collection<int, User>
     */
    private function strongestFirst(Collection $players): Collection
    {
        return $players
            ->groupBy(fn (User $player): int => $this->rankingWeight($player))
            ->sortKeys()
            ->flatMap(function (Collection $sameRanking): array {
                [$dated, $undated] = $sameRanking->partition(fn (User $player): bool => $player->birthdate !== null);
                $eldestFirst = $dated->sortBy(fn (User $player): string => (string) $player->birthdate)->values()->all();

                array_splice($eldestFirst, intdiv(count($eldestFirst), 2), 0, $undated->all());

                return $eldestFirst;
            })
            ->values();
    }
}
