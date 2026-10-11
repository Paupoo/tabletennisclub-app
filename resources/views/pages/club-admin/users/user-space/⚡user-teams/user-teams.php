<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\InterclubResultEnum;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Support\Breadcrumb;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public ?int $selectedTeamId = null;

    public User $user;

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

        $this->user = $user;
        $this->selectedTeamId = $this->teams()->first()?->id;
    }

    public function with(): array
    {
        $teams = $this->teams();

        /** @var Team|null $team */
        $team = $teams->firstWhere('id', $this->selectedTeamId) ?? $teams->first();

        return [
            'teams' => $teams,
            'team' => $team,
            'teamOptions' => $teams
                ->map(fn (Team $option): array => [
                    'id' => $option->id,
                    'name' => $option->fullName() . ($option->season?->name ? ' · ' . $option->season->name : ''),
                ])
                ->values()
                ->all(),
            'categoryLabel' => $team ? $this->categoryLabel($team) : null,
            'upcomingMatches' => $team ? $this->upcomingMatches($team) : collect(),
            'pastMatches' => $team ? $this->pastMatches($team) : collect(),
            'roster' => $team ? $this->roster($team) : collect(),
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('My team(s)'))
                ->toArray(),
        ];
    }

    /**
     * Human label for the team's league category.
     */
    private function categoryLabel(Team $team): ?string
    {
        return LeagueCategory::fromName($team->league?->category)?->label();
    }

    /**
     * The team's matches already played, newest first, each with who played
     * and how many singles they won and lost.
     *
     * A player the sheet could not match to a member is still named, after
     * every member, under the name the federation wrote.
     *
     * @return Collection<int, array{id: int, opponent: string, is_home: bool, start_date_time: Carbon, week_number: int|null, score: string|null, result: InterclubResultEnum|null, players: Collection<int, array{id: int|null, name: string, won: int, lost: int}>}>
     */
    private function pastMatches(Team $team): Collection
    {
        $category = $team->league?->category;

        return Interclub::with([
            'visitedTeam.club',
            'visitingTeam.club',
            'interclubResult',
            'individualMatches' => fn ($query) => $query->where('is_double', false)->with('user'),
        ])
            ->where(fn ($query) => $query
                ->where('visited_team_id', $team->id)
                ->orWhere('visiting_team_id', $team->id))
            ->withoutByes()
            ->where('start_date_time', '<', now())
            ->orderByDesc('start_date_time')
            ->orderByDesc('interclubs.id')
            ->get()
            ->map(function (Interclub $interclub) use ($category): array {
                $isHome = $interclub->isHome();

                $players = $interclub->individualMatches
                    ->groupBy(fn (InterclubIndividualMatch $line): string => $line->user_id !== null ? 'u' . $line->user_id : 'x' . ($line->our_player_licence ?? $line->our_player_name))
                    ->map(function (Collection $lines) use ($category): array {
                        /** @var InterclubIndividualMatch $first */
                        $first = $lines->first();

                        return [
                            'id' => $first->user_id,
                            'name' => $first->user?->last_name ?? (string) $first->our_player_name,
                            'first_name' => (string) $first->user?->first_name,
                            'sort' => $first->user?->forceListSortKey($category) ?? '9999|~|' . mb_strtolower((string) $first->our_player_name),
                            'won' => $lines->where('we_won', true)->count(),
                            'lost' => $lines->where('we_won', false)->count(),
                        ];
                    })
                    ->sortBy('sort')
                    ->values();

                // Two Martins on one sheet read as one: name them by initial.
                $shared = $players->countBy('name')->filter(fn (int $count): bool => $count > 1);

                $players = $players
                    ->map(fn (array $player): array => [
                        ...Arr::except($player, ['sort', 'first_name']),
                        'name' => $shared->has($player['name']) && $player['first_name'] !== ''
                            ? $player['name'] . ' ' . mb_substr($player['first_name'], 0, 1) . '.'
                            : $player['name'],
                    ]);

                return [
                    'id' => $interclub->id,
                    'opponent' => $interclub->opponentTeam()?->fullName() ?? '—',
                    'is_home' => $isHome,
                    'start_date_time' => $interclub->start_date_time,
                    'week_number' => $interclub->week_number,
                    'score' => $interclub->interclubResult?->scoreFromOurSide($isHome),
                    'result' => $interclub->interclubResult?->result,
                    'players' => $players,
                ];
            });
    }

    /**
     * The team's players in force-list order for its category, each with
     * their singles record for this team this season.
     *
     * Counted from the match sheets, like « My results »: every single the
     * member is named on, forfeits included; the double names nobody.
     *
     * @return Collection<int, array{user: User, force_list: int|null, won: int, lost: int}>
     */
    private function roster(Team $team): Collection
    {
        $category = $team->league?->category;

        $lines = InterclubIndividualMatch::query()
            ->where('is_double', false)
            ->whereIn('user_id', $team->users->pluck('id'))
            ->whereHas('interclub', fn ($query) => $query
                ->where('visited_team_id', $team->id)
                ->orWhere('visiting_team_id', $team->id))
            ->get(['user_id', 'we_won'])
            ->groupBy('user_id');

        return $team->users
            ->sortBy(fn (User $mate): string => $mate->forceListSortKey($category))
            ->map(fn (User $mate): array => [
                'user' => $mate,
                'force_list' => $mate->forceListFor($category),
                'won' => $lines->get($mate->id)?->where('we_won', true)->count() ?? 0,
                'lost' => $lines->get($mate->id)?->where('we_won', false)->count() ?? 0,
            ])
            ->values();
    }

    /**
     * @return Collection<int, Team>
     */
    private function teams(): Collection
    {
        return $this->user->teams()
            ->with(['club', 'league', 'season', 'captain', 'users'])
            ->get()
            ->toBase()
            ->sortByDesc(fn (Team $team) => $team->season?->start_at)
            ->values();
    }

    /**
     * Upcoming interclub encounters of the given team, with the member's own
     * availability and the aggregated answers of the whole roster.
     *
     * @return Collection<int, array{id: int, opponent: string, is_home: bool, start_date_time: Carbon, address: string, week_number: int|null, availability: InterclubAvailability|null, is_selected: bool, availability_counts: array{available: int, maybe: int, unavailable: int, no_response: int}}>
     */
    private function upcomingMatches(Team $team): Collection
    {
        $rosterIds = $team->users->pluck('id');

        return Interclub::with([
            'visitedTeam.club',
            'visitedTeam.league',
            'visitingTeam.club',
            'visitingTeam.league',
            'league',
            'users' => fn ($query) => $query->whereIn('users.id', $rosterIds),
        ])
            ->where(fn ($query) => $query
                ->where('visited_team_id', $team->id)
                ->orWhere('visiting_team_id', $team->id))
            ->where('start_date_time', '>=', now())
            ->orderBy('start_date_time')
            ->get()
            ->map(function (Interclub $interclub) use ($rosterIds): array {
                $pivot = $interclub->users
                    ->firstWhere('id', $this->user->id)?->registration;

                $answers = $interclub->users
                    ->map(fn ($mate) => $mate->registration->availability)
                    ->filter()
                    ->countBy();

                return [
                    'id' => $interclub->id,
                    'opponent' => $interclub->opponentTeam()?->fullName() ?? '—',
                    'is_home' => $interclub->isHome(),
                    'start_date_time' => $interclub->start_date_time,
                    'address' => $interclub->address ?? '—',
                    'week_number' => $interclub->week_number,
                    'availability' => $pivot?->availability
                        ? InterclubAvailability::from($pivot->availability)
                        : null,
                    'is_selected' => (bool) $pivot?->is_selected,
                    'availability_counts' => [
                        'available' => $answers[InterclubAvailability::AVAILABLE->value] ?? 0,
                        'maybe' => $answers[InterclubAvailability::MAYBE->value] ?? 0,
                        'unavailable' => $answers[InterclubAvailability::UNAVAILABLE->value] ?? 0,
                        'no_response' => max(0, $rosterIds->count() - $answers->sum()),
                    ],
                ];
            });
    }
};
