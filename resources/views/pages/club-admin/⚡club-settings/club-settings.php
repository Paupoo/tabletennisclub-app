<?php

use App\Enums\CommitteeRolesEnum;
use App\Models\ClubAdmin\Users\User;
use App\Support\Breadcrumb;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public bool $addCommitteeMemberModal = false;
    
    #[Rule('required', 'integer')]
    public ?int $selectedMemberId = null;

    public ?string $selectedRoleId = null;
    public $membersSearchList = [];

    public string $club_name = '';
    public string $club_id = '';
    public string $address = '';
    public string $contact_email = '';
    public string $contact_phone = '';
    public string $website_url = '';
    public bool $allow_online_renewal;
    public bool $public_trainings;

    public function rules(): array
    {
        return [
            'selectedRoleId' => ['required', new Enum(CommitteeRolesEnum::class)],
        ];
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Club Information'))
                ->toArray(),
            'committeeMembers' => User::where('is_committee_member', true)
                ->orderByRaw("
                    CASE
                        WHEN committee_role = 'PRESIDENT' THEN 1
                        WHEN committee_role = 'SECRETARY' THEN 2
                        WHEN committee_role = 'TREASURER' THEN 3
                        ELSE 4
                    END ASC
                ")
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'committee_role']),
        ];
    }

    public function getRoleOptionsProperty(): array
    {
        return CommitteeRolesEnum::getOptions();
    }

    public function searchMembers(string $value = '')
    {
        $this->membersSearchList = User::where('first_name', 'like', '%' . $value . '%')
            ->orWhere('last_name', 'like', '%' . $value . '%')
            ->orWhere('licence', 'like', '%' . $value . '%')
            ->take(5)
            ->get(['id', 'first_name', 'last_name', 'licence'])
            ->map(function ($user): array {
                return [
                    'id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'description' => $user->licence
                ];
            });
    }

    public function addMember(): void
    {
        $this->validate();
        $user = User::findOrFail($this->selectedMemberId);
        $user->update([
            'is_committee_member' => true,
            'committee_role' => $this->selectedRoleId
        ]);

        $this->reset(['selectedMemberId', 'selectedRoleId', 'addCommitteeMemberModal']);
        $this->success(__('Member added to committee list.'));
    }

    public function removeMember(int $id): void
    {
        $user = User::findOrFail($id);
        $user->update([
            'is_committee_member' => false,
            'committee_role' => null,
        ]);

        $this->success(__('Member removed from committee list'));
    }

    public function mount(): void
    {
        $this->club_name = env('APP_NAME', '');
        $this->club_id = env('APP_CLUB_LICENCE', '');
        $this->address = env('APP_CLUB_STREET', '');
        $this->contact_email = env('APP_CLUB_EMAIL', '');
        $this->contact_phone = env('APP_CLUB_PHONE_NUMBER', '');
        $this->website_url = 'cttottigniesblocry.be';
        $this->address = env('APP_CLUB_STREET', '');
        $this->public_trainings = true;
        $this->allow_online_renewal = true;
    }

    public function render(): View
    {
        return view('clubAdmin.club.club-settings');
    }
};