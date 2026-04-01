<?php

use App\Support\Breadcrumb;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public string $step = '1';

    // ──────────────────────────────────────────
    // Étape 1 – Config sportive
    // ──────────────────────────────────────────

    public string $name = '';

    public bool $publicRegistration = false;

    public int $nb_poules = 4;

    public int $nb_qualifies = 2;

    public int $totalSets = 3;

    public int $pool_size = 4; // joueurs par poule

    public $tournamentDate = '2026-05-15';

    public array $selectedRooms = [];

    public array $setOptions = [
        ['id' => 1, 'name' => '1'],
        ['id' => 2, 'name' => '2'],
        ['id' => 3, 'name' => '3'],
        ['id' => 4, 'name' => '4'],
        ['id' => 5, 'name' => '5'],
    ];

    public array $poolSizeOptions = [
        ['id' => 3, 'name' => '3 joueurs'],
        ['id' => 4, 'name' => '4 joueurs'],
        ['id' => 5, 'name' => '5 joueurs'],
        ['id' => 6, 'name' => '6 joueurs'],
    ];

    // ──────────────────────────────────────────
    // Contraintes physiques (simulateur)
    // ──────────────────────────────────────────

    public int $nb_tables = 8;   // tables disponibles

    public int $tournament_minutes = 180; // durée totale en minutes

    public int $logistics_buffer = 3;   // minutes de tampon entre matchs

    // ──────────────────────────────────────────
    // Computed : Format du match
    // ──────────────────────────────────────────

    #[Computed]
    public function bestOfCount(): int
    {
        return ($this->totalSets * 2) - 1;
    }

    /**
     * Durée moyenne estimée d'un match selon le format best-of.
     * Basé sur des moyennes observées en compétition amateur.
     *
     * Pourquoi des estimations fixes ? Parce qu'on n'a pas encore de
     * données réelles. Ces constantes sont à affiner avec l'expérience.
     */
    #[Computed]
    public function avgMatchMinutes(): int
    {
        $durations = [
            1 => 6,
            3 => 12,
            5 => 20,
            7 => 30,
            9 => 40,
        ];

        return ($durations[$this->bestOfCount] ?? 12) + $this->logistics_buffer;
    }

    // ──────────────────────────────────────────
    // Computed : Capacité du tournoi
    // ──────────────────────────────────────────

    /**
     * Combien de matchs peut-on jouer en tout ?
     * C'est le "budget" disponible.
     */
    #[Computed]
    public function totalMatchCapacity(): int
    {
        if ($this->avgMatchMinutes <= 0) {
            return 0;
        }

        // On applique un coefficient de congestion réaliste :
        // en pratique, les tables ne sont jamais utilisées à 100%
        // (joueurs pas là, retards, fin de match décalée).
        $congestion = 0.80;

        return (int) floor(
            ($this->tournament_minutes / $this->avgMatchMinutes)
                * $this->nb_tables
                * $congestion
        );
    }

    // ──────────────────────────────────────────
    // Computed : Phase de poules
    // ──────────────────────────────────────────

    /**
     * Nombre total de joueurs inscrits (calculé depuis les poules).
     */
    #[Computed]
    public function totalPlayers(): int
    {
        return $this->nb_poules * $this->pool_size;
    }

    /**
     * Matchs dans une poule : n(n-1)/2
     * Formule combinatoire : chaque paire de joueurs joue une fois.
     */
    #[Computed]
    public function matchesPerPool(): int
    {
        $n = $this->pool_size;

        return ($n * ($n - 1)) / 2;
    }

    /**
     * Total des matchs de poules.
     */
    #[Computed]
    public function poolMatchesTotal(): int
    {
        return $this->matchesPerPool * $this->nb_poules;
    }

    // ──────────────────────────────────────────
    // Computed : Phase finale
    // ──────────────────────────────────────────

    /**
     * Nombre de qualifiés pour la phase finale.
     */
    #[Computed]
    public function finalistsCount(): int
    {
        return $this->nb_poules * $this->nb_qualifies;
    }

    /**
     * Matchs en élimination directe : N joueurs → N-1 matchs.
     * (chaque match élimine exactement un joueur)
     */
    #[Computed]
    public function bracketMatchesTotal(): int
    {
        $n = $this->finalistsCount;
        if ($n <= 1) {
            return 0;
        }

        return $n - 1;
    }

    // ──────────────────────────────────────────
    // Computed : Bilan global
    // ──────────────────────────────────────────

    #[Computed]
    public function grandTotalMatches(): int
    {
        return $this->poolMatchesTotal + $this->bracketMatchesTotal;
    }

    /**
     * Durée estimée du tournoi en minutes (sans congestion,
     * juste le volume de matchs / capacité parallèle des tables).
     */
    #[Computed]
    public function estimatedMinutes(): int
    {
        if ($this->nb_tables <= 0) {
            return 0;
        }

        // On joue en parallèle sur toutes les tables disponibles.
        // Combien de "vagues" de matchs faut-il ?
        $waves = ceil($this->grandTotalMatches / $this->nb_tables);

        return (int) ($waves * $this->avgMatchMinutes);
    }

    /**
     * Occupation des tables (% du temps total utilisé).
     */
    #[Computed]
    public function tableOccupancyPercent(): int
    {
        if ($this->totalMatchCapacity <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->grandTotalMatches / $this->totalMatchCapacity) * 100));
    }

    /**
     * Nombre de matchs par joueur en moyenne (poules uniquement,
     * un joueur ne joue pas forcément toute la finale).
     */
    #[Computed]
    public function avgMatchesPerPlayer(): float
    {
        if ($this->totalPlayers <= 0) {
            return 0;
        }

        return round($this->grandTotalMatches / $this->totalPlayers, 1);
    }

    /**
     * Est-ce que le tournoi est faisable dans le temps imparti ?
     */
    #[Computed]
    public function isFeasible(): bool
    {
        return $this->grandTotalMatches <= $this->totalMatchCapacity;
    }

    /**
     * Niveau de risque : 'ok', 'warning', 'danger'
     * ok      : < 80% de la capacité utilisée
     * warning : 80–100%
     * danger  : > 100% (impossible en l'état)
     */
    #[Computed]
    public function riskLevel(): string
    {
        $ratio = $this->tableOccupancyPercent;

        if ($ratio > 100) {
            return 'danger';
        }
        if ($ratio >= 80) {
            return 'warning';
        }

        return 'ok';
    }

    // ──────────────────────────────────────────
    // Étape 2 – Invitations
    // ──────────────────────────────────────────

    public string $memberSearch = '';

    public array $selectedMembers = [];

    public string $publicationDate;

    // ──────────────────────────────────────────
    // Données hardcodées (à remplacer par DB)
    // ──────────────────────────────────────────

    public array $rooms = [
        [
            'id' => 1,
            'name' => 'Demeester 0',
            'address' => "Rue de l'invasion 80, 1340 Ottignies",
        ],
        [
            'id' => 2,
            'name' => 'Demeester -1',
            'address' => "Rue de l'invasion 80, 1340 Ottignies",
        ],
        [
            'id' => 3,
            'name' => 'Blocry G3',
            'address' => 'Place des sports, 1 1348 Louvain-la-Neuve',
        ],
    ];

    public array $members = [
        ['id' => 1,  'name' => 'Alice Martin',    'email' => 'alice@example.com',    'ranking' => 3],
        ['id' => 2,  'name' => 'Bob Dupont',       'email' => 'bob@example.com',      'ranking' => 5],
        ['id' => 3,  'name' => 'Clara Petit',      'email' => 'clara@example.com',    'ranking' => 2],
        ['id' => 4,  'name' => 'David Moreau',     'email' => 'david@example.com',    'ranking' => 7],
        ['id' => 5,  'name' => 'Emma Bernard',     'email' => 'emma@example.com',     'ranking' => 4],
        ['id' => 6,  'name' => 'François Leroy',   'email' => 'francois@example.com', 'ranking' => 6],
        ['id' => 7,  'name' => 'Gabrielle Simon',  'email' => 'gab@example.com',      'ranking' => 1],
        ['id' => 8,  'name' => 'Hugo Laurent',     'email' => 'hugo@example.com',     'ranking' => 8],
        ['id' => 9,  'name' => 'Inès Michel',      'email' => 'ines@example.com',     'ranking' => 3],
        ['id' => 10, 'name' => 'Julien Garcia',    'email' => 'julien@example.com',   'ranking' => 5],
    ];

    public array $registrated = [
        ['id' => 1, 'name' => 'Alice Martin',  'ranking' => 'B2'],
        ['id' => 2, 'name' => 'Bob Dupont',     'ranking' => 'C4'],
        ['id' => 3, 'name' => 'Clara Petit',    'ranking' => 'D0'],
        ['id' => 4, 'name' => 'David Moreau',   'ranking' => 'D6'],
        ['id' => 5, 'name' => 'Emma Bernard',   'ranking' => 'E2'],
        ['id' => 6, 'name' => 'François Leroy', 'ranking' => 'NC'],
    ];

    public array $waiting_list = [
        ['id' => 7, 'name' => 'Gabrielle Simon', 'ranking' => 1],
        ['id' => 8, 'name' => 'Hugo Laurent',    'ranking' => 8],
    ];

    // ──────────────────────────────────────────
    // Étape 2 Invitations
    // ──────────────────────────────────────────

    public function toggleMember(int $id): void
    {
        if (in_array($id, $this->selectedMembers)) {
            $this->selectedMembers = array_values(
                array_filter($this->selectedMembers, fn ($m) => $m !== $id)
            );
        } else {
            $this->selectedMembers[] = $id;
        }
    }

    public function selectAllMembers(): void
    {
        $this->selectedMembers = array_column($this->members, 'id');
    }

    public function selectNoMembers(): void
    {
        $this->selectedMembers = [];
    }

    public bool $showInviteModal = false;

    public string $inviteMessage = '';

    public function sendInvitations(): void
    {
        // Simulation d'envoi (tu ajouterais ta logique Mail::to() ici)
        sleep(1);

        // Notification de succès Mary-UI
        $this->success(
            title: 'Invitations envoyées !',
            description: count($this->selectedMembers).' membres ont été notifiés.',
            icon: 'o-paper-airplane'
        );

        // On ferme le modal et on réinitialise le message
        $this->showInviteModal = false;
        $this->inviteMessage = '';

        // Optionnel : vider la sélection après envoi
        // $this->selectedMembers = [];
    }

    public bool $registrationClosed = false;

    /**
     * Switch registrations on/off
     */
    public function toggleRegistrations(): void
    {
        $this->registrationClosed = ! $this->registrationClosed;

        $this->success(
            title: $this->registrationClosed === false ? __('Registrations are opened') : __('Registrations are closed'),
            description: count($this->selectedMembers).' membres ont été notifiés.',
            icon: 'o-paper-airplane'
        );
    }

    // Propriétés pour l'article
    public bool $showPublishModal = false;

    public string $articleTitle = '';

    public string $articleContent = '';

    public string $publishDate = '';

    public array $selectedTags = [];

    // Options pour le modal
    public array $tagOptions = [
        ['id' => 1, 'name' => 'Tournoi'],
        ['id' => 2, 'name' => 'Interclubs'],
        ['id' => 3, 'name' => 'Jeunes'],
    ];

    public array $invitationHistory = [];

    public function getInvitationHistoryProperty()
    {
        // Exemple : Regroupement par date d'envoi (Batch)
        return [
            ['id' => 1, 'count' => 12, 'sent_at' => '2026-02-15 14:00', 'status' => 'Délivré'],
            ['id' => 2, 'count' => 5, 'sent_at' => '2026-01-10 09:30', 'status' => 'Délivré'],
        ];
    }

    public function viewBatchDetails($batchId)
    {
        // Logique pour ouvrir un modal avec la liste précise des membres invités ce jour-là
    }

    public function publishArticle()
    {
        $this->validate([
            'articleTitle' => 'required|min:5',
            'articleContent' => 'required',
        ]);

        // Logique de sauvegarde ici...
        sleep(1);

        $this->success(
            title: 'Article publié !',
            description: 'Votre article est maintenant en ligne.',
            icon: 'o-megaphone'
        );

        $this->showPublishModal = false;
    }

    // ──────────────────────────────────────────
    // Étape 3 Registrations
    // ──────────────────────────────────────────

    public array $selectedPeople = [];

    public bool $bulkDrawer = false;

    public function confirmBulkPresence(): void
    {
        if (empty($this->selectedPeople)) {
            return;
        }

        // Logique de mise à jour (Exemple avec Eloquent)
        // Registration::whereIn('id', $this->selectedPeople)->update(['status' => 'confirmed']);

        $this->resetPostAction();
        $this->success(count($this->selectedPeople).' présences confirmées.');
    }

    public function confirmBulkNoShow(): void
    {
        // Logique similaire pour le No-show
        $this->resetPostAction();
        $this->warning('Absences enregistrées.');
    }

    public function confirmBulkCancel(): void
    {
        // Logique similaire pour la suppression
        $this->resetPostAction();
        $this->error('Inscriptions annulées.');
    }

    private function resetPostAction(): void
    {
        $this->selectedPeople = [];
        $this->bulkDrawer = false;
    }

    // ──────────────────────────────────────────
    // Étape 4 Lancement
    // ──────────────────────────────────────────

    public bool $setupDrawer = false;

    // ──────────────────────────────────────────
    // Pools & grag & drog
    // ──────────────────────────────────────────
    public array $pools = [];

    public string $loggedPlayer = 'Jean Dupont';

    public function mount()
    {
        // Liste des classements belges possibles (de B0 à NC)
        $belgianRanks = ['B0', 'B2', 'B4', 'B6', 'C0', 'C2', 'C4', 'C6', 'D0', 'D2', 'D4', 'D6', 'E0', 'E2', 'E4', 'E6', 'NC'];

        for ($p = 1; $p <= 8; $p++) {
            $this->pools[$p] = [];
            for ($i = 1; $i <= 4; $i++) {
                $this->pools[$p][] = [
                    'id' => uniqid(),
                    'name' => ($p == 1 && $i == 1) ? $this->loggedPlayer : 'Joueur '.($p * 4 + $i),
                    'rank' => $belgianRanks[array_rand($belgianRanks)], // Attribution aléatoire
                    'pts' => rand(0, 9),
                ];
            }
        }

        $this->invitationHistory = [
            ['id' => 1, 'count' => 12, 'sent_at' => '2026-02-15 14:00', 'status' => 'Délivré'],
        ];

        $this->publicationDate = today()->format('Y-m-d');
    }

    public function movePlayer($fromPoolId, $toPoolId, $oldIndex, $newIndex)
    {
        // On récupère le joueur
        $player = $this->pools[$fromPoolId][$oldIndex];

        // On le supprime de l'ancienne liste
        unset($this->pools[$fromPoolId][$oldIndex]);
        $this->pools[$fromPoolId] = array_values($this->pools[$fromPoolId]);

        // On l'ajoute dans la nouvelle liste
        array_splice($this->pools[$toPoolId], $newIndex, 0, [$player]);

        // On force le rafraîchissement si nécessaire,
        // bien que Livewire le fasse automatiquement au changement de $pools
    }

    /**
     * Cette fonction reçoit la nouvelle structure envoyée par SortableJS
     */
    public function updateStructure($newStructure)
    {
        // 1. On crée un dictionnaire de tous les joueurs pour les retrouver instantanément
        $allPlayers = [];
        foreach ($this->pools as $pool) {
            foreach ($pool as $p) {
                $allPlayers[$p['id']] = $p;
            }
        }

        $updatedPools = [];

        // 2. On reconstruit l'état à partir des IDs envoyés par le JS
        foreach ($newStructure as $s) {
            $poolId = $s['teamId']; // 'teamId' correspond à ton data-team-id="{{ $poolId }}"

            $updatedPools[$poolId] = collect($s['memberIds'])
                ->map(fn ($id) => $allPlayers[$id] ?? null)
                ->filter()
                ->values()
                ->toArray();
        }

        // 3. On met à jour la variable publique pour que Livewire rafraîchisse l'UI
        $this->pools = $updatedPools;

        // Si tu veux afficher la petite barre de confirmation comme dans ton exemple :
        // $this->isDirty = true;
    }

    public bool $showLaunchModal = false;

    public function launch()
    {
        // 1. On ouvre le modal visuellement
        $this->showLaunchModal = true;

        // On ne fait PAS de sleep ici, sinon Livewire attend la fin de la fonction
        // pour envoyer l'ordre d'ouvrir le modal au navigateur.

        // On demande à Livewire d'exécuter la suite APRÈS le prochain rendu
        $this->js('$wire.processLaunch()');
    }

    public function processLaunch()
    {
        // 2. Maintenant le modal est visible, on peut simuler le travail
        sleep(5);

        // 3. Redirection finale
        return redirect()->route('admin.tournaments.live-center', 1);
    }

    // ──────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────
    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
            ->home()
            ->tournaments()
            ->current(__('Setup Wizard'))
            ->toArray()
        ];
    }

    public function render()
    {
        $filteredMembers = $this->members;

        if (! empty($this->memberSearch)) {
            $search = strtolower($this->memberSearch);
            $filteredMembers = array_values(array_filter(
                $this->members,
                fn ($m) => str_contains(strtolower($m['name']), $search)
                    || str_contains(strtolower($m['email']), $search)
            ));
        }

        return $this->view([
            'filteredMembers' => $filteredMembers,
        ]);
    }
};