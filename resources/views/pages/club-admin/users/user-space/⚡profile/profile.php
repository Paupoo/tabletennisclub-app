<?php

declare(strict_types=1);

use App\Actions\User\UpdateUserAction;
use App\Data\User\UpdateUserData;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Rules\ValidIban;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasPhotoUpload;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasPhotoUpload, Toast, WithFileUploads;

    public string $activeTeamTab = '';

    #[Rule('nullable|date')]
    public ?string $birthdate = null;

    #[Rule('nullable|integer|between:1000,9999')]
    public ?string $city_code = null;

    #[Rule('nullable|string|max:100')]
    public ?string $city_name = null;

    public bool $drawer = false;

    // Contact
    public string $email = '';

    // Identity
    #[Rule('required|string|max:255')]
    public string $first_name = '';

    #[Rule('required')]
    public ?Gender $gender = Gender::MEN;

    #[Rule(['nullable', new ValidIban])]
    public ?string $iban = null;

    #[Rule('required|string|max:255')]
    public string $last_name = '';

    // Documents (uploaded by the member)
    public $medicalCertificate = null;

    public $parentalConsent = null;

    #[Rule('nullable|string|max:20')]
    public ?string $phone_number = null;

    #[Rule('nullable|string|max:255')]
    public ?string $street = null;

    public User $user;

    /**
     * Whether the member is a minor (< 18y) based on the entered birthdate.
     * Drives whether the parental consent document is relevant.
     */
    #[Computed()]
    public function isMinor(): bool
    {
        return $this->birthdate !== null
            && $this->birthdate !== ''
            && Carbon::parse($this->birthdate)->age < 18;
    }

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->first_name = $user->first_name ?? '';
        $this->last_name = $user->last_name ?? '';
        $this->gender = $user->gender ?? Gender::MEN;
        $this->birthdate = $user->birthdate?->format('Y-m-d');
        $this->email = $user->email;
        $this->phone_number = $user->phone_number;
        $this->street = $user->street;
        $this->city_code = $user->city_code;
        $this->city_name = $user->city_name;
        $this->iban = $user->iban;
        $this->currentPhoto = $user->photo;
        $this->activeTeamTab = 'team-' . $this->user->teams->first()?->id;
    }

    public function render(): View
    {
        return $this->view();
    }

    public function requestErasure(): void
    {
        abort_unless(Auth::user()->is($this->user), 403);

        $this->user->update(['gdpr_erasure_requested_at' => now()]);

        $this->success(__('Erasure request sent. The admin will process it shortly.'));
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                ValidationRule::unique('users', 'email')->ignore($this->user->id),
            ],
            'photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2024',
            ],
            'medicalCertificate' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:4096',
            ],
            'parentalConsent' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:4096',
            ],
        ];
    }

    public function save(): void
    {
        $actor = Auth::user();

        if (! ($actor->is($this->user) || $actor->is_admin || $actor->is_committee_member)) {
            $this->error(__('Unauthorized.'));

            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->error(__('Please check the form fields.'));
            throw $e;
        }

        $this->handlePhotoUpload($this->user);
        $this->handleDocumentUploads($this->user);

        UpdateUserAction::handle(
            $this->user,
            new UpdateUserData(
                first_name: $this->first_name,
                last_name: $this->last_name,
                email: $this->email,
                gender: $this->gender,
                phone_number: $this->phone_number,
                street: $this->street,
                city_code: $this->city_code,
                city_name: $this->city_name,
                birthdate: $this->birthdate,
                // Legacy free-text guardian phone is now managed via the Guardian system
                // (admin side); preserve any existing value, not editable here.
                guardian_phone_number: $this->user->guardian_phone_number,
                iban: $this->iban,
                // Admin-only fields are preserved from the current model (not self-editable).
                is_competitor: $this->user->is_competitor,
                is_committee_member: $this->user->is_committee_member,
                is_admin: $this->user->is_admin,
                is_coach: $this->user->is_coach,
                licence: $this->user->licence,
                ranking: $this->user->ranking,
                committee_role: $this->user->committee_role,
                guardianIds: $this->user->guardians()->pluck('guardians.id')->all(),
            ),
            $actor,
        );

        $this->drawer = false;

        $this->success(__('Profile updated.'));
    }

    public function with(): array
    {
        $this->user->loadMissing('teams.league', 'teams.users', 'teams.club', 'teams.season');

        return [
            'genders' => Gender::options(),
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->add(__('My profile'), null, null);
    }

    /**
     * Store any newly uploaded member documents (medical certificate, parental consent).
     */
    protected function handleDocumentUploads(User $user): void
    {
        if ($this->medicalCertificate !== null) {
            if ($user->medical_certificate_path) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->medical_certificate_path));
            }

            $extension = $this->medicalCertificate->getClientOriginalExtension();
            $path = $this->medicalCertificate->storeAs("documents/{$user->id}", "medical.{$extension}", 'public');

            $user->update(['medical_certificate_path' => "/storage/{$path}"]);
            $this->medicalCertificate = null;
        }

        if ($this->parentalConsent !== null) {
            if ($user->parental_consent_path) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->parental_consent_path));
            }

            $extension = $this->parentalConsent->getClientOriginalExtension();
            $path = $this->parentalConsent->storeAs("documents/{$user->id}", "parental_consent.{$extension}", 'public');

            $user->update(['parental_consent_path' => "/storage/{$path}"]);
            $this->parentalConsent = null;
        }
    }
};
