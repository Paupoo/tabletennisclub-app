<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Models\Tournament;
use Livewire\Component;

class RegisteredPlayers extends Component
{
    public Tournament $tournament;

    protected $listeners = ['playerRegistered' => '$refresh'];

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
}
