<?php

namespace App\Livewire\Public;

use App\Models\User;
use App\Services\TournamentService;
use Auth;
use Livewire\Component;
use View;

class TournamentCard extends Component
{
    public $tournament;
    public $showDetails = false;
    public $isRegistered = false;
    protected User $user;

    public function mount($tournament)
    {
        $this->tournament = $tournament;

        $this->user = Auth::user();

        if ($this->tournament->users->contains($this->user)) {
            $this->isRegistered = true;
        }
    }

    public function toggleDetails()
    {
        $this->showDetails = !$this->showDetails;
    }

    public function register()
    {
        $this->user = Auth::user();
        try {
            new TournamentService()->registerUser($this->tournament, $this->user);
        } catch (\Throwable $th) {
            session()->flash('error', $th->getMessage());
            return;
        }

        $this->isRegistered = true;
        session()->flash('message', 'Successfully registered for ' . $this->tournament['name']);
    }

    public function viewDetails()
    {
        return redirect()->route('tournaments.show' , $this->tournament);
    }

    public function render()
    {
        return view('livewire.public.tournament-card');
    }
}
