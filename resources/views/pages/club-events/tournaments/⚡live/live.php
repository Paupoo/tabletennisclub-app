<?php

declare(strict_types=1);

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\ReadsTournamentLiveState;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Le tournoi vu par un joueur, pas par le comité.
 *
 * La régie répond à « quel match lancer sur cette table ». Celle-ci répond à
 * « quand est-ce que je joue, et où » — la même journée par l'autre bout. Un
 * joueur qui ouvrait la régie n'y avait pas accès, et l'aurait de toute façon
 * trouvée organisée autour d'un geste qui n'est pas le sien.
 *
 * Elle ne sait rien écrire. Aucun score, aucune table, aucun statut : c'est
 * une page de lecture, et c'est ce qui permet de l'ouvrir aux inscrits.
 */
new class extends Component
{
    use HasBreadcrumbs, ReadsTournamentLiveState;

    public string $activeTab = 'my-tournament';

    public Tournament $tournament;

    /**
     * Le joueur est-il inscrit à ce tournoi ?
     *
     * C'est la porte. Le comité passe aussi, pour voir ce que ses joueurs
     * voient — mais il n'a pas à s'inscrire pour cela.
     */
    #[Computed]
    public function isParticipant(): bool
    {
        return $this->tournament->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting', 'no_show'])
            ->whereKey(auth()->id())
            ->exists();
    }

    public function mount(): void
    {
        abort_unless(
            $this->isParticipant || auth()->user()?->can(Permission::TournamentsManage->value),
            403,
        );
    }

    /**
     * Le match du joueur qui est en train de se jouer, s'il y en a un.
     *
     * @return array{match: TournamentMatch, table: string, room: string, startedAt: mixed}|null
     */
    #[Computed]
    public function myLiveMatch(): ?array
    {
        return $this->liveMatches->first(
            fn (array $live): bool => $this->involvesMe($live['match'])
        );
    }

    /**
     * Le prochain match du joueur, et combien de matchs passent avant.
     *
     * Le compte suit l'ordre de la file, c'est-à-dire l'ordre dans lequel le
     * comité lance les matchs. Il n'y a pas de minutes annoncées : une table
     * peut se libérer en trois minutes ou en vingt, et une estimation
     * optimiste envoie quelqu'un au bar pour lui faire rater son match.
     *
     * @return array{match: TournamentMatch, ahead: int}|null
     */
    #[Computed]
    public function myNextMatch(): ?array
    {
        $position = $this->queue->search(fn (TournamentMatch $match): bool => $this->involvesMe($match));

        if ($position === false) {
            return null;
        }

        return [
            'match' => $this->queue[$position],
            'ahead' => $position,
        ];
    }

    /** Les matchs du joueur déjà joués, pour qu'il retrouve ses résultats. */
    #[Computed]
    public function myPlayedMatches(): Collection
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->where('status', 'completed')
            ->with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'pool'])
            ->get()
            ->filter(fn (TournamentMatch $match): bool => $this->involvesMe($match))
            ->values();
    }

    /**
     * La file d'attente du tournoi, dans l'ordre où le comité lance les matchs.
     *
     * @return Collection<int, TournamentMatch>
     */
    #[Computed]
    public function queue(): Collection
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->where('status', 'scheduled')
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'pool', 'referee'])
            ->orderByRaw('CASE WHEN pool_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE round WHEN 'round_16' THEN 1 WHEN 'quarterfinal' THEN 2 WHEN 'semifinal' THEN 3 WHEN 'final' THEN 4 WHEN 'bronze' THEN 5 ELSE 0 END")
            ->orderBy('match_order')
            ->limit(30)
            ->get()
            ->values();
    }

    public function render(): View
    {
        return view('pages.club-events.tournaments.⚡live.live', [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]);
    }

    #[Computed]
    public function tournamentIsLive(): bool
    {
        return $this->tournament->status === TournamentStatusEnum::PENDING;
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current($this->tournament->name);
    }

    /** Le joueur connecté est-il d'un côté ou de l'autre de ce match ? */
    private function involvesMe(TournamentMatch $match): bool
    {
        $me = auth()->id();

        return $me !== null && $match->sidePlayerIds(1)->merge($match->sidePlayerIds(2))->contains($me);
    }
};
