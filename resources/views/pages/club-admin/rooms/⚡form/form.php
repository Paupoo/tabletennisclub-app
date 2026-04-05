<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    #[Rule('string|max:255')]
    public string $access_description = '';

    public array $allTables = [];

    #[Rule('required|string|max:255')]
    public string $building_name = '';

    #[Rule('required|integer|between:0,99')]
    public int $capacity_for_interclubs = 0;

    #[Rule('required|integer|between:0,99')]
    public int $capacity_for_trainings = 0;

    #[Rule('required|integer|between:1000,9999')]
    public string $city_code = '';

    #[Rule('required|string|max:255')]
    public string $city_name = '';

    public array $filteredTables = [];

    #[Rule('string|max:10')]
    public string $floor = '';

    #[Rule('required|string|max:255')]
    public string $name = '';

    public string $newTableName = '';
    public string $newTablePurchasedOn = '';
    public string $newTableState = 'new';

    public Room $room;

    public array $selectedTables = [];

    public bool $showTableModal = false;

    #[Rule('required|string|max:255')]
    public string $street = '';

    public function addTableToList(): void
    {
        // 1. Validation spécifique au modal uniquement !
        $this->validate([
            'newTableName' => 'required|string|max:10',
            'newTablePurchasedOn' => 'required|date',
            'newTableState' => 'required|string|max:10',
        ]);

        // 2. Création d'un ID temporaire unique (négatif pour différencier du hardcodé)
        $tempId = count($this->allTables) + 100;

        $newTable = [
            'id' => $tempId,
            'name' => $this->newTableName,
            'purchased_on' => $this->newTablePurchasedOn ?: now()->format('Y-m-d'),
            'state' => $this->newTableState,
            'room_id' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        // 3. Ajouter au tableau principal et filtré
        $this->allTables[] = $newTable;
        $this->filteredTables[] = $newTable;

        // 4. Sélectionner automatiquement la nouvelle table
        $this->selectedTables[] = $tempId;

        // 5. Reset et fermeture
        $this->reset(['newTableName', 'newTableState', 'newTablePurchasedOn', 'showTableModal']);

        $this->success(__('Table added to selection!'));
    }

    public function clearForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function decrementMatch($amount = 1)
    {
        $this->capacity_for_interclubs = max(0, $this->capacity_for_interclubs - $amount);
    }

    public function decrementTraining($amount = 1)
    {
        $this->capacity_for_trainings = max(0, $this->capacity_for_trainings - $amount);
    }

    public function incrementMatch($amount = 1)
    {
        $this->capacity_for_interclubs = min(99, $this->capacity_for_interclubs + $amount);
    }

    public function incrementTraining($amount = 1)
    {
        $this->capacity_for_trainings = min(99, $this->capacity_for_trainings + $amount);
    }

    public function mount(?Room $room = null)
    {
        // 1. On stocke le modèle (ou un nouveau modèle vide)
        $this->room = $room ?? new Room;

        // 2. On ne remplit les champs que si le modèle existe déjà en base de données
        if ($this->room->exists) {
            $this->name = $this->room->name;
            $this->building_name = $this->room->building_name;
            $this->street = $this->room->street;
            $this->city_code = $this->room->city_code;
            $this->city_name = $this->room->city_name;
            $this->floor = $this->room->floor ?? '';
            $this->access_description = $this->room->access_description ?? '';
            $this->capacity_for_trainings = $this->room->capacity_for_trainings;
            $this->capacity_for_interclubs = $this->room->capacity_for_interclubs;
            
            $this->selectedTables = $this->room->tables()->pluck('id')->toArray();
        }

        $this->room->exists
                ? $this->selectedTables = $this->room->tables()->pluck('id')->toArray()
                : $this->selectedTables = [];

        $this->allTables = Table::all()->map(function ($table) {
            return [
                'id' => $table->id,
                'name' => $table->name,
                'purchased_on' => $table->purchased_on?->format('d M Y'),
                'state' => $table->state,
            ];
        })->toArray();

        $this->filteredTables = $this->allTables;
    }

    public function render(): View
    {
        return $this->view();
    }

    public function save(): void
    {
        // 1. Validation des données du formulaire
        $validated = $this->validate();

        // 2. Sauvegarde de la Room (Création ou Mise à jour)
        $this->room->fill([
            'name' => $this->name,
            'building_name' => $this->building_name,
            'street' => $this->street,
            'city_code' => $this->city_code,
            'city_name' => $this->city_name,
            'floor' => $this->floor,
            'access_description' => $this->access_description,
            'capacity_for_trainings' => $this->capacity_for_trainings,
            'capacity_for_interclubs' => $this->capacity_for_interclubs,
        ]);

        $this->room->save();

        // 3. Tri des tables sélectionnées (Existantes vs Nouvelles)
        $existingTableIds = [];
        $newTablesToCreate = [];

        foreach ($this->selectedTables as $tableId) {
            // On vérifie si c'est un ID temporaire (string commençant par 'new_')
            // *Voir point 2 plus bas pour l'amélioration de l'ID temporaire*
            if (is_string($tableId) && str_starts_with($tableId, 'new_')) {
                $tempTable = collect($this->allTables)->firstWhere('id', $tableId);
                if ($tempTable) {
                    $newTablesToCreate[] = [
                        'name' => $tempTable['name'],
                        // Assure-toi que le format correspond à ce qu'attend ta base
                        'purchased_on' => Carbon::parse($tempTable['purchased_on'])->format('Y-m-d'),
                        'state' => $tempTable['state'],
                    ];
                }
            } else {
                $existingTableIds[] = $tableId;
            }
        }

        // 4. Mise à jour des tables existantes
        // A. Détacher les tables qui appartenaient à cette salle mais qui ont été désélectionnées
        Table::where('room_id', $this->room->id)
            ->whereNotIn('id', $existingTableIds)
            ->update(['room_id' => null]);

        // B. Attacher les tables existantes sélectionnées à cette salle
        if (! empty($existingTableIds)) {
            Table::whereIn('id', $existingTableIds)->update(['room_id' => $this->room->id]);
        }

        // 5. Création en base des nouvelles tables ajoutées via le modal
        if (! empty($newTablesToCreate)) {
            $this->room->tables()->createMany($newTablesToCreate);
        }

        // 6. Feedback et redirection
        $this->success(__('Room saved successfully!'));

        // Optionnel : rediriger vers la liste des salles
        // $this->redirect(route('rooms.index'));
    }

    public function searchTables(string $value = '')
    {
        // On met à jour la variable de la vue
        $this->filteredTables = collect($this->allTables)
            ->filter(function ($table) use ($value) {
                return str_contains(strtolower($table['name']), strtolower($value));
            })
            ->take(10)
            ->values()
            ->toArray();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->rooms()
                ->current($this->room->exists ? __('Edit') : __('Create'))
                ->toArray(),
        ];
    }
};
