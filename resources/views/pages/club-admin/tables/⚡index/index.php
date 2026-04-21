<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Club\Table;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public string $search = '';

    public array $selected = [];

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public function render(): View
    {
        return $this->view();
    }

    public function unlink(Table $table): void
    {
        $table->room()->disassociate()->save();
        $this->success(__('The table has been unlinked from the room.'));
    }

    public function with(): array
    {
        // 1. Récupération avec les relations
        $tables = Table::query()
            ->with('room')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('state', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->get();

        // 2. Groupement avec accès complet à la room
        $groupedTables = $tables
            ->groupBy(fn ($table) => $table->room_id ?? 'unassigned')
            ->map(fn ($group) => [
                'room' => $group->first()?->room,
                'room_display' => $group->first()?->room?->name ?? __('Not Assigned'),
                'tables' => $group,
            ])
            // Sort logic:
            // 1. All rooms with names come first (sorted A-Z)
            // 2. The 'unassigned' group (where room is null) comes last
            ->sortBy(fn ($group) => [
                is_null($group['room']), // false (0) for rooms, true (1) for unassigned
                $group['room_display'],   // then alphabetically
            ])
            ->values();

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Tables'))
                ->toArray(),

            'headers' => [
                ['key' => 'name', 'label' => __('Name'), 'class' => 'w-1/3'],
                ['key' => 'purchased_on', 'label' => __('Purchased On'), 'class' => 'w-1/3'],
                ['key' => 'is_competition_ready', 'label' => __('Competition Ready'), 'class' => 'w-1/3'],
                ['key' => 'state', 'label' => __('State'), 'class' => 'w-32'],
                ['key' => 'actions', 'label' => '', 'class' => 'w-20'],
            ],

            // 2. La magie opère : On groupe par le nom de la relation "room"
            // Si la table n'a pas de salle (room est null), on la met dans "Non assignée"
            'groupedTables' => $groupedTables,
        ];
    }
};
