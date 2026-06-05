<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Livewire\Component;

class TournamentCard extends Component
{
    public bool $isRegistered = false;

    public bool $showDetails = false;

    public Tournament $tournament;

    protected User $user;

    public function mount(Tournament $tournament): void
    {
        $this->tournament = $tournament;

        if (Auth::user() !== null) {
            $this->user = Auth::user();

            if ($this->tournament->users->contains($this->user)) {
                $this->isRegistered = true;
            }
        }

    }

    public function register(): void
    {
        $this->user = Auth::user();
        try {
            (new TournamentService)->registerUser($this->tournament, $this->user);
        } catch (\Throwable $th) {
            session()->flash('error', $th->getMessage());

            return;
        }

        $this->isRegistered = true;
        session()->flash('message', 'Successfully registered for ' . $this->tournament['name']);
    }

    public function render(): View
    {
        return view('livewire.public.tournament-card');
    }

    public function toggleDetails(): void
    {
        $this->showDetails = ! $this->showDetails;
    }

    public function viewDetails(): RedirectResponse
    {
        return redirect()->route('tournaments.show', $this->tournament);
    }
}
