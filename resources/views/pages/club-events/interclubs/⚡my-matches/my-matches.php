<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\MyMatches;

use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\InterclubAvailability;
use App\Domains\Shared\Enums\InterclubResultEnum;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    public string $availabilityNote = '';

    public bool $bulkUnavailableModal = false;

    public ?int $editingInterclubId = null;

    public function bulkMarkAvailability(string $availability): void
    {
        $user = Auth::user();
        $enum = InterclubAvailability::from($availability);
        $teamIds = $user->teams()->pluck('teams.id');

        Interclub::withoutByes()
            ->where('start_date_time', '>=', now())
            ->where(fn ($q) => $q->whereIn('visited_team_id', $teamIds)
                ->orWhereIn('visiting_team_id', $teamIds))
            ->get()
            ->each(fn ($ic) => $ic->markAvailability($user, $enum));

        $this->success(__('Availability set for all upcoming matches.'), position: 'toast-bottom toast-end');
    }

    public function bulkMarkUnavailable(): void
    {
        $this->bulkUnavailableModal = false;
        $this->bulkMarkAvailability('unavailable');
    }

    public function markAvailability(int $interclubId, string $availability): void
    {
        $user = Auth::user();
        $interclub = Interclub::findOrFail($interclubId);

        $team = $this->getUserTeamForInterclub($interclub, $user->id);

        if (! $team) {
            $this->error(__('You are not part of this team.'));

            return;
        }

        $enum = InterclubAvailability::from($availability);
        $interclub->markAvailability($user, $enum, $this->availabilityNote ?: null);

        $this->editingInterclubId = null;
        $this->availabilityNote = '';

        $this->success(__('Availability saved!'), position: 'toast-bottom toast-end');
    }

    public function openNote(int $interclubId): void
    {
        $this->editingInterclubId = $interclubId;
        $this->availabilityNote = '';
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $user = Auth::user();

        $teamIds = $user->teams()->pluck('teams.id');

        $interclubs = Interclub::with([
            'visitedTeam.club',
            'visitedTeam.league',
            'visitingTeam.club',
            'visitingTeam.league',
            'league',
        ])
            // Ceux qui jouent : le WO figure sur la feuille mais pas à la table.
            ->withCount(['users as selected_count' => fn ($q) => $q->where('interclub_user.is_selected', true)
                ->where('interclub_user.is_walkover', false)])
            ->withoutByes()
            ->where(function ($q) use ($teamIds): void {
                $q->whereIn('visited_team_id', $teamIds)
                    ->orWhereIn('visiting_team_id', $teamIds);
            })
            ->where('start_date_time', '>=', now())
            ->orderBy('start_date_time')
            ->get()
            ->map(function (Interclub $interclub) use ($user): array {
                $pivot = $interclub->users()
                    ->where('users.id', $user->id)
                    ->first()?->registration;

                $availability = $pivot?->availability
                    ? InterclubAvailability::from($pivot->availability)
                    : null;

                $ourTeam = $interclub->ourTeam();
                $isHome = $interclub->isHome();
                $opponent = $interclub->opponentTeam()?->fullName() ?? '—';

                $division = $interclub->league?->division ?? '';
                $teamLabel = ($ourTeam?->name ?? '—') . ($division ? ' — ' . $division : '');

                $category = LeagueCategory::fromName($ourTeam?->league?->category);

                return [
                    'id' => $interclub->id,
                    'category' => $category,
                    'category_label' => $category?->label() ?? '—',
                    'category_sort' => $category?->sortOrder() ?? 99,
                    'team_name' => $teamLabel,
                    'opponent' => $opponent,
                    'is_home' => $isHome,
                    'division' => $division,
                    'date' => $interclub->start_date_time->format('d/m/Y'),
                    'time' => $interclub->start_date_time->format('H:i'),
                    'address' => $interclub->address ?? '—',
                    'week_number' => $interclub->week_number,
                    'availability' => $availability,
                    'availability_note' => $pivot?->availability_note,
                    'is_selected' => (bool) $pivot?->is_selected,
                    'selection_confirmed_at' => $pivot?->selection_confirmed_at,
                    // Jouer à 3 : le nombre annoncé, seulement si le capitaine l'a déclaré.
                    'short_handed_count' => $interclub->isShortHanded() ? (int) $interclub->selected_count : null,
                    'days_until' => (int) now()->diffInDays($interclub->start_date_time, false),
                ];
            });

        $grouped = $interclubs
            ->sortBy([['category_sort', 'asc'], ['team_name', 'asc']])
            ->groupBy('category_label')
            ->map(fn ($catGroup) => $catGroup->groupBy('team_name'));

        $season = Season::current();
        $matchDayMap = $season ? Interclub::matchDayMap($season->id) : [];

        return [
            'played' => $this->playedMatches($teamIds),
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add(__('Interclubs'), route('admin.interclubs.captain-selection'))
                ->current(__('My Matches'))
                ->toArray(),
            'grouped' => $grouped,
            'availabilityOptions' => InterclubAvailability::cases(),
            'matchDayMap' => $matchDayMap,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('My Matches'));
    }

    private function getUserTeamForInterclub(Interclub $interclub, int $userId): ?Team
    {
        $teamIds = Team::whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->pluck('id');

        if ($teamIds->contains($interclub->visited_team_id)) {
            return $interclub->visitedTeam;
        }

        if ($teamIds->contains($interclub->visiting_team_id)) {
            return $interclub->visitingTeam;
        }

        return null;
    }

    /**
     * This season's matches already played, most recent first.
     *
     * The list above filters on `start_date_time >= now()`, which is right for
     * answering availability and wrong for everything else: it left the member
     * with no screen anywhere naming a match they had played. A notification
     * about a result therefore led to a page that did not contain it.
     *
     * @param  Collection<int, int>  $teamIds
     * @return Collection<int, array<string, mixed>>
     */
    private function playedMatches($teamIds)
    {
        return Interclub::with([
            'interclubResult',
            'visitedTeam.club',
            'visitingTeam.club',
        ])
            ->withoutByes()
            // Roster membership, or a line on the federation's sheet. A season
            // imported from the federation brings teams with nobody in them —
            // it publishes its own teams and never our roster — so on that
            // history the sheet is the only thing tying a member to a match
            // they actually played.
            ->where(fn ($q) => $q->whereIn('visited_team_id', $teamIds)
                ->orWhereIn('visiting_team_id', $teamIds)
                ->orWhereIn('id', InterclubIndividualMatch::query()
                    ->where('user_id', Auth::id())
                    ->select('interclub_id')))
            ->where('start_date_time', '<', now())
            ->orderByDesc('start_date_time')
            ->orderByDesc('interclubs.id')
            ->get()
            ->map(function (Interclub $interclub) use ($teamIds): array {
                $isHome = match (true) {
                    $teamIds->contains($interclub->visited_team_id) => true,
                    $teamIds->contains($interclub->visiting_team_id) => false,
                    // Reached through the sheet rather than a roster: the club's
                    // own side is the side the member played for.
                    default => $interclub->isHome(),
                };
                $opponent = $isHome ? $interclub->visitingTeam : $interclub->visitedTeam;
                $matchResult = $interclub->interclubResult;

                // Stored home-first: an away 4-12 is filed as 12-4.
                $score = null;
                if ($matchResult?->score && str_contains($matchResult->score, '-')) {
                    [$home, $away] = array_map(intval(...), explode('-', $matchResult->score, 2));
                    $score = $isHome ? "{$home}-{$away}" : "{$away}-{$home}";
                }

                [$letter, $tone] = match ($matchResult?->result) {
                    InterclubResultEnum::WIN, InterclubResultEnum::FORFEIT_WIN, InterclubResultEnum::WITHDRAWAL_OPPONENT => ['V', 'bg-success/15 text-success'],
                    InterclubResultEnum::DRAW => ['P', 'bg-base-200 text-muted'],
                    InterclubResultEnum::LOSS, InterclubResultEnum::FORFEIT_LOSS, InterclubResultEnum::WITHDRAWAL => ['D', 'bg-error/15 text-error'],
                    default => [null, ''],
                };

                return [
                    'date' => $interclub->start_date_time,
                    'id' => $interclub->id,
                    'is_home' => $isHome,
                    'letter' => $letter,
                    'opponent' => $opponent?->fullName() ?? '—',
                    'score' => $score,
                    'tone' => $tone,
                ];
            });
    }
};
