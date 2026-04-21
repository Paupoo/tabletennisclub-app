<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Club\Table;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public bool $deleteModal = false;
    public bool $unlinkModal = false;
    
    public Table $tableToDelete;
    public Table $tableToUnlink;

    public string $search = '';

    public array $selected = [];

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public function render(): View
    {
        return $this->view();
    }

    public function confirmUnlink(Table $table): void
    {
        $this->tableToUnlink = $table;
        $this->unlinkModal = true;
    }

    public function unlink(): void
    {
        try {
            $this->authorize('edit', $this->tableToUnlink);
            $this->tableToUnlink->room()->disassociate()->save();
            $this->unlinkModal = false;

            $this->success(__('The table has been unlinked from the room.'));
        } catch (\Exception $e) {
            $this->error('Erreur : ' . $e->getMessage());
        }
    }
    
    public function confirmDelete(Table $table)
    {
        $this->tableToDelete = $table;
        $this->deleteModal = true;
    }
    
    public function delete(): void
    {
        try {
            $this->authorize('delete', $this->tableToDelete);
            $this->tableToDelete->delete();
            $this->deleteModal = false;
            $this->success(__('The table has been deleted.'));
        } catch (\Exception $e) {
            $this->error('Erreur : ' . $e->getMessage());
        }
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
