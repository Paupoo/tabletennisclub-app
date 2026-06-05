<?php

declare(strict_types=1);

namespace App\Livewire\Tournament;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class PlayerRegistration extends Component
{
    public int $highlightedIndex = -1;

    public string $searchQuery = '';

    public ?int $selectedPlayerId = null;

    public bool $showDropdown = false;

    public bool $showModal = false;

    public Tournament $tournament;

    protected array $messages = [
        'selectedPlayerId.required' => 'Vous devez sélectionner un joueur.',
        'selectedPlayerId.exists' => 'Le joueur sélectionné n\'existe pas.',
    ];

    protected array $rules = [
        'selectedPlayerId' => 'required|exists:users,id',
    ];

    private TournamentService $tournamentService;

    public function boot(TournamentService $tournamentService): void
    {
        $this->tournamentService = $tournamentService;
    }

    public function clearSelection(): void
    {
        $this->selectedPlayerId = null;
        $this->searchQuery = '';
        $this->showDropdown = false;
        $this->highlightedIndex = -1;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function getFilteredPlayers(): Collection
    {
        $query = trim($this->searchQuery);
        if (empty($query)) {
            return collect();
        }

        return User::unregisteredUsers($this->tournament)
            ->where(function (Builder $queryBuilder) use ($query): void {
                // Utilise le scope search + email
                $queryBuilder->search($query)
                    ->orWhere('email', 'like', '%' . $query . '%');
            })
            ->limit(10)
            ->get();
    }

    public function getSelectedPlayer(): ?User
    {
        if (! $this->selectedPlayerId) {
            return null;
        }

        return User::find($this->selectedPlayerId);
    }

    public function mount(Tournament $tournament): void
    {
        $this->tournament = $tournament;
    }

    public function openModal(): void
    {
        $this->showModal = true;
        $this->resetForm();
    }

    public function registerPlayer(): void
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

    public function render(): View
    {
        return view('livewire.tournament.player-registration', [
            'filteredPlayers' => $this->getFilteredPlayers(),
            'selectedPlayer' => $this->getSelectedPlayer(),
        ]);
    }

    public function resetForm(): void
    {
        $this->searchQuery = '';
        $this->selectedPlayerId = null;
        $this->highlightedIndex = -1;
        $this->showDropdown = false;
        $this->resetErrorBag();
    }

    public function selectPlayer(int $playerId): void
    {
        $player = $this->getFilteredPlayers()->firstWhere('id', $playerId);

        if ($player) {
            $this->selectedPlayerId = $playerId;
            $this->searchQuery = $player->first_name . ' ' . $player->last_name;
            $this->showDropdown = false;
            $this->highlightedIndex = -1;
        }
    }

    public function updatedSearchQuery(): void
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
