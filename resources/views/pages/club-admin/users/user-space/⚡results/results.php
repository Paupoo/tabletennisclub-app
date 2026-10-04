<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubAdmin\Users\UserSpace\Results;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\OfficialTournamentMatch;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Services\MemberResults;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Everything a member has played, interclub and official tournaments.
 *
 * Reads the federation's own data, which is what lets it reach back further
 * than the club's records do: an imported season brings teams with nobody on
 * their roster, so a line on a sheet is the only thing tying a member to a
 * match they played years ago.
 *
 * Nothing here is derived from the team score. A tie carries points a player
 * list cannot explain — the double, and every individual match forfeited by a
 * side that came up short — so the count is of lines this member is named on,
 * and nothing else.
 *
 * The two kinds are only ever added up on the "everything" tab, for the member
 * alone, and the sum always shows what it is made of.
 */
new class extends Component
{
    use HasBreadcrumbs;

    #[Url(as: 'season', except: 0)]
    public int $seasonId = 0;

    #[Url(except: 'all')]
    public string $tab = 'all';

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
        return $this->view()->title(__('My results'));
    }

    public function with(): array
    {
        $results = app(MemberResults::class);
        $interclub = $results->interclubLines($this->userId, $this->seasonId);
        $tournaments = $results->tournamentLines($this->userId, $this->seasonId);

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add(__('My profile'), route('admin.user.profile', $this->userId))
                ->current(__('My results'))
                ->toArray(),
            'bySeason' => $this->groupBySeason($interclub),
            'feed' => $results->feed(Auth::user(), $interclub, $tournaments),
            'seasons' => $this->seasonsPlayed(),
            'totals' => $results->totals($interclub, $tournaments),
            'tournamentsBySeason' => $results->tournamentsBySeason($tournaments),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('My results'));
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
     * Only the seasons this member actually played, of either kind, newest
     * first — a filter offering years somebody was not in the club is a
     * filter that lies.
     *
     * @return Collection<int, Season>
     */
    private function seasonsPlayed(): Collection
    {
        return Season::query()
            ->where(fn ($query) => $query
                ->whereHas('interclubs', fn ($sub) => $sub->whereHas(
                    'individualMatches', fn ($lines) => $lines->where('user_id', $this->userId)
                ))
                ->orWhereIn('id', OfficialTournamentMatch::query()
                    ->where('user_id', $this->userId)
                    ->select('season_id')))
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get();
    }
};
