<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Clubs;

use App\Domains\Competitions\Interclub\Models\Club;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs;

    public string $search = '';

    public bool $editModal = false;

    public bool $deleteModal = false;

    public ?int $editingClubId = null;

    public ?int $deletingClubId = null;

    public string $formName = '';

    public string $formLicence = '';

    public string $formStreet = '';

    public string $formCityCode = '';

    public string $formCityName = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->is_admin || Auth::user()->is_committee_member, 403);
    }


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__("Clubs"));
    }

        public function render(): View
    {
        return $this->view()->title(__('Opponent Clubs'));
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->editingClubId = null;
        $this->formName      = '';
        $this->formLicence   = '';
        $this->formStreet    = '';
        $this->formCityCode  = '';
        $this->formCityName  = '';
        $this->editModal     = true;
    }

    public function openEditModal(int $clubId): void
    {
        $club = Club::findOrFail($clubId);
        $this->resetErrorBag();
        $this->editingClubId = $club->id;
        $this->formName      = $club->name;
        $this->formLicence   = $club->licence;
        $this->formStreet    = $club->street ?? '';
        $this->formCityCode  = $club->city_code ?? '';
        $this->formCityName  = $club->city_name ?? '';
        $this->editModal     = true;
    }

    public function save(): void
    {
        $this->validate([
            'formName'     => ['required', 'string', 'max:100'],
            'formLicence'  => ['nullable', 'string', 'max:50', Rule::unique('clubs', 'licence')->ignore($this->editingClubId)],
            'formStreet'   => ['nullable', 'string', 'max:255'],
            'formCityCode' => ['nullable', 'string', 'max:10'],
            'formCityName' => ['nullable', 'string', 'max:100'],
        ]);

        $name    = trim($this->formName);
        $licence = trim($this->formLicence) ?: 'OPP-' . strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 8));

        $data = [
            'name'      => $name,
            'licence'   => $licence,
            'street'    => trim($this->formStreet) ?: null,
            'city_code' => trim($this->formCityCode) ?: null,
            'city_name' => trim($this->formCityName) ?: null,
        ];

        if ($this->editingClubId) {
            Club::findOrFail($this->editingClubId)->update($data);
            $this->success(__('Club updated.'));
        } else {
            Club::create($data);
            $this->success(__('Club created.'));
        }

        $this->editModal = false;
    }

    public function confirmDelete(int $clubId): void
    {
        $this->deletingClubId = $clubId;
        $this->deleteModal    = true;
    }

    public function delete(): void
    {
        if (! $this->deletingClubId) {
            $this->deleteModal = false;

            return;
        }

        $club = Club::withCount('teams')->findOrFail($this->deletingClubId);

        if ($club->teams_count > 0) {
            $this->error(__('Cannot delete: this club has teams linked to it.'));
            $this->deleteModal    = false;
            $this->deletingClubId = null;

            return;
        }

        $club->delete();
        $this->deleteModal    = false;
        $this->deletingClubId = null;
        $this->success(__('Club deleted.'));
    }

    public function with(): array
    {
        $clubs = Club::otherClubs()
            ->withCount('teams')
            ->when(
                $this->search,
                fn ($q) => $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('city_name', 'like', '%' . $this->search . '%')
            )
            ->orderBy('name')
            ->get();

        return [
            'clubs'       => $clubs,
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add(__('Interclubs'), '#')
                ->current(__('Opponent Clubs'))
                ->toArray(),
        ];
    }
};
