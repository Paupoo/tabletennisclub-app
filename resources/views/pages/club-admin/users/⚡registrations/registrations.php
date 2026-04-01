<?php

use App\Models\ClubAdmin\Users\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public bool $registrationClosed = false;

    public string $search = '';

    public bool $memberDrawer = false;

    public string $searchMember = '';

    public bool $reviewModal = false;

    public bool $paymentGenerated = false;

    // Pour stocker la demande en cours de révision
    public array|object $currentRequest = [];

    public $selectedMember = null; // Contiendra l'objet membre sélectionné

    public string $searchFamily = '';

    public array $familyBasket = []; // Contient les configs : ['user_id' => [...], 'user_id' => [...]]

    /**
     * Ajoute un membre au panier actuel dans le drawer
     */
    public function addToBasket($userId)
    {
        $user = User::find($userId);

        // On initialise avec des valeurs par défaut pour ce membre précis
        $this->familyBasket[$userId] = [
            'name' => $user->first_name.' '.$user->last_name,
            'licence_type' => 'recreative',
            'trainings' => [],
        ];

        $this->searchMember = ''; // Reset la recherche pour en ajouter un autre éventuellement
    }

    /**
     * Supprime un membre du panier
     */
    public function removeFromBasket($userId)
    {
        unset($this->familyBasket[$userId]);
    }

    public function toggleRegistrations(): void
    {
        $this->registrationClosed = ! $this->registrationClosed;
    }

    #[Computed()]
    public function familyMatches()
    {
        // On n'affiche rien si la recherche est trop courte
        if (strlen($this->searchFamily) < 2) {
            return [];
        }

        // On cherche dans la table des membres/utilisateurs
        return User::query()
            ->where(function ($query) {
                $query->where('last_name', 'like', "%{$this->searchFamily}%")
                    ->orWhere('first_name', 'like', "%{$this->searchFamily}%");
            })
            // On exclut le membre qu'on est en train d'inscrire
            ->where('id', '!=', $this->selectedMember?->id)
            // Optionnel : on ne propose que ceux qui ont déjà une affiliation cette année
            // ->whereHas('registrations')
            ->limit(5)
            ->get();
    }

    public array $affiliationForm = [
        'type' => 'recreative',
        'trainings' => [],
        'hasFamilyGroup' => false,
        'familyId' => null,
    ];

    public function trainingOptions(): array
    {
        return [
            ['id' => 'lun-18', 'name' => 'Lundi 18h00'],
            ['id' => 'mer-14', 'name' => 'Mercredi 14h00'],
            ['id' => 'sam-10', 'name' => 'Samedi 10h00'],
        ];
    }

    public function selectMember($id)
    {
        $this->selectedMember = User::find($id);
        $this->searchMember = ''; // Reset la recherche
    }

    public function saveAffiliation()
    {
        // Logique de création de la registration
        // $this->affiliationForm contient tout : type, entraînements, groupement

        $this->memberDrawer = false;
        $this->selectedMember = null;
        $this->success('Inscription enregistrée !');
    }

    public function with(): array
    {
        return [
            'membersFound' => strlen($this->searchMember) > 2
                ? User::where(function ($q) {
                    $q->where('first_name', 'like', "%{$this->searchMember}%")
                        ->orWhere('last_name', 'like', "%{$this->searchMember}%")
                        ->orWhere('email', 'like', "%{$this->searchMember}%");
                })->limit(5)->get()
                : [],
            // On peut enlever 'trainingOptions' d'ici car on utilise la méthode ci-dessus
        ];
    }

    public function saveFamilyRegistration()
    {
        // Logique de sauvegarde (foreach sur $this->familyBasket...)
        $this->success('Inscription de groupe réussie !');
        $this->memberDrawer = false;
        $this->familyBasket = [];
    }

    public function renewAffiliation($memberId)
    {
        // Logique de renouvellement ici
        $this->success("Affiliation de l'utilisateur mise à jour !");
    }

    public function registrations(): Collection
    {
        // Mockup d'inscriptions complexes (familles, entraînements multiples)
        $data = collect([
            (object) [
                'id' => 101,
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'type' => 'Compétition',
                'status' => 'En attente',
                'members' => [
                    ['first_name' => 'Jean', 'last_name' => 'Dupont', 'trainings' => ['Mardi 18h-20h', 'Jeudi 18h-20h']],
                    ['first_name' => 'Léo', 'last_name' => 'Dupont', 'trainings' => ['Mercredi 14h-16h (Jeunes)']],
                ],
                'total_price' => 280,
            ],
            (object) [
                'id' => 102,
                'first_name' => 'Françoise',
                'last_name' => 'Martin',
                'type' => 'Récréative',
                'status' => 'En attente',
                'members' => [
                    ['first_name' => 'Sophie', 'last_name' => 'Martin', 'trainings' => ['Lundi 19h-21h (Loisirs)']],
                ],
                'total_price' => 110,
            ],
        ]);

        return $data->when($this->search, function ($collection) {
            return $collection->filter(
                fn ($req) => str_contains(strtolower($req->name), strtolower($this->search)),
            );
        });
    }

    public function headers(): array
    {
        return [
            ['key' => 'id', 'label' => '#', 'class' => 'w-16 text-gray-400'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'type', 'label' => 'Licence'],
            ['key' => 'members_count', 'label' => 'Members'],
            ['key' => 'trainings_count', 'label' => 'Trainings', 'sortable' => false],
            ['key' => 'status', 'label' => 'Status'],
        ];
    }

    public function review(int $id): void
    {
        $this->currentRequest = $this->registrations()->firstWhere('id', $id);
        $this->paymentGenerated = false;
        $this->reviewModal = true;
    }

    public function approve(): void
    {
        // Logique de validation ici...
        $this->paymentGenerated = true; // Affiche les infos de paiement
        $this->success('Inscription validée. Informations de paiement générées.');
    }

    public function reject(): void
    {
        $this->warning('Demande rejetée.');
        $this->reviewModal = false;
    }

    public function render(): View
    {
        return $this->view([
            'headers' => $this->headers(),
            'registrations' => $this->registrations(),
        ]);
    }
};