<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentService;
use Livewire\Component;

class PlayerRegistration extends Component
{
    public $highlightedIndex = -1;

    public $searchQuery = '';

    public $selectedPlayerId = null;

    public $showDropdown = false;

    public $showModal = false;

    public Tournament $tournament;

    protected $messages = [
        'selectedPlayerId.required' => 'Vous devez sélectionner un joueur.',
        'selectedPlayerId.exists' => 'Le joueur sélectionné n\'existe pas.',
    ];

    protected $rules = [
        'selectedPlayerId' => 'required|exists:users,id',
    ];

    private TournamentService $tournamentService;

    public function boot(TournamentService $tournamentService)
    {
        $this->tournamentService = $tournamentService;
    }

    public function clearSelection()
    {
        $this->selectedPlayerId = null;
        $this->searchQuery = '';
        $this->showDropdown = false;
        $this->highlightedIndex = -1;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function getFilteredPlayers()
    {
        $query = trim($this->searchQuery);
        if (empty($query)) {
            return collect();
        }

        return User::unregisteredUsers($this->tournament)
            ->where(function ($queryBuilder) use ($query) {
                // Utilise le scope search + email
                $queryBuilder->search($query)
                    ->orWhere('email', 'like', '%' . $query . '%');
            })
            ->limit(10)
            ->get();
    }

    public function getSelectedPlayer()
    {
        if (! $this->selectedPlayerId) {
            return null;
        }

        return User::find($this->selectedPlayerId);
    }

    public function mount(Tournament $tournament)
    {
        $this->tournament = $tournament;
    }

    public function openModal()
    {
        $this->showModal = true;
        $this->resetForm();
    }

    public function registerPlayer()
    {
        // $this->validate();

        if ($this->tournamentService->isFull($this->tournament)) {
            $this->adderror('selectedPlayerId', 'Sorry, the tournament is full, you cannot register more players.');

            return;
        }

        // Vérifier si le joueur n'est pas déjà inscrit
        if ($this->tournament->users()->where('user_id', $this->selectedPlayerId)->exists()) {
            $this->addError('selectedPlayerId', 'Ce joueur est déjà inscrit au tournoi.');

            return;
        }

        // Inscrire le joueur
        $this->tournament->users()->attach($this->selectedPlayerId);

        $this->tournamentService->countRegisteredUsers($this->tournament);

        // Message de succès
        session()->flash('message', 'Joueur inscrit avec succès !');

        // Fermer le modal et réinitialiser
        $this->closeModal();

        // Émettre un événement pour rafraîchir la liste des joueurs si nécessaire
        $this->dispatch('playerRegistered');
    }

    public function render()
    {
        return view('livewire.player-registration', [
            'filteredPlayers' => $this->getFilteredPlayers(),
            'selectedPlayer' => $this->getSelectedPlayer(),
        ]);
    }

    public function resetForm()
    {
        $this->searchQuery = '';
        $this->selectedPlayerId = null;
        $this->highlightedIndex = -1;
        $this->showDropdown = false;
        $this->resetErrorBag();
    }

    public function selectPlayer($playerId)
    {
        $player = $this->getFilteredPlayers()->firstWhere('id', $playerId);

        if ($player) {
            $this->selectedPlayerId = $playerId;
            $this->searchQuery = $player->first_name . ' ' . $player->last_name;
            $this->showDropdown = false;
            $this->highlightedIndex = -1;
        }
    }

    public function updatedSearchQuery()
    {
        $this->showDropdown = ! empty(trim($this->searchQuery));
        $this->highlightedIndex = -1;

        // Si on efface la recherche, on désélectionne le joueur
        if (empty(trim($this->searchQuery))) {
            $this->selectedPlayerId = null;
        }
    }

    /**
     * Check if there the tournament has reached its maximum amount of players
     */
    private function IsFull(Tournament $tournament): bool
    {
        return $tournament->total_users >= $tournament->max_users;
    }
}
