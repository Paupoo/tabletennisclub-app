<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Rooms;

use App\Models\Room;
use Illuminate\Support\Collection;
use Livewire\Component;

class RoomsIndex extends Component
{
    public string $building = '';

    public Collection $buildings;

    public string $search = '';

    public function mount(): void
    {
        $this->buildings = Room::distinct()
            ->pluck('building_name')
            ->sort()
            ->values();
    }

    public function render()
    {
        $rooms = Room::search($this->search)
            ->when($this->building !== '', function ($query): void {
                $query->where('building_name', $this->building);
            })
            ->get();

        return view('livewire.admin.rooms.rooms-index', [
            'rooms' => $rooms,
        ]);
    }
}
