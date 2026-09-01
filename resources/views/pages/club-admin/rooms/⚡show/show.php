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

    public bool $deleteModal = false;

    public Room $room;

    public ?int $tableId = null;

    public bool $unlinkModal = false;

    public function confirmDelete(int $id): void
    {
        $this->tableId = $id;
        $this->deleteModal = true;
    }

    public function confirmUnlink(int $id): void
    {
        $this->tableId = $id;
        $this->unlinkModal = true;
    }

    public function delete(): void
    {
        $table = Table::findOrFail($this->tableId);
        $this->authorize('delete', $table);

        $table->delete();
        $this->reset('deleteModal', 'tableId');
        unset($this->tables);
        $this->success(__('The table has been deleted.'));
    }

    public function mount(Room $room): void
    {
        $this->room = $room;
    }

    public function render(): View
    {
        return $this->view();
    }

    /** @return Collection<int, Table> */
    #[Computed]
    public function tables(): Collection
    {
        return $this->room->tables()->orderBy('name')->get();
    }

    public function unlink(): void
    {
        $table = Table::findOrFail($this->tableId);
        $this->authorize('update', $table);

        $table->room()->disassociate()->save();
        $this->reset('unlinkModal', 'tableId');
        unset($this->tables);
        $this->success(__('The table has been unlinked from the room.'));
    }

    public function with(): array
    {
        $start = now();
        $end = (clone $start)->addWeeks(2);

        $this->room->load([
            'trainings' => fn ($query) => $query->with('trainer')->whereBetween('start', [$start, $end]),
            'interclubs' => fn ($query) => $query->whereBetween('start_date_time', [$start, $end]),
            'tournaments' => fn ($query) => $query
                ->onTheCalendar()
                ->whereBetween('start_date', [$start, $end]),
        ]);

        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'headers' => [
                ['key' => 'name', 'label' => __('Name'), 'class' => 'w-1/4'],
                ['key' => 'equipment', 'label' => __('Brand'), 'class' => 'w-1/3'],
                ['key' => 'state', 'label' => __('State'), 'class' => 'w-1/4'],
                ['key' => 'actions', 'label' => '', 'class' => 'w-20'],
            ],
            'tables' => $this->tables,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->rooms()
            ->current($this->room->name);
    }
};
