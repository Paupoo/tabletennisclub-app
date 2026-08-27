<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams;

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Models\AppSetting;
use App\Domains\Shared\Rules\ValidIban;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    public bool $addCommitteeMemberModal = false;

    #[Validate(['required', 'string', 'max:50', new ValidIban])]
    public ?string $bank_account;

    #[Validate('string|max:20')]
    public ?string $bic;

    #[Validate('nullable|string|max:100')]
    public ?string $building_name;

    #[Validate('required|integer|between:1000,9999')]
    public ?string $city_code;

    #[Validate('required|string|max:100')]
    public ?string $city_name;

    #[Validate('required|email|max:100')]
    public ?string $email_contact;

    #[Validate('nullable|string|max:13')]
    public ?string $enterprise_number;

    #[Validate('nullable|string|max:100')]
    public ?string $interclubDay = 'Vendredi';

    #[Validate('nullable|string|max:255')]
    public ?string $interclubDescription = 'Matches de compétition à domicile. Venez nous supporter !';

    // ── Interclub schedule settings ───────────────────────────────────────────

    #[Validate('boolean')]
    public bool $interclubEnabled = true;

    #[Validate('nullable|string|max:100')]
    public ?string $interclubLocation = 'Demeester (0 et -1)';

    #[Validate('nullable|string|max:100')]
    public ?string $interclubTimeEnd = '23:30';

    #[Validate('nullable|string|max:100')]
    public ?string $interclubTimeStart = '19:00';

    #[Validate('nullable|numeric')]
    public ?float $latitude;

    public string $licence;

    #[Validate('nullable|numeric')]
    public ?float $longitude;

    public ?string $name;

    #[Validate('nullable|string|max:50 ')]
    public ?string $phone_contact;

    #[Validate('required|string|max:255')]
    public ?string $street;

    #[Validate('nullable|string')]
    public ?string $website_url;

    /**
     * Whether the visitor may seat or unseat anyone at all.
     *
     * Asked of an unsaved User because the button it guards targets nobody yet;
     * the per-member check below is what refuses a visitor their own row.
     */
    #[Computed()]
    public function canManageAccess(): bool
    {
        return Gate::allows('manageAccess', new User);
    }

    public function mount(): void
    {
        $club = Club::own();

        $this->name = $club->name ?? '';
        $this->licence = $club->licence ?? '';
        $this->street = $club->street ?? '';
        $this->city_code = $club->city_code ?? '';
        $this->city_name = $club->city_name ?? '';
        $this->building_name = $club->building_name ?? '';
        $this->latitude = $club?->latitude;
        $this->longitude = $club?->longitude;
        $this->email_contact = $club?->email_contact;
        $this->phone_contact = $club->phone_contact ?? '';
        $this->website_url = $club->website_url ?? '';
        $this->bank_account = $club->bank_account ?? '';
        $this->bic = $club->bic ?? '';
        $this->enterprise_number = $club->enterprise_number ?? '';

        $this->interclubEnabled = AppSetting::get('interclub_schedule_enabled', '1') === '1';
        $this->interclubDay = AppSetting::get('interclub_schedule_day', 'Vendredi');
        $this->interclubTimeStart = AppSetting::get('interclub_schedule_time_start', '19:00');
        $this->interclubTimeEnd = AppSetting::get('interclub_schedule_time_end', '23:30');
        $this->interclubLocation = AppSetting::get('interclub_schedule_location', 'Demeester (0 et -1)');
        $this->interclubDescription = AppSetting::get('interclub_schedule_description', 'Matches de compétition à domicile. Venez nous supporter !');
    }

    #[On('member-added')] // 👈 Écoute l'événement du modal
    public function refreshCommitteeMembers(): void
    {
        // La liste des membres se rafraîchit automatiquement
        // grâce au `with()` qui est appelé après chaque interaction
    }

    /**
     * The seat and its statutory title are rights, so taking them away answers to
     * whoever manages rights — not to whoever may edit the venue settings, which
     * is all this screen used to ask for.
     */
    public function removeMember(int $id): void
    {
        $user = User::findOrFail($id);

        Gate::authorize('manageAccess', $user);

        $user->removeRole(Role::COMMITTEE->value);
        $user->update(['committee_role' => null]);

        $this->success(__('Member removed from committee list'));
    }

    public function render(): View
    {
        return $this->view();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', ValidationRule::unique('clubs', 'name')->ignore($this->licence, 'licence')],
            'licence' => ['required', 'string', ValidationRule::unique('clubs', 'licence')->ignore($this->licence, 'licence')],
        ];
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

        Club::forgetOwnClub();

        AppSetting::set('interclub_schedule_enabled', $this->interclubEnabled ? '1' : '0');
        AppSetting::set('interclub_schedule_day', $this->interclubDay ?? 'Vendredi');
        AppSetting::set('interclub_schedule_time_start', $this->interclubTimeStart ?? '19:00');
        AppSetting::set('interclub_schedule_time_end', $this->interclubTimeEnd ?? '23:30');
        AppSetting::set('interclub_schedule_location', $this->interclubLocation ?? 'Demeester (0 et -1)');
        AppSetting::set('interclub_schedule_description', $this->interclubDescription ?? 'Matches de compétition à domicile. Venez nous supporter !');

        $this->success(__('Club information updated.'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'committeeMembers' => User::role(Role::COMMITTEE->value)
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
            'keyHolders' => User::where('has_key', true)
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name']),
            'cashRegisterHolders' => CashRegister::with('heldBy')
                ->orderBy('name')
                ->get(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Club Information'));
    }
};
