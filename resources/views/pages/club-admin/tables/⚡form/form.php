    <?php

    use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Support\Breadcrumb;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    #[Validate('nullable')]
    public ?string $brand = null;

    #[Validate('nullable')]
    public ?string $model = null;

    #[Validate('required')]
    public string $name = '';

    #[Validate('nullable')]
    public ?int $room_id = null;

    #[Validate('nullable|date')]
    public ?string $purchased_on = null;

    #[Validate('nullable')]
    public ?string $state = null;

    #[Validate('nullable')]
    public ?string $state_description = null;

    public array $states = [];

    public ?int $tableId = null; // pour savoir si c'est un update

    public function mount(?Table $table): void
    {
        if ($table && $table->exists) {
            $this->tableId = $table->id;
            $this->name = $table->name;
            $this->brand = $table->brand ?? '';
            $this->model = $table->model ?? '';
            $this->room_id = $table->room_id;
            $this->purchased_on = $table->purchased_on ? $table->purchased_on->format('Y-m-d') : null;
            $this->state = $table->state;
            $this->state_description = $table->state_description ?? '';
        }

        $this->states = Table::getStates();
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->tables()
                ->current($this->tableId ? __('Update') : __('Create'))
                ->toArray(),
            'rooms' => Room::all(),
            'states' => $this->states,
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $table = $this->tableId
            ? Table::findOrFail($this->tableId)
            : new Table();

        $table->fill($validated)->save();

        $this->success('Table enregistrée avec succès.');
    }
};
