<?php

namespace App\Livewire;

use App\Models\Tournament;
use App\Models\User;
use Livewire\Component;
use Illuminate\Validation\Rule;

class PlayerRegistration extends Component
{
    public Tournament $tournament;
    public $showModal = false;
    public $searchQuery = '';
    public $selectedPlayerId = null;
    public $highlightedIndex = -1;
    public $showDropdown = false;

    protected $rules = [
        'selectedPlayerId' => 'required|exists:users,id',
    ];

    protected $messages = [
        'selectedPlayerId.required' => 'Vous devez sélectionner un joueur.',
        'selectedPlayerId.exists' => 'Le joueur sélectionné n\'existe pas.',
    ];

    public function mount(Tournament $tournament)
    {
        $this->tournament = $tournament;
    }

    public function openModal()
    {
        $this->showModal = true;
        $this->resetForm();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->searchQuery = '';
        $this->selectedPlayerId = null;
        $this->highlightedIndex = -1;
        $this->showDropdown = false;
        $this->resetErrorBag();
    }

    public function updatedSearchQuery()
    {
        $this->showDropdown = !empty(trim($this->searchQuery));
        $this->highlightedIndex = -1;
        
        // Si on efface la recherche, on désélectionne le joueur
        if (empty(trim($this->searchQuery))) {
            $this->selectedPlayerId = null;
        }
    }

    public function selectPlayer($playerId)
    {
        $player = $this->getFilteredPlayers()->firstWhere('id', $playerId);
        
        if ($player) {
            $this->selectedPlayerId = $playerId;
            $this->searchQuery = $player->name;
            $this->showDropdown = false;
            $this->highlightedIndex = -1;
        }
    }

    public function clearSelection()
    {
        $this->selectedPlayerId = null;
        $this->searchQuery = '';
        $this->showDropdown = false;
        $this->highlightedIndex = -1;
    }

    public function registerPlayer()
    {
        $this->validate();

        // Vérifier si le joueur n'est pas déjà inscrit
        if ($this->tournament->users()->where('user_id', $this->selectedPlayerId)->exists()) {
            $this->addError('selectedPlayerId', 'Ce joueur est déjà inscrit au tournoi.');
            return;
        }

        // Inscrire le joueur
        $this->tournament->users()->attach($this->selectedPlayerId);

        // Message de succès
        session()->flash('message', 'Joueur inscrit avec succès !');

        // Fermer le modal et réinitialiser
        $this->closeModal();

        // Émettre un événement pour rafraîchir la liste des joueurs si nécessaire
        $this->dispatch('playerRegistered');
    }

    public function getFilteredPlayers()
    {
        $query = trim($this->searchQuery);
        
        if (empty($query)) {
            return collect();
        }

        // Récupérer les joueurs non encore inscrits au tournoi
        $registeredPlayerIds = $this->tournament->users()->pluck('user_id')->toArray();

        return User::where(function ($queryBuilder) use ($query) {
            $queryBuilder->where('name', 'like', '%' . $query . '%')
                        ->orWhere('email', 'like', '%' . $query . '%');
        })
        ->whereNotIn('id', $registeredPlayerIds)
        ->limit(10)
        ->get();
    }

    public function getSelectedPlayer()
    {
        if (!$this->selectedPlayerId) {
            return null;
        }

        return User::find($this->selectedPlayerId);
    }

    public function render()
    {
        return view('livewire.player-registration', [
            'filteredPlayers' => $this->getFilteredPlayers(),
            'selectedPlayer' => $this->getSelectedPlayer(),
        ]);
    }
}