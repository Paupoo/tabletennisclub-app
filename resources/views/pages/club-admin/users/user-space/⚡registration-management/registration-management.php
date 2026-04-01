<?php

use App\Models\Clubadmin\Users\User;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Mary\Traits\Toast;
new class extends Component
{
use Toast;

    // --- Gestion du panier d'inscriptions ---
    // Contient les données d'inscription pour chaque membre : id, formula, trainings[]
    public User $user;

    public array $registrations = [];

    public string $selectedTab;

    // --- Gestion du Modal "Ajouter un membre" ---
    public bool $addMemberModal = false;

    #[Rule('required|string')]
    public string $new_first_name = '';

    #[Rule('required|string')]
    public string $new_last_name = '';

    #[Rule('required|string')]
    public string $new_birthdate = '';

    #[Rule('required|string')]
    public string $new_gender = '';

    #[Rule('required|string|email')]
    public string $new_email = '';

    #[Rule('nullable|string')]
    public string $new_phone_number = '';

    public function mount(): void
    {
        // On initialise avec le membre connecté par défaut
        $this->user = User::first();
        $this->addRegistrationTab($this->user);
        $this->selectedTab = (string) 'tab-'.$this->user->id;
    }

    public function addRegistrationTab(User $user): void
    {
        // On ajoute le membre au "panier" d'inscriptions
        $this->registrations[$user->id] = [
            'user_id' => $user->id,
            'name' => $user->first_name.' '.$user->last_name,
            'formula' => 'competition', // Valeur par défaut
            'trainings' => [],
        ];
    }

    public function createFamilyMember(): void
    {
        $this->validate();

        // Création rapide du profil avec un mot de passe aléatoire
        $newMember = User::create([
            'first_name' => $this->new_first_name,
            'last_name' => $this->new_last_name,
            'birthdate' => $this->new_birthdate,
            'gender' => $this->new_gender,
            'email' => $this->new_email,
            'phone_number' => $this->phone_number ?? User::first()->phone_number,

            // On hérite des infos du parent
            'street' => User::first()->street,
            'postal_code' => User::first()->postal_code,
            'city' => User::first()->city,

            // LA CORRECTION EST ICI 👇
            'password' => Hash::make(Str::random(16)),
        ]);

        $this->addRegistrationTab($newMember);

        // Reset du modal
        $this->reset(['new_first_name', 'new_last_name', 'new_birthdate', 'new_gender', 'addMemberModal']);
        $this->success('Membre ajouté avec succès !');
    }

    #[Computed()]
    public function stats(): array
    {
        $registrations = collect($this->registrations);
        $countMembers = $registrations->count();

        // 1. Compter les types de licences
        $countCompetitors = $registrations->where('formula', 'competitive')->count();
        $countRecreative = $countMembers - $countCompetitors;

        // 2. Compter le total des sessions d'entraînement choisies par la famille
        $totalSessions = $registrations->sum(fn ($r) => count($r['trainings'] ?? []));

        // 3. Calcul du prix des licences (Base)
        $basePrice = ($countRecreative * 60) + ($countCompetitors * 125);

        // 4. Calcul du prix des entraînements (Logique de ton Alpine)
        $trainingPrice = 0;
        if ($totalSessions > 0) {
            // 90€ si solo, 80€ si famille (>1 membre)
            $firstSessionRate = $countMembers > 1 ? 80 : 90;

            if ($totalSessions === 1) {
                $trainingPrice = $firstSessionRate;
            } else {
                // Première session au tarif plein (ou réduit famille) + les autres à 80€
                $trainingPrice = $firstSessionRate + (($totalSessions - 1) * 80);
            }
        }

        return [
            'base' => $basePrice,
            'training' => $trainingPrice,
            'total' => $basePrice + $trainingPrice,
            'countMembers' => $countMembers,
            'countSessions' => $totalSessions,
        ];
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Registration management'))
                ->toArray(),
            'licenceTypes' => collect([
                ['id' => 'competitive', 'value' => 'competitive'],
                ['id' => 'recreative', 'value' => 'recreative'],
            ]),
            'trainings' => [
                [
                    'id' => 1,
                    'day' => 'Monday',
                    'time' => '18:00 - 20:00',
                    'coach' => 'Coach Jean',
                    'level' => __('D & E series'),
                    'dot_color' => 'bg-info',
                    'spots' => 4,
                    'full' => false,
                ],
                [
                    'id' => 2,
                    'day' => 'Wednesday',
                    'time' => '14:00 - 16:00',
                    'coach' => 'Coach Sarah',
                    'level' => __('Juniors (U12)'),
                    'dot_color' => 'bg-warning',
                    'spots' => 2,
                    'full' => false,
                ],
                [
                    'id' => 3,
                    'day' => 'Wednesday',
                    'time' => '18:00 - 20:00',
                    'coach' => 'Coach Sarah',
                    'level' => __('Advanced (B & C)'),
                    'dot_color' => 'bg-error',
                    'spots' => 0,
                    'full' => true,
                ],
                [
                    'id' => 4,
                    'day' => 'Friday',
                    'time' => '19:00 - 21:00',
                    'coach' => 'Coach Marc',
                    'level' => __('Beginners / Fun'),
                    'dot_color' => 'bg-success',
                    'spots' => 12,
                    'full' => false,
                ],
            ],
        ];
    }
};