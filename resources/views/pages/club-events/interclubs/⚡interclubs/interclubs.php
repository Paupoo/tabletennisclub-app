<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Interclubs;

use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\MatchResult;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public ?int $seasonId = null;

    public ?int $selectedTeamId = null;

    public bool $editModal = false;

    public bool $deleteModal = false;

    public ?int $editingInterclubId = null;

    public ?int $deletingInterclubId = null;

    public ?int $formOurTeamId = null;

    public ?int $formOpponentTeamId = null;

    public ?string $formDate = null;

    public string $formTime = '09:00';

    public bool $formIsHome = true;

    public ?string $formAddress = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->is_admin || Auth::user()->is_committee_member, 403);

        $this->seasonId = Season::current()?->id;
    }

    public function openCreateModal(?int $teamId = null): void
    {
        $this->resetErrorBag();
        $this->editingInterclubId = null;
        $this->formOurTeamId = $teamId ?? $this->selectedTeamId;
        $this->formOpponentTeamId = null;
        $this->formDate = null;
        $this->formTime = '09:00';
        $this->formIsHome = true;
        $this->formAddress = null;
        $this->editModal = true;
    }

    public function openEditModal(int $interclubId): void
    {
        $interclub = Interclub::findOrFail($interclubId);
        $ourTeamIds = Team::inClub()->where('season_id', $this->seasonId)->pluck('id');
        $isHome = $ourTeamIds->contains($interclub->visited_team_id);

        $this->resetErrorBag();
        $this->editingInterclubId = $interclub->id;
        $this->formOurTeamId = $isHome ? $interclub->visited_team_id : $interclub->visiting_team_id;
        $this->formOpponentTeamId = $isHome ? $interclub->visiting_team_id : $interclub->visited_team_id;
        $this->formDate = $interclub->start_date_time->format('Y-m-d');
        $this->formTime = $interclub->start_date_time->format('H:i');
        $this->formIsHome = $isHome;
        $this->formAddress = $interclub->address;
        $this->editModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'formOurTeamId' => 'required|exists:teams,id',
            'formOpponentTeamId' => 'required|exists:teams,id',
            'formDate' => 'required|date',
            'formTime' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'formAddress' => 'nullable|string|max:255',
        ]);

        $ourTeam = Team::with(['league', 'club'])->findOrFail($this->formOurTeamId);
        $opponentTeam = Team::with('club')->findOrFail($this->formOpponentTeamId);
        $startDateTime = Carbon::parse($this->formDate . ' ' . $this->formTime);

        $data = [
            'season_id' => $this->seasonId,
            'league_id' => $ourTeam->league_id,
            'visited_team_id' => $this->formIsHome ? $ourTeam->id : $opponentTeam->id,
            'visiting_team_id' => $this->formIsHome ? $opponentTeam->id : $ourTeam->id,
            'start_date_time' => $startDateTime,
            'address' => $this->formAddress,
            'week_number' => $startDateTime->isoWeek(),
            'total_players' => $this->totalPlayersByCategory($ourTeam->league?->category),
        ];

        if ($this->editingInterclubId) {
            Interclub::findOrFail($this->editingInterclubId)->update($data);
            $this->success(__('Match updated'));
        } else {
            $interclub = Interclub::create($data);
            $this->syncMatchResult($ourTeam, $opponentTeam, $interclub, $this->formIsHome);
            $this->success(__('Match added'));
        }

        $this->editModal = false;
    }

    public function confirmDelete(int $interclubId): void
    {
        $this->deletingInterclubId = $interclubId;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        if ($this->deletingInterclubId) {
            Interclub::findOrFail($this->deletingInterclubId)->delete();
            $this->success(__('Match deleted'));
        }

        $this->deleteModal = false;
        $this->deletingInterclubId = null;
    }

    public function render(): View
    {
        return $this->view()->title(__('Interclubs Schedule'));
    }

    public function updatedFormOurTeamId(): void
    {
        $this->formOpponentTeamId = null;
    }

    public function with(): array
    {
        $ourTeams = Team::with(['league', 'club'])
            ->inClub()
            ->where('season_id', $this->seasonId)
            ->orderBy('name')
            ->get();

        $ourTeamIds = $ourTeams->pluck('id')->toArray();

        $query = Interclub::with(['visitedTeam.club', 'visitedTeam.league', 'visitingTeam.club', 'visitingTeam.league', 'league'])
            ->where('season_id', $this->seasonId)
            ->where(fn ($q) => $q->whereIn('visited_team_id', $ourTeamIds)->orWhereIn('visiting_team_id', $ourTeamIds))
            ->orderBy('start_date_time');

        if ($this->selectedTeamId) {
            $query->where(fn ($q) => $q->where('visited_team_id', $this->selectedTeamId)->orWhere('visiting_team_id', $this->selectedTeamId));
        }

        $interclubs = $query->get()->map(fn (Interclub $ic) => $this->formatInterclub($ic, $ourTeamIds));

        $grouped = $interclubs
            ->sortBy([['category_sort', 'asc'], ['our_team_name', 'asc'], ['date', 'asc']])
            ->groupBy('category_label')
            ->map(fn (Collection $catGroup) => $catGroup->groupBy('our_team_name'));

        $selectedLeagueId = $this->formOurTeamId
            ? Team::find($this->formOurTeamId)?->league_id
            : null;

        $opponentTeams = Team::with('club')
            ->notInClub()
            ->where('season_id', $this->seasonId)
            ->when($selectedLeagueId, fn ($q) => $q->where('league_id', $selectedLeagueId))
            ->orderBy('name')
            ->get()
            ->map(fn (Team $t) => [
                'id'   => $t->id,
                'name' => trim(($t->club?->name ?? '') . ' ' . $t->name),
            ]);

        return [
            'breadcrumbs'    => Breadcrumb::make()->home()->add(__('Interclubs'), route('admin.interclubs.captain-selection'))->current(__('Schedule'))->toArray(),
            'seasons'        => Season::orderBy('start_at')->get(),
            'ourTeams'       => $ourTeams,
            'ourTeamOptions' => $ourTeams->map(fn (Team $t) => [
                'id'   => $t->id,
                'name' => trim(($t->club?->name ?? '') . ' ' . $t->name),
            ])->values()->toArray(),
            'opponentTeams'  => $opponentTeams->toArray(),
            'grouped'        => $grouped,
            'total'          => $interclubs->count(),
        ];
    }

    private function formatInterclub(Interclub $ic, array $ourTeamIds): array
    {
        $isHome = in_array($ic->visited_team_id, $ourTeamIds);
        $ourTeam = $isHome ? $ic->visitedTeam : $ic->visitingTeam;
        $opponentTeam = $isHome ? $ic->visitingTeam : $ic->visitedTeam;

        $categoryRaw = $ourTeam?->league?->category;
        $categoryValue = is_string($categoryRaw) ? $categoryRaw : ($categoryRaw?->value ?? '');

        [$categoryLabel, $categorySort] = match ($categoryValue) {
            'MEN'      => ['Hommes', 1],
            'VETERANS' => ['Vétérans', 2],
            'WOMEN'    => ['Dames', 3],
            default    => ['—', 99],
        };

        return [
            'id'             => $ic->id,
            'our_team_id'    => $ourTeam?->id,
            'our_team_name'  => $ourTeam?->name ?? '—',
            'category_label' => $categoryLabel,
            'category_sort'  => $categorySort,
            'week'           => $ic->week_number,
            'date'           => $ic->start_date_time->format('d/m/Y'),
            'time'           => $ic->start_date_time->format('H:i'),
            'is_home'        => $isHome,
            'opponent'       => trim(($opponentTeam?->club?->name ?? '') . ' ' . ($opponentTeam?->name ?? '')) ?: '—',
            'address'        => $ic->address ?? '—',
            'division'       => $ic->league?->division ?? '',
        ];
    }

    private function syncMatchResult(Team $ourTeam, Team $opponentTeam, Interclub $interclub, bool $isHome): void
    {
        $opponentName = trim(($opponentTeam->club?->name ?? '') . ' ' . ($opponentTeam->name ?? ''));

        MatchResult::firstOrCreate(
            [
                'team_id' => $ourTeam->id,
                'season_id' => $this->seasonId,
                'match_date' => $interclub->start_date_time->toDateString(),
            ],
            [
                'week_number' => $interclub->week_number,
                'is_home' => $isHome,
                'opponent_name' => $opponentName,
                'score' => null,
                'result' => null,
                'is_bye' => false,
            ]
        );
    }

    private function totalPlayersByCategory(?string $category): int
    {
        return match ($category) {
            'MEN' => 4,
            default => 3,
        };
    }
};
