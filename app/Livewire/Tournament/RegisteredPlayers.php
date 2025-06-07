<?php

namespace App\Livewire\Tournament;

use App\Models\Tournament;
use Livewire\Component;

class RegisteredPlayers extends Component
{
    public Tournament $tournament;

    public function mount(Tournament $tournament)
    {
        $this->tournament = $tournament;
    }

    public function render()
    {
        return view('livewire.tournament.registered-players', [
            'users' => $this->tournament->users()->paginate(),
        ]);
    }

    protected $listeners = ['playerRegistered' => '$refresh'];
}
