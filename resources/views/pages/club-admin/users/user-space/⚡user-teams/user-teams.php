<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Support\Breadcrumb;
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
     * @return Collection<int, array{id: int, opponent: string, is_home: bool, start_date_time: \Illuminate\Support\Carbon, address: string, week_number: int|null, availability: InterclubAvailability|null, is_selected: bool, availability_counts: array{available: int, maybe: int, unavailable: int, no_response: int}}>
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
