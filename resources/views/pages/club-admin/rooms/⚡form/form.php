<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    #[Validate('string|max:255')]
    public string $access_description = '';

    public array $allTables = [];

    #[Validate('required|string|max:255')]
    public string $building_name = '';

    #[Validate('required|integer|between:0,99|lte:capacity_for_trainings')]
    public int|string $capacity_for_interclubs = 0;

    #[Validate('required|integer|between:0,99|gte:capacity_for_interclubs')]
    public int|string $capacity_for_trainings = 0;

    #[Validate('required|integer|between:1000,9999')]
    public string $city_code = '';

    #[Validate('required|string|max:255')]
    public string $city_name = '';

    public array $filteredTables = [];

    #[Validate('string|max:10')]
    public string $floor = '';

    #[Validate('required|string|max:255')]
    public string $name = '';

    public string $newTableName = '';
    public string $newTableBrand = '';
    public string $newTableModel = '';
    public string $newTablePurchasedOn = '';
    public string $newTableState = 'new';

    public Room $room;

    public array $selectedTables = [];

    public bool $showTableModal = false;

    #[Validate('required|string|max:255')]
    public string $street = '';

    public function addTableToList(): void
    {
        // 1. Validation spécifique au modal uniquement !
        $this->validate([
            'newTableName' => 'required|string|max:255',
            'newTableBrand' => 'nullable|string|max:100',
            'newTableModel' => 'nullable|string|max:100',
            'newTablePurchasedOn' => 'nullable|date',
            'newTableState' => 'required|string|max:10',
        ]);

        $newTable = Table::create([
            'name' => $this->newTableName,
            'brand' => $this->newTableBrand,
            'model' => $this->newTableModel,
            'state' => $this->newTableState,
            'state_description' => $this->newTableState,
            'purchased_on' => $this->newTablePurchasedOn,
        ]);

        // 3. Ajouter au tableau principal et filtré
        $this->allTables[] = $newTable;
        $this->filteredTables[] = $newTable;

        // 4. Sélectionner automatiquement la nouvelle table
        $this->selectedTables[] = $newTable->id;

        // 5. Reset et fermeture
        $this->reset(['newTableName', 'newTableState', 'newTableBrand', 'newTableModel', 'newTableState', 'newTablePurchasedOn', 'showTableModal']);

        $this->success(__('Table added to selection!'));
    }

    public function clearForm(): void
    {
        $this->reset();
        $this->resetValidation();
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

        $this->allTables = Table::query()
                ->where(function ($query) use ($room) {
                    $query->doesntHave('room')
                        ->orWhere('room_id', $room->id ?? null);
                })                
                ->get()->map(function ($table) {
            return [
                'id' => $table->id,
                'name' => $table->name,
                'purchased_on' => $table->purchased_on?->format('d M Y'),
                'state' => $table->state,
            ];
        })->toArray();

        // $tables_already_in_room = Table::whereRoomId($room->id)->get()->map(function ($table) {
        //         return [
        //             'id' => $table->id,
        //             'name' => $table->name,
        //             'purchased_on' => $table->purchased_on?->format('d M Y'),
        //             'state' => $table->state,
        //         ];
        //     })->toArray();

        // $this->allTables = array_merge($this->allTables, $tables_already_in_room);

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
