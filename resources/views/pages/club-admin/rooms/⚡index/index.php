<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    public bool $deleteRoomModal = false;

    public ?int $deletingRoomId = null;

    // ── Actions ───────────────────────────────────────────────────────────────

    public function confirmDeleteRoom(int $id): void
    {
        $this->deletingRoomId = $id;
        $this->deleteRoomModal = true;
    }

    public function delete(Room $room): void
    {
        $this->authorize('delete', $room);

        $hasRelatedRecords = $room->tables()->exists()
            || $room->trainings()->exists()
            || $room->trainingPacks()->exists()
            || $room->interclubs()->exists()
            || $room->tournaments()->exists();

        if ($hasRelatedRecords) {
            $this->error(__('This room cannot be deleted because it has linked tables, trainings, or events.'));

            return;
        }

        $room->delete();
        unset($this->rooms);
        $this->success(__('The room :name has been deleted.', ['name' => $room->name]));
    }

    public function deleteRoom(): void
    {
        if ($this->deletingRoomId) {
            $this->delete(Room::findOrFail($this->deletingRoomId));
        }
        $this->deleteRoomModal = false;
        $this->deletingRoomId = null;
    }

    public function render(): View
    {
        return $this->view();
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /** @return Collection<int, Room> */
    #[Computed]
    public function rooms(): Collection
    {
        $start = now();
        $end = (clone $start)->addWeeks(2);

        return Room::withCount('tables')
            ->withCount([
                'trainings' => fn ($query) => $query->whereBetween('start', [$start, $end]),
                'interclubs' => fn ($query) => $query->whereBetween('start_date_time', [$start, $end]),
                'tournaments' => fn ($query) => $query
                    ->onTheCalendar()
                    ->whereBetween('start_date', [$start, $end]),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Tables with no room. There are none today, but `room_id` is nullable, so
     * without this they would be reachable from nowhere once the tables list
     * is gone.
     *
     * @return Collection<int, Table>
     */
    #[Computed]
    public function unassignedTables(): Collection
    {
        return Table::whereNull('room_id')->orderBy('name')->get();
    }

    public function with(): array
    {
        return [
            'rooms' => $this->rooms,
            'unassignedTables' => $this->unassignedTables,
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    // ── Render ────────────────────────────────────────────────────────────────

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Rooms'));
    }
};
