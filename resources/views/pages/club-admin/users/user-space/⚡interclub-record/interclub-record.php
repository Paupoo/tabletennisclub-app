<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubAdmin\Users\UserSpace\InterclubRecord;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Everything a member has played in interclub, season by season.
 *
 * Reads the federation's own match sheets, which is what lets it reach back
 * further than the club's records do: an imported season brings teams with
 * nobody on their roster, so a line on a sheet is the only thing tying a
 * member to a match they played years ago.
 *
 * Nothing here is derived from the team score. A tie carries points a player
 * list cannot explain — the double, and every individual match forfeited by a
 * side that came up short — so the count is of lines this member is named on,
 * and nothing else.
 */
new class extends Component
{
    use HasBreadcrumbs;

    #[Url(as: 'season', except: 0)]
    public int $seasonId = 0;

    #[Locked]
    public int $userId;

    public function mount(User $user): void
    {
        // Self only. A member's record is theirs; the office reads it from the
        // member's file, not by walking into their space.
        abort_unless(Auth::user()->is($user), 403);

        $this->userId = $user->id;
    }

    public function render(): View
    {
        return $this->view()->title(__('My interclub record'));
    }

    public function with(): array
    {
        $lines = $this->lines();

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add(__('My profile'), route('admin.user.profile', $this->userId))
                ->current(__('My interclub record'))
                ->toArray(),
            'bySeason' => $this->groupBySeason($lines),
            'seasons' => $this->seasonsPlayed(),
            'totals' => $this->totals($lines),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('My interclub record'));
    }

    /**
     * One entry per tie, each carrying the lines played in it.
     *
     * @param  Collection<int, InterclubIndividualMatch>  $lines
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    private function groupBySeason(Collection $lines): Collection
    {
        return $lines
            ->groupBy(fn (InterclubIndividualMatch $line): string => $line->interclub->season?->name ?? '—')
            ->map(fn (Collection $seasonLines): Collection => $seasonLines
                ->groupBy(fn (InterclubIndividualMatch $line): int => $line->interclub_id)
                ->map(function (Collection $tie): array {
                    $fixture = $tie->first()->interclub;
                    $ourTeam = $fixture->playerTeam(Auth::user()) ?? $fixture->ourTeam();
                    $isHome = $ourTeam !== null && $fixture->visited_team_id === $ourTeam->id;

                    return [
                        'date' => $fixture->start_date_time,
                        'id' => $fixture->id,
                        'is_home' => $isHome,
                        'lines' => $tie->sortBy('position')->values(),
                        'opponent' => ($isHome ? $fixture->visitingTeam : $fixture->visitedTeam)?->fullName() ?? '—',
                        'team' => $ourTeam?->fullName() ?? '—',
                        'won' => $tie->where('we_won', true)->count(),
                    ];
                })
                ->sortByDesc('date')
                ->values());
    }

    /**
     * @return Collection<int, InterclubIndividualMatch>
     */
    private function lines(): Collection
    {
        return InterclubIndividualMatch::with([
            'interclub.season',
            'interclub.visitedTeam.club',
            'interclub.visitingTeam.club',
        ])
            ->where('user_id', $this->userId)
            ->when($this->seasonId > 0, fn ($query) => $query->whereHas(
                'interclub', fn ($sub) => $sub->where('season_id', $this->seasonId)
            ))
            ->get()
            ->sortByDesc(fn (InterclubIndividualMatch $line) => $line->interclub->start_date_time)
            ->values();
    }

    /**
     * Only the seasons this member actually played, newest first — a filter
     * offering years somebody was not in the club is a filter that lies.
     *
     * @return Collection<int, Season>
     */
    private function seasonsPlayed(): Collection
    {
        return Season::whereHas('interclubs', fn ($query) => $query->whereHas(
            'individualMatches', fn ($sub) => $sub->where('user_id', $this->userId)
        ))
            ->orderByDesc('start_at')
            ->get();
    }

    /**
     * @param  Collection<int, InterclubIndividualMatch>  $lines
     * @return array<string, int>
     */
    private function totals(Collection $lines): array
    {
        $played = $lines->count();
        $won = $lines->where('we_won', true)->count();

        return [
            'played' => $played,
            'rate' => $played > 0 ? (int) round($won / $played * 100) : 0,
            'ties' => $lines->pluck('interclub_id')->unique()->count(),
            'won' => $won,
        ];
    }
};
