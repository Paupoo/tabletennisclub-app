<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Domains\Competitions\Tournament\Models\Tournament;
use Illuminate\View\View;
use Livewire\Component;

class RegisteredPlayers extends Component
{
    public Tournament $tournament;

    protected $listeners = ['playerRegistered' => '$refresh'];

    public function mount(Tournament $tournament): void
    {
        $this->tournament = $tournament;
    }

    public function render(): View
    {
        return view('livewire.tournament.registered-players', [
            'users' => $this->tournament->users()->paginate(),
        ]);
    }
}
