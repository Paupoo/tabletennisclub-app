<?php

use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public Collection $rooms;

    // Propriétés de contrôle
    public string $search = '';
    public bool $drawer = false;
    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    // On déclare sans initialiser avec des fonctions
    public array $headers = [];
    public array $tables = [];

    public function mount(): void
    {
        // On initialise ici pour éviter l'erreur "Constant expression"
        $this->rooms = Room::with('tables')->get();
    }

    public function with(): array
    {
        // On applique le tri et le filtre avant d'envoyer à la vue
        $rows = collect($this->tables)
            ->filter(fn($row) => str_contains(strtolower($row['name']), strtolower($this->search)))
            ->sortBy($this->sortBy['column'], SORT_REGULAR, $this->sortBy['direction'] === 'desc')
            ->values()
            ->all();

        return [
            'rows' => $rows,
            'headers' => $this->headers,
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Rooms'))
                ->toArray(),
        ];
    }

    public function destroy(Room $room): void
    {
        $this->authorize('delete', $room);
        $room->delete();

        $this->success(__('The room ' . $room->name . ' has been deleted.'));
    }

    public function render(): View
    {
        return $this->view();
    }
};