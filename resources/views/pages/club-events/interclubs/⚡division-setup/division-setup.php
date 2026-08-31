<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\DivisionSetup;

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast;

    public bool $addModal = false;

    /** @var array<int, array{id: int, name: string, street: string}> */
    public array $clubOptions = [];

    public bool $deleteModal = false;

    public ?int $deletingTeamId = null;

    public ?int $formClubId = null;

    public string $formClubName = '';

    public string $formClubStreet = '';

    public bool $formNewClub = false;

    public string $formTeamLetter = '';

    public ?int $seasonId = null;

    public ?int $selectedLeagueId = null;

    public function addParticipant(): void
    {
        $rules = ['formTeamLetter' => ['required', 'string', 'size:1', 'alpha']];

        if ($this->formClubId) {
            $rules['formClubId'] = ['integer', 'exists:clubs,id'];
        } else {
            $rules['formClubName'] = ['required', 'string', 'max:100'];
            $rules['formClubStreet'] = ['nullable', 'string', 'max:255'];
        }

        $this->validate($rules);

        $club = $this->formClubId
            ? Club::findOrFail($this->formClubId)
            : Club::firstOrCreate(
                ['name' => trim($this->formClubName)],
                [
                    'licence' => 'OPP-' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $this->formClubName), 0, 6)),
                    'street' => $this->formClubStreet ?: null,
                ]
            );

        $already = Team::where([
            'name' => strtoupper($this->formTeamLetter),
            'season_id' => $this->seasonId,
            'league_id' => $this->selectedLeagueId,
            'club_id' => $club->id,
        ])->exists();

        if ($already) {
            $this->error(__('This team already exists in this division.'));

            return;
        }

        Team::create([
            'name' => strtoupper($this->formTeamLetter),
            'season_id' => $this->seasonId,
            'league_id' => $this->selectedLeagueId,
            'club_id' => $club->id,
        ]);

        $this->addModal = false;
        $this->success(__('Participant added.'));
    }

    public function clearFilters(): void
    {
        $this->seasonId = Season::current()?->id;
    }

    public function confirmDelete(int $teamId): void
    {
        $this->deletingTeamId = $teamId;
        $this->deleteModal = true;
    }

    public function deleteParticipant(): void
    {
        if (! $this->deletingTeamId) {
            $this->deleteModal = false;

            return;
        }

        $hasMatches = Interclub::where('visited_team_id', $this->deletingTeamId)
            ->orWhere('visiting_team_id', $this->deletingTeamId)
            ->exists();

        if ($hasMatches) {
            $this->error(__('Cannot delete: this team has matches linked to it.'));
            $this->deleteModal = false;
            $this->deletingTeamId = null;

            return;
        }

        Team::find($this->deletingTeamId)?->delete();

        $this->deleteModal = false;
        $this->deletingTeamId = null;
        $this->success(__('Participant removed.'));
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->seasonId !== Season::current()?->id) {
            $seasonName = Season::find($this->seasonId)?->name ?? __('All seasons');
            $chips[] = ['key' => 'seasonId', 'label' => __('Season') . ': ' . $seasonName];
        }

        return $chips;
    }

    public function mount(): void
    {
        Gate::authorize(Permission::LeaguesManage->value);

        $this->seasonId = Season::current()?->id;
    }

    public function openAddModal(): void
    {
        $this->resetErrorBag();
        $this->formClubId = null;
        $this->formClubName = '';
        $this->formClubStreet = '';
        $this->formNewClub = false;
        $this->formTeamLetter = '';
        $this->search();
        $this->addModal = true;
    }

    public function removeFilter(string $key): void
    {
        if ($key === 'seasonId') {
            $this->seasonId = Season::current()?->id;

            return;
        }

        $this->reset([$key]);
    }

    public function render(): View
    {
        return $this->view()->title(__('Division Setup'));
    }

    /**
     * Feeds the searchable club picker (maryUI x-choices calls this method).
     * Only opponent clubs already encoded are listed; the selected one is kept
     * in the list so the choice stays visible once picked.
     */
    public function search(string $value = ''): void
    {
        $selected = $this->formClubId
            ? Club::whereKey($this->formClubId)->get(['id', 'name', 'street'])
            : new EloquentCollection;

        $this->clubOptions = Club::query()
            ->otherClubs()
            ->when($value !== '', fn (Builder $q) => $q->where('name', 'like', '%' . $value . '%'))
            ->orderBy('name')
            ->take(20)
            ->get(['id', 'name', 'street'])
            ->merge($selected)
            ->unique('id')
            ->sortBy('name')
            ->map(fn (Club $club): array => [
                'id' => $club->id,
                'name' => $club->name,
                'street' => $club->street ?? '',
            ])
            ->values()
            ->all();
    }

    public function selectLeague(int $leagueId): void
    {
        $this->selectedLeagueId = $leagueId;
    }

    /**
     * Switch between picking an already encoded club and typing a brand new one.
     */
    public function toggleNewClub(): void
    {
        $this->resetErrorBag();
        $this->formNewClub = ! $this->formNewClub;
        $this->formClubId = null;
        $this->formClubName = '';
        $this->formClubStreet = '';
    }

    public function with(): array
    {
        $leagues = League::whereHas('teams', fn ($q) => $q->whereHas('club', fn ($q) => $q->where('is_own_club', true))
            ->where('season_id', $this->seasonId)
        )
            ->where('season_id', $this->seasonId)
            ->with(['teams.club'])
            ->get();

        $participants = $this->selectedLeagueId
            ? Team::with('club')
                ->notInClub()
                ->where('league_id', $this->selectedLeagueId)
                ->where('season_id', $this->seasonId)
                ->orderBy('name')
                ->get()
            : collect();

        $categoryMeta = [
            'MEN' => ['label' => 'Hommes',   'bg' => 'bg-blue-50',  'border' => 'border-blue-200',  'text' => 'text-blue-700',  'dot' => 'bg-blue-500'],
            'VETERANS' => ['label' => 'Vétérans', 'bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500'],
            'WOMEN' => ['label' => 'Dames',    'bg' => 'bg-pink-50',  'border' => 'border-pink-200',  'text' => 'text-pink-700',  'dot' => 'bg-pink-500'],
        ];

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add(__('Interclubs'), '#')
                ->current(__('Division Setup'))
                ->toArray(),
            'filterChips' => $this->filterChips,
            'seasons' => Season::orderBy('start_at')->get(),
            'leagues' => $leagues,
            'participants' => $participants,
            'categoryMeta' => $categoryMeta,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Division Setup'));
    }
};
