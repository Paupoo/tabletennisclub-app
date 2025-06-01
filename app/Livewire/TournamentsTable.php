<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Tournament;
use Livewire\Component;
use Livewire\WithPagination;

class TournamentsTable extends Component
{
    use WithPagination;

    public int $perPage = 10;

    public string $search = '';

    public string $status = '';

    public string $sortByField = '';

    public string $sortDirection = 'desc';

    public function sortBy($field)
    {
        if ($this->sortByField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        }

        $this->sortByField = $field;
    }

    public function render()
    {
        return view('livewire.tournaments-table', [
            'tournaments' => Tournament::search($this->search)
                ->when($this->status !== '', function ($query) {
                    $query->where('status', $this->status);
                })
                ->orderBy('start_date')
                ->paginate($this->perPage),
        ]);
    }
}
