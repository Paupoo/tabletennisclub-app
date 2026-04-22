<?php

declare(strict_types=1);

use App\Enums\CommitteeRolesEnum;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public bool $isOpen = false;

    #[Validate('required|integer')]
    public ?int $selectedMemberId = null;

    public ?string $selectedRoleId = null;

    public $membersSearchList = [];

    public function getRoleOptionsProperty(): array
    {
        return CommitteeRolesEnum::getOptions();
    }

    public function search(string $value = ''): void
    {
        $this->membersSearchList = User::query()
            ->where('first_name', 'like', "%{$value}%")
            ->orWhere('last_name', 'like', "%{$value}%")
            ->orWhere('licence', 'like', "%{$value}%")
            ->take(5)
            ->get(['id', 'first_name', 'last_name', 'licence'])
            ->map(function (User $user) {
                return [
                'id' => $user->id,
                'name' => "{$user->first_name} {$user->last_name}",
                'description' => $user->licence
                ];
            });
    }

    #[On('open-committee-modal')]
    public function open(): void
    {
        $this->isOpen = true;
    }

    public function rules(): array
    {
        return [
            'selectedRoleId' => ['required', new Enum(CommitteeRolesEnum::class)],
        ];
    }

    public function addMember(): void
    {
        $validated = $this->validate();

        $user = User::findOrFail($validated['selectedMemberId']);
        $user->update([
            'is_committee_member' => true,
            'committee_role' => $validated['selectedRoleId']
        ]);

        $this->reset();
        $this->dispatch('member-added'); // 👈 Notifie le parent
        $this->success(__('Member added to committee list.'));
    }

    public function close(): void   
    {
        $this->reset();
        $this->isOpen = false;
    }

    public function mount(): void
    {
        $this->search();
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div>
    <x-modal wire:model="isOpen" title="{{ __('Add Committee Member') }}" separator>
        <div class="grid gap-4">
            <x-choices
                label="{{ __('Search Member') }}"
                wire:model="selectedMemberId"
                :options="$membersSearchList"
                no-result-text="{{ __('Oops, nothing found here.') }}"
                debounce="250"
                min-chars="2"
                icon="o-magnifying-glass"
                hint="{{ __('Search by name or license number') }}"
                single
                searchable
                clearable
            >
                @scope('item', $user)
                    <x-list-item :item="$user" sub-value="description" />
                @endscope
                
            </x-choices>

            <x-select 
                label="{{ __('Committee Role') }}" 
                icon="o-briefcase" 
                placeholder="{{ __('Select a role') }}"
                :options="$this->roleOptions" 
                wire:model="selectedRoleId" 
            />
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="close" />
            <x-button 
                label="{{ __('Add to Committee') }}" 
                class="btn-primary" 
                icon="o-check" 
                wire:click="addMember" 
                spinner="addMember" 
            />
        </x-slot:actions>
    </x-modal>
</div>