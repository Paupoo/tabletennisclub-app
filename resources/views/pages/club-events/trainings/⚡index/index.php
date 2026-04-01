<?php

use App\Mocks\HasMockTraining;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use HasMockTraining;

    public bool $showModal = false;

    public ?int $selectedId = null;

    public ?string $categoryFilter = null;

    public array $selectedCategory = [];

    public ?string $formRecurrence = null;

    // Une seule déclaration pour le formulaire avec ses valeurs par défaut
    public array $form = [
        'id' => null,
        'title' => '',
        'category' => '',
        'price' => 0,
        'start_date' => '',
        'max_spots' => 0,
    ];

    /**
     * Récupère l'entraînement sélectionné via son ID.
     * Accessible dans la vue via $this->selectedTraining
     */
    #[Computed()]
    public function selectedTraining()
    {
        return collect($this->getTrainings())->firstWhere('id', $this->selectedId);
    }

    // --- Actions de navigation ---

    public function showAttendance(int $id): void
    {
        $this->selectedId = $id;
    }

    public function backToList(): void
    {
        $this->selectedId = null;
    }

    // --- Actions CRUD (Mock) ---

    public function edit(int $id): void
    {
        $training = collect($this->getTrainings())->firstWhere('id', $id);

        if ($training) {
            $this->form = [
                'id' => $training['id'],
                'title' => $training['title'],
                'category' => $training['category'],
                'price' => $training['price'],
                'start_date' => $training['start_date'],
                'max_spots' => $training['max_spots'],
            ];
            $this->showModal = true;
        }
    }

    public function save(): void
    {
        // Ici, logique de sauvegarde réelle plus tard...
        $this->showModal = false;
        $this->reset('form'); // On vide le formulaire
        // $this->toast()->success('Enregistré !');
    }

    public function delete(int $id): void
    {
        // Logique de suppression
    }

    /**
     * On passe les données nécessaires à la vue
     */
    public function with(): array
    {

        return [
            'trainings' => $this->getTrainings(),   // Vient du Trait
            'categories' => $this->getCategories(), // Vient du Trait
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Trainings'), route('admin.trainings.index'))
                ->toArray(),
            'headers' => [
                ['key' => 'title', 'label' => __('Title')],
                ['key' => 'category', 'label' => __('Category')],
                ['key' => 'location', 'label' => __('Location')],
                ['key' => 'coach_name', 'label' => __('Coach Name')],
                ['key' => 'spots', 'label' => __('Spots')],
            ],
            'recurrenceOptions' => $this->getRecurrences(), // Vient du Trait

        ];
    }

    public function render(): View
    {
        return $this->view();
    }
};