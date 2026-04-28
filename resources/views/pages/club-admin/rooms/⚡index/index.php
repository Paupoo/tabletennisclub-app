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
        // To do => revisit once events are correctly remodeled. I want all events in the upcoming 2 weeks, ordered by date, to be populated in the UI.
        $start = now();
        $end = (clone $start)->addWeeks(2);

        $this->rooms = Room::with([
            'trainings' => fn ($query) => $query
                ->whereBetween('start', [$start, $end]),

            'interclubs' => fn ($query) => $query
                ->whereBetween('start_date_time', [$start, $end]),

            'tournaments' => fn ($query) => $query
                ->whereBetween('start_date', [$start, $end]),
        ])
        ->get()
        ->map(function ($room) {
            $room->upcoming_events = collect()
                ->merge($room->trainings->map(fn ($item) => [
                    'type' => 'training',
                    'date' => $item->start,
                    'model' => $item,
                ]))
                ->merge($room->interclubs->map(fn ($item) => [
                    'type' => 'interclub',
                    'date' => $item->start_date_time,
                    'model' => $item,
                ]))
                ->merge($room->tournaments->map(fn ($item) => [
                    'type' => 'tournament',
                    'date' => $item->start_date,
                    'model' => $item,
                ]))
                ->sortBy('date')
                ->values();

            return $room;
        });
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