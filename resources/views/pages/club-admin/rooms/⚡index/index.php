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



    public function mount(): void
    {
        // On initialise ici pour éviter l'erreur "Constant expression"
        $this->rooms = Room::with('trainings')->get();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Rooms'))
                ->toArray(),
            ''
        ];
    }

    public function delete(Room $room): void
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