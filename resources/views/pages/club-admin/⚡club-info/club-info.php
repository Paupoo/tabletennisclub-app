<?php

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Club;
use App\Support\Breadcrumb;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public bool $addCommitteeMemberModal = false;

    public ?string $name;

    public string $licence;

    #[Validate('required|string|max:255')]
    public ?string $street;

    #[Validate('required|integer|between:1000,9999')]
    public ?string $city_code;

    #[Validate('required|string|max:100')]
    public ?string $city_name;
    
    #[Validate('nullable|string|max:100')]
    public ?string $building_name;

    #[Validate('nullable|numeric')]
    public ?float $latitude;

    #[Validate('nullable|numeric')]
    public ?float $longitude;

    #[Validate('nullable|email|max:100 ')]
    public ?string $email_contact;

    #[Validate('nullable|string|max:50 ')]
    public ?string $phone_contact;

    #[Validate('nullable|string')]
    public ?string $website_url;

    #[Validate('nullable|string|max:50')]
    public ?string $bank_account;

    #[Validate('nullable|string|max:13')]
    public ?string $enterprise_number;

    public function rules(): array
    {
        return [
            'name' => ['required','string', ValidationRule::unique('clubs', 'name')->ignore($this->licence, 'licence')],
            'licence' => ['required', 'string', ValidationRule::unique('clubs', 'licence')->ignore($this->licence, 'licence')],
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
        $club = Club::ourClub()->first();

        $this->name = $club->name ?? '';
        $this->licence = env('APP_CLUB_LICENCE', '');
        $this->street =  $club->street ?? '';
        $this->city_code =  $club->city_code ?? '';
        $this->city_name = $club->city_name ?? '';
        $this->building_name =  $club->building_name ?? '';
        $this->latitude =  $club?->latitude;
        $this->longitude =  $club?->longitude;
        $this->email_contact =  $club->email_contact ?? '';
        $this->phone_contact =  $club->phone_contact ?? '';
        $this->website_url =  $club->website_url ?? '';
        $this->bank_account =  $club->bank_account ?? '';
        $this->enterprise_number =  $club->enterprise_number ?? '';
    }

    #[On('member-added')]  // 👈 Écoute l'événement du modal
    public function refreshCommitteeMembers(): void
    {
        // La liste des membres se rafraîchit automatiquement
        // grâce au `with()` qui est appelé après chaque interaction
    }

    public function save(): void
    {
        try {
            $validated = $this->validate();
        } catch (ValidationException $e) {
            $this->error(
                'Une erreur est survenue. Veuillez vérifier les champs du formulaire.'
            );

            throw $e; // important pour conserver l'affichage des erreurs sous les champs
        } catch (Throwable $e) {

            report($e);

            $this->error(
                'Une erreur inattendue est survenue. Veuillez réessayer.'
            );
            return;
        }

        $club = Club::ourClub()->first();

        $club->update($validated);

        $this->success(__('Club information updated.'));
    }

    public function render(): View
    {
        return $this->view();
    }
};