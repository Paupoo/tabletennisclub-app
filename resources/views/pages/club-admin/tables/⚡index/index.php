<?php

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

    public function with(): array
    {
        // 1. On récupère les tables depuis la BDD à chaque rendu
        // pour que la recherche et le tri soient dynamiques
        $tables = Table::query()
            ->with('room')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('state', 'like', '%' . $this->search . '%');
            })
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->get();

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
            'groupedTables' => $tables->groupBy(fn ($table) => $table->room?->name ?? __('Not Assigned'))->sortKeys(),
        ];
    }

    public function render(): View
    {
        return $this->view();
    }
};