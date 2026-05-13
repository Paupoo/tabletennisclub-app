<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams\Edit;

use App\Enums\TeamName;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\Team;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    #[Locked]
    public int $teamId;

    public string $name        = '';
    public ?int $captainId     = null;
    public array $memberIds    = [];
    public string $memberSearch = '';

    public function mount(Team $team): void
    {
        $this->teamId    = $team->id;
        $this->name      = $team->name;
        $this->captainId = $team->captain_id;
        $this->memberIds = $team->users->pluck('id')->toArray();
    }

    public function render(): View
    {
        return $this->view();
    }

    public function save(): void
    {
        $this->validate([
            'name'      => ['required', 'string', 'size:1'],
            'memberIds' => ['array', 'min:1'],
        ], [
            'name.size'       => 'Le nom doit être une seule lettre (A–Z).',
            'memberIds.min'   => 'L\'équipe doit avoir au moins un joueur.',
        ]);

        $team = Team::findOrFail($this->teamId);
        $team->name       = strtoupper($this->name);
        $team->captain_id = $this->captainId;
        $team->save();

        $team->users()->sync($this->memberIds);

        $this->success(
            'Équipe mise à jour',
            redirectTo: route('admin.interclubs.teams.show', $this->teamId)
        );
    }

    public function toggleMember(int $userId): void
    {
        if (in_array($userId, $this->memberIds)) {
            $this->memberIds = array_values(array_filter($this->memberIds, fn ($id) => $id !== $userId));
        } else {
            $this->memberIds[] = $userId;
        }
    }

    public function setCaptain(int $userId): void
    {
        if (! in_array($userId, $this->memberIds)) {
            $this->memberIds[] = $userId;
        }
        $this->captainId = $userId;
    }

    public function removeCaptain(): void
    {
        $this->captainId = null;
    }

    public function with(): array
    {
        $team = Team::with(['league', 'captain', 'users', 'club', 'season'])->findOrFail($this->teamId);

        $competitors = User::where('is_competitor', true)
            ->when($this->memberSearch, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', "%{$this->memberSearch}%")
                ->orWhere('last_name', 'like', "%{$this->memberSearch}%")
            ))
            ->orderBy('force_list')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $teamNameOptions = collect(TeamName::cases())
            ->map(fn ($n) => ['id' => $n->name, 'name' => $n->name]);

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Interclubs', '#')
                ->add('Équipes', route('admin.interclubs.teams'))
                ->add($team->club?->name . ' ' . $team->name, route('admin.interclubs.teams.show', $team->id))
                ->current('Modifier')
                ->toArray(),
            'team'            => $team,
            'competitors'     => $competitors,
            'teamNameOptions' => $teamNameOptions,
        ];
    }
};
