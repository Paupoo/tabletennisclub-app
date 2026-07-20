    <?php

    use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\Shared\Enums\TableStateEnum;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    #[Validate('nullable')]
    public ?string $brand = null;

    #[Validate('nullable')]
    public ?string $model = null;

    #[Validate('required')]
    public string $name = '';

    #[Validate('nullable|date')]
    public ?string $purchased_on = null;

    #[Validate('nullable')]
    public ?int $room_id = null;

    #[Validate(['required', new Enum(TableStateEnum::class)])]
    public ?string $state = null;

    #[Validate('nullable')]
    public ?string $state_description = null;

    public array $states = [];

    public ?int $tableId = null; // pour savoir si c'est un update

    public function mount(?Table $table): void
    {
        $this->tableId = $table->id;
        $this->name = $table->name ?? '';
        $this->brand = $table->brand ?? '';
        $this->model = $table->model ?? '';
        $this->room_id = $table->room_id;
        $this->purchased_on = $table->purchased_on ? $table->purchased_on->format('Y-m-d') : null;
        $this->state = ($table->state ?? TableStateEnum::GOOD)->value;
        $this->state_description = $table->state_description ?? '';

        $this->states = Table::getStates();
    }

    public function render(): View
    {
        return $this->view();
    }

    public function save(): void
    {
        $validated = $this->validate();

        $table = $this->tableId
            ? Table::findOrFail($this->tableId)
            : new Table;

        $table->fill($validated)->save();

        $this->success('Table enregistrée avec succès.');
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'rooms' => Room::all(),
            'states' => $this->states,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->tables()
            ->current($this->tableId ? __('Update') : __('Create'));
    }
};
