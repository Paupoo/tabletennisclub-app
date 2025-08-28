<?php

namespace App\Livewire\Admin\Spams;

use App\Models\Spam;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    // Propriétés de recherche et filtrage
    public string $perPage = '25';
    public string $search = '';
    public array $filters = [
        'period' => '',
        'userAgentType' => '',
        'specificIp' => '',
    ];

    // Sélection et actions
    public array $selectedItems = [];
    public bool $selectAll = false;

    // Interface
    public bool $showFilters = false;

    // Query string pour persistance
    protected $queryString = [
        'search' => ['except' => ''],
        'filters' => ['except' => []],
    ];

    public function mount(): void
    {
        // Réinitialiser les filtres vides
        $this->filters = array_filter($this->filters);
    }

    public function render()
    {
        $spamsQuery = $this->getSpamsQuery();
        
        return view('livewire.admin.spams.index', [
            'spams' => $spamsQuery->paginate($this->perPage),
            'stats' => $this->getStats(),
            'totalResults' => $spamsQuery->count(),
        ]);
    }

    /**
     * Construction de la requête avec filtres
     */
    private function getSpamsQuery(): Builder
    {
        $query = Spam::query()->orderBy('created_at', 'desc');

        // Recherche textuelle
        if (!empty($this->search)) {
            $searchTerm = '%' . $this->search . '%';
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('ip', 'like', $searchTerm)
                  ->orWhere('user_agent', 'like', $searchTerm)
                  ->orWhereRaw("JSON_SEARCH(inputs, 'all', ?) IS NOT NULL", [$this->search]);
            });
        }

        // Filtres avancés
        if (!empty($this->filters['period'])) {
            match ($this->filters['period']) {
                'today' => $query->whereDate('created_at', today()),
                'week' => $query->where('created_at', '>=', now()->subWeek()),
                'month' => $query->where('created_at', '>=', now()->subMonth()),
                default => null,
            };
        }

        if (!empty($this->filters['userAgentType'])) {
            $query->where('user_agent', 'like', match ($this->filters['userAgentType']) {
                'bot' => '%bot%',
                'curl' => '%curl%',
                'browser' => '%Mozilla%',
                default => '%',
            });
        }

        if (!empty($this->filters['specificIp'])) {
            $query->where('ip', $this->filters['specificIp']);
        }

        return $query;
    }

    /**
     * Calcul des statistiques
     */
    private function getStats(): Collection
    {
        $baseQuery = Spam::query();
        
        return collect([
            'totalSpams' => $baseQuery->count(),
            'todaySpams' => $baseQuery->whereDate('created_at', today())->count(),
            'uniqueIps' => $baseQuery->distinct('ip')->count('ip'),
            'blockedIps' => 0, // À implémenter avec un modèle BlockedIp si nécessaire
        ]);
    }

    /**
     * Mise à jour de la recherche
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    /**
     * Mise à jour des filtres
     */
    public function updatedFilters(): void
    {
        $this->resetPage();
        $this->resetSelection();
    }

    /**
     * Basculer l'affichage des filtres
     */
    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    /**
     * Réinitialiser tous les filtres
     */
    public function clearFilters(): void
    {
        $this->search = '';
        $this->filters = [
            'period' => '',
            'userAgentType' => '',
            'specificIp' => '',
        ];
        $this->resetPage();
        $this->resetSelection();
    }

    /**
     * Sélection/désélection de tous les éléments
     */
    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedItems = $this->getSpamsQuery()
                ->paginate(15)
                ->pluck('id')
                ->toArray();
        } else {
            $this->selectedItems = [];
        }
    }

    /**
     * Suppression d'un spam individuel
     */
    public function deleteSpam(int $spamId): void
    {
        try {
            $spam = Spam::findOrFail($spamId);
            $spam->delete();
            
            // Retirer de la sélection si présent
            $this->selectedItems = array_filter(
                $this->selectedItems, 
                fn($id) => $id !== $spamId
            );
            
            $this->dispatch('spam-deleted', [
                'message' => 'Spam supprimé avec succès.',
                'type' => 'success'
            ]);
            
        } catch (\Exception $e) {
            $this->dispatch('spam-error', [
                'message' => 'Erreur lors de la suppression du spam.',
                'type' => 'error'
            ]);
        }
    }

    /**
     * Suppression en lot
     */
    public function bulkDelete(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        try {
            $deletedCount = Spam::whereIn('id', $this->selectedItems)->delete();
            
            $this->resetSelection();
            
            $this->dispatch('spam-bulk-deleted', [
                'message' => "{$deletedCount} spam(s) supprimé(s) avec succès.",
                'type' => 'success'
            ]);
            
        } catch (\Exception $e) {
            $this->dispatch('spam-error', [
                'message' => 'Erreur lors de la suppression en lot.',
                'type' => 'error'
            ]);
        }
    }

    /**
     * Blocage d'une IP (à adapter selon ton système)
     */
    public function blockIp(string $ip): void
    {
        try {
            // Ici tu peux implémenter ta logique de blocage
            // Par exemple, créer un modèle BlockedIp ou ajouter à un fichier .htaccess
            
            // Exemple simple : log de l'action
            logger()->info("IP blocked by admin: {$ip}");
            
            $this->dispatch('ip-blocked', [
                'message' => "IP {$ip} ajoutée à la liste de blocage.",
                'type' => 'success'
            ]);
            
        } catch (\Exception $e) {
            $this->dispatch('spam-error', [
                'message' => 'Erreur lors du blocage de l\'IP.',
                'type' => 'error'
            ]);
        }
    }

    /**
     * Export des données (à implémenter selon tes besoins)
     */
    public function exportData(): void
    {
        try {
            // Tu peux utiliser Laravel Excel ou une solution custom
            // Pour l'instant, on log juste l'action
            
            $count = $this->getSpamsQuery()->count();
            logger()->info("Spam export requested: {$count} records");
            
            $this->dispatch('export-started', [
                'message' => "Export de {$count} enregistrement(s) en cours...",
                'type' => 'info'
            ]);
            
        } catch (\Exception $e) {
            $this->dispatch('spam-error', [
                'message' => 'Erreur lors de l\'export.',
                'type' => 'error'
            ]);
        }
    }

    /**
     * Vérifier s'il y a des filtres actifs
     */
    public function hasActiveFilters(): bool
    {
        return !empty($this->search) || 
               !empty(array_filter($this->filters));
    }

    /**
     * Réinitialiser la sélection
     */
    private function resetSelection(): void
    {
        $this->selectedItems = [];
        $this->selectAll = false;
    }

    /**
     * Formatage pour l'affichage
     */
    public function truncateText(string $text, int $length = 80): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }

    /**
     * Formater les inputs JSON pour l'affichage
     */
    public function formatInputs(?array $inputs): string
    {
        if (!$inputs) {
            return 'Aucune donnée';
        }

        $formatted = [];
        foreach ($inputs as $key => $value) {
            $truncatedValue = $this->truncateText((string)$value, 30);
            $formatted[] = "{$key}: {$truncatedValue}";
        }

        return implode(' | ', $formatted);
    }
}
