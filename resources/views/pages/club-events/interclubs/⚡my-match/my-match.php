<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\MyMatch;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\InterclubResult;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\InterclubResultEnum;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * One interclub fixture, for the player who is in it.
 *
 * The screen every notification about a match should always have opened. Four
 * of them, the member calendar and the team card all used to point at the
 * personal match list, which knows nothing of which match was meant — and,
 * filtering on `start_date_time >= now()`, did not even contain a played one.
 *
 * Reads nothing but this fixture and writes nothing but the caller's own
 * availability, which is what lets it be opened by the whole roster rather than
 * by a délégation.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    public string $availabilityNote = '';

    #[Locked]
    public int $interclubId;

    public bool $noteModal = false;

    /**
     * Memoised: `with()` and `myRegistration()` both need the fixture, and
     * loading nine relations twice per render buys nothing.
     */
    private ?Interclub $loaded = null;

    public function markAvailability(string $availability): void
    {
        $interclub = $this->interclub();
        $user = Auth::user();

        if (! $this->canAnswer($interclub, $user)) {
            $this->error(__('Your availability can no longer be changed for this match.'));

            return;
        }

        $interclub->markAvailability(
            $user,
            InterclubAvailability::from($availability),
            $this->availabilityNote !== '' ? $this->availabilityNote : null,
        );

        $this->noteModal = false;
        $this->availabilityNote = '';

        $this->success(__('Availability saved!'), position: 'toast-bottom toast-end');
    }

    public function mount(Interclub $interclub): void
    {
        Gate::authorize('viewMatchPage', $interclub);

        // A bye is a round without an opponent. It is imported so the results
        // screens can name it, but there is no date to keep, no lineup to
        // publish and nobody to be available for.
        abort_if($interclub->is_bye, 404);

        $this->interclubId = $interclub->id;
    }

    public function openNote(): void
    {
        $this->availabilityNote = (string) $this->myRegistration()?->availability_note;
        $this->noteModal = true;
    }

    public function render(): View
    {
        return $this->view()->title(__('My match'));
    }

    public function with(): array
    {
        $interclub = $this->interclub();
        $user = Auth::user();

        $myTeam = $interclub->playerTeam($user);
        $isHome = $myTeam !== null
            ? $interclub->visited_team_id === $myTeam->id
            : $interclub->isHome();

        // Falls back to the club's own side for a reader who plays on neither —
        // a délégation, or the captain of a team they are not listed in.
        $team = $myTeam ?? $interclub->ourTeam();
        $opponent = $isHome ? $interclub->visitingTeam : $interclub->visitedTeam;

        $registration = $this->myRegistration();
        $lineupPublished = $interclub->isLineupPublished();
        $isPast = $interclub->start_date_time->isPast();
        $result = $interclub->interclubResult;

        return [
            'availabilityOptions' => InterclubAvailability::cases(),
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add(__('My Matches'), route('admin.interclubs.my-matches'))
                ->current($interclub->start_date_time->translatedFormat('D j M Y'))
                ->toArray(),
            'canAnswer' => $this->canAnswer($interclub, $user),
            'captain' => $team?->captain,
            'categoryLabel' => LeagueCategory::fromName($team?->league?->category)?->label(),
            'counts' => $this->availabilityCounts($interclub, $team),
            'division' => $interclub->league?->division ?: $team?->league?->division,
            'icsUrl' => route('admin.interclubs.my-match.ics', $interclub),
            'interclub' => $interclub,
            'isHome' => $isHome,
            'isOnRoster' => $myTeam !== null,
            // L'équipe, capitaine compris, par opposition au visiteur venu du
            // calendrier du club : à elle seule le mot du capitaine et ses
            // coordonnées sans condition.
            'isTeamMember' => $myTeam !== null || ($team?->captain_id !== null && $team->captain_id === $user->id),
            'captainPhoneVisible' => $team?->captain !== null
                && ($myTeam !== null || $team->captain_id === $user->id || $team->captain->contactVisibleTo($user, 'phone')),
            'captainEmailVisible' => $team?->captain !== null
                && ($myTeam !== null || $team->captain_id === $user->id || $team->captain->contactVisibleTo($user, 'email')),
            'isPast' => $isPast,
            'isSelected' => (bool) $registration?->is_selected && $lineupPublished,
            // L'ordre de la feuille (C.22.3.1) : indice de force, puis nom et
            // prénom pour départager un même indice. Un indice absent en dernier.
            'lineup' => $lineupPublished
                ? $interclub->getSelectedPlayers()->sortBy([
                    fn (User $a, User $b): int => ($a->forceListFor($team?->league?->category) ?? PHP_INT_MAX) <=> ($b->forceListFor($team?->league?->category) ?? PHP_INT_MAX),
                    fn (User $a, User $b): int => strcasecmp((string) $a->last_name, (string) $b->last_name),
                    fn (User $a, User $b): int => strcasecmp((string) $a->first_name, (string) $b->first_name),
                ])->values()
                : collect(),
            'lineupCategory' => $team?->league?->category,
            'sheetInfo' => $this->sheetInfo($interclub, $team),
            'lineupPublished' => $lineupPublished,
            'myAvailability' => $registration?->availability
                ? InterclubAvailability::from($registration->availability)
                : null,
            'myNote' => $registration?->availability_note,
            'opponent' => $opponent,
            'players' => $isPast ? $this->playersWhoPlayed($interclub) : collect(),
            'sheet' => $isPast ? $this->matchSheet($interclub) : collect(),
            'tally' => $isPast ? $this->winTally($interclub) : collect(),
            'result' => $result,
            'resultLabel' => $this->resultLabel($result),
            'score' => $this->scoreFromOurSide($result, $isHome),
            // The pivot declares no casts, so every timestamp on it arrives as a
            // string; the view formats this one.
            'selectedAt' => $registration?->selection_confirmed_at
                ? Carbon::parse($registration->selection_confirmed_at)
                : null,
            // Jouer à 3 : ce que le capitaine a déclaré, par qui et quand. Rien
            // tant que la composition n'est pas publiée.
            'shortHanded' => $lineupPublished && $interclub->isShortHanded()
                ? [
                    'playing' => $interclub->shortHandedSummary()['playing'] ?? 0,
                    'max' => $interclub->total_players,
                    'by' => $interclub->shortHandedConfirmedBy?->full_name,
                    'at' => $interclub->short_handed_confirmed_at,
                ]
                : null,
            'team' => $team,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('My Matches'), route('admin.interclubs.my-matches'))
            ->current(__('My match'));
    }

    /**
     * How the team answered, in counts and never in names.
     *
     * The named answers — and the notes, which are sentences about weddings and
     * funerals — stay on the captain's screen. What a teammate gets here is the
     * same aggregate the team card already shows them: enough to see the reply
     * is being waited on, nothing about who is dodging.
     *
     * @return array<string, int>
     */
    private function availabilityCounts(Interclub $interclub, ?Team $team): array
    {
        $rosterSize = $team?->users()->count() ?? 0;

        $answers = $interclub->users()
            ->whereNotNull('interclub_user.availability')
            ->pluck('interclub_user.availability');

        $counts = [
            'available' => $answers->where(fn (string $a): bool => $a === InterclubAvailability::AVAILABLE->value)->count(),
            'maybe' => $answers->where(fn (string $a): bool => $a === InterclubAvailability::MAYBE->value)->count(),
            'unavailable' => $answers->where(fn (string $a): bool => $a === InterclubAvailability::UNAVAILABLE->value)->count(),
        ];

        $counts['no_response'] = max(0, $rosterSize - $answers->count());

        return $counts;
    }

    /**
     * Whether the caller may still say yes or no.
     *
     * Closed by the lineup going out, not by the first whistle: once the captain
     * has told the team who plays, an answer changed here would move nothing —
     * nothing in this application notifies a captain — and would quietly
     * contradict a mail everyone has already read. The page hands over the
     * captain's phone number instead.
     */
    private function canAnswer(Interclub $interclub, User $user): bool
    {
        return $interclub->playerTeam($user) !== null
            && ! $interclub->start_date_time->isPast()
            && ! $interclub->isLineupPublished();
    }

    private function interclub(): Interclub
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        return $this->loaded = Interclub::with([
            'interclubResult',
            'league',
            'room',
            'visitedTeam.captain',
            'visitedTeam.club',
            'visitedTeam.league',
            'visitingTeam.captain',
            'visitingTeam.club',
            'visitingTeam.league',
        ])->findOrFail($this->interclubId);
    }

    /**
     * Who actually turned out, once a result has been recorded.
     *
     * `has_played` is stamped on the selected roster when the result is saved,
     * so it is the honest answer for a played match — a published lineup can
     * still have changed on the night.
     *
     * @return Collection<int, User>
     */
    /**
     * Every line of the federation's sheet, ours first, in playing order.
     *
     * @return Collection<int, InterclubIndividualMatch>
     */
    private function matchSheet(Interclub $interclub): Collection
    {
        return InterclubIndividualMatch::with('user')
            ->where('interclub_id', $interclub->id)
            ->orderBy('position')
            ->get();
    }

    private function myRegistration(): ?object
    {
        return $this->interclub()
            ->users()
            ->where('users.id', Auth::id())
            ->first()?->registration;
    }

    private function playersWhoPlayed(Interclub $interclub): Collection
    {
        $played = $interclub->users()->wherePivot('has_played', true)->get();

        return $played->isNotEmpty() ? $played : $interclub->getSelectedPlayers();
    }

    private function resultLabel(?InterclubResult $result): ?string
    {
        return match ($result?->result) {
            InterclubResultEnum::WIN => __('Win'),
            InterclubResultEnum::LOSS => __('Loss'),
            InterclubResultEnum::DRAW => __('Draw'),
            InterclubResultEnum::FORFEIT_WIN => __('Win by forfeit'),
            InterclubResultEnum::FORFEIT_LOSS => __('Loss by forfeit'),
            InterclubResultEnum::WITHDRAWAL => __('Our general forfeit'),
            InterclubResultEnum::WITHDRAWAL_OPPONENT => __('Opponent general forfeit'),
            default => null,
        };
    }

    /**
     * The score written our way round.
     *
     * `interclub_results.score` is stored home-first, which reads backwards to a
     * player who was away: their 4-12 defeat is filed as 12-4.
     */
    private function scoreFromOurSide(?InterclubResult $result, bool $isHome): ?string
    {
        if ($result?->score === null || ! str_contains($result->score, '-')) {
            return $result?->score;
        }

        [$home, $away] = array_map(intval(...), explode('-', $result->score, 2));

        return $isHome ? "{$home}-{$away}" : "{$away}-{$home}";
    }

    /**
     * What the match sheet asks for, so nobody hunts for it at the table.
     *
     * The federation writes division and series as one code — « P2A », « 3C » —
     * the series being the final letter.
     *
     * @return array{match_number: string|null, category: string|null, division: string|null, series: string|null, week: int|null, home: array{name: string, licence: string|null}, away: array{name: string, licence: string|null}}
     */
    private function sheetInfo(Interclub $interclub, ?Team $team): array
    {
        $code = (string) ($interclub->league?->division ?: $team?->league?->division);
        $splits = preg_match('/^(.+?)([A-Z])$/', $code, $parts) === 1;

        $side = fn (?Team $side): array => [
            'name' => trim(($side?->club?->name ?? '') . ' ' . ($side?->name ?? '')) ?: '—',
            'licence' => $side?->club?->licence,
        ];

        return [
            'match_number' => $interclub->aftt_match_id,
            'category' => LeagueCategory::fromName($interclub->league?->category ?? $team?->league?->category)?->label(),
            'division' => $code === '' ? null : ($splits ? $parts[1] : $code),
            'series' => $splits ? $parts[2] : null,
            'week' => $interclub->week_number,
            'home' => $side($interclub->visitedTeam),
            'away' => $side($interclub->visitingTeam),
        ];
    }

    /**
     * How many each of our players won, plus the lines no player owns.
     *
     * Counted from the sheet rather than read from the federation's own
     * per-player total, which leaves out the double and is simply absent for a
     * player who forfeited — three names adding up to 8 under a 9-1 scoreline
     * is the kind of arithmetic a reader checks and stops trusting.
     *
     * The double and any forfeited line therefore get a row of their own, so
     * the column adds up to the team score.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function winTally(Interclub $interclub): Collection
    {
        $lines = $this->matchSheet($interclub);

        if ($lines->isEmpty()) {
            return collect();
        }

        $named = $lines
            ->filter(fn (InterclubIndividualMatch $line): bool => ! $line->is_double
                && ($line->user_id !== null || $line->our_player_name !== null))
            ->groupBy(fn (InterclubIndividualMatch $line): string => $line->user_id
                ? 'u' . $line->user_id
                : 'x' . $line->our_player_licence)
            ->map(fn (Collection $group): array => [
                'is_me' => $group->first()->user_id === Auth::id(),
                'label' => $group->first()->user?->full_name ?? $group->first()->our_player_name,
                'played' => $group->count(),
                'wins' => $group->where('we_won', true)->count(),
            ])
            ->sortByDesc('wins')
            ->values();

        $doubleWins = $lines->where('is_double', true)->where('we_won', true)->count();

        if ($lines->contains('is_double', true)) {
            $named->push([
                'is_me' => false,
                'label' => __('Doubles'),
                'played' => $lines->where('is_double', true)->count(),
                'wins' => $doubleWins,
            ]);
        }

        return $named;
    }
};
