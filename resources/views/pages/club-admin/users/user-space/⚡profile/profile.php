<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\Role;
use App\Actions\User\StoreUserDocumentAction;
use App\Actions\User\UpdateUserAction;
use App\Data\User\UpdateUserData;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Rules\ValidIban;
use App\Domains\Shared\Rules\ValidPhone;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasPhotoUpload;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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

    #[Rule(['nullable', 'string', 'max:20', new ValidPhone])]
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

    /**
     * This member's own fines, newest first. Almost always empty — the section
     * renders nothing at all in that case, so it costs no space.
     *
     * @return Collection<int, Fine>
     */
    #[Computed]
    public function fines(): Collection
    {
        return Fine::query()
            ->with('payment')
            ->where('user_id', $this->user->id)
            ->latest()
            ->get();
    }

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);

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
    }

    public function render(): View
    {
        return $this->view();
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

        abort_unless($actor->is($this->user), 403);

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
                is_committee_member: $this->user->hasRole(Role::COMMITTEE->value),
                is_admin: $this->user->hasRole(Role::ADMINISTRATOR->value),
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
        $this->user->loadMissing('teams.league', 'teams.users', 'teams.club', 'teams.season', 'subscriptions.season');

        $activeSubscriptions = $this->user->subscriptions
            ->whereIn('status', ['pending', 'confirmed', 'paid']);

        $currentSeason = Season::current();

        return [
            'genders' => Gender::options(),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'memberSince' => $activeSubscriptions
                ->map(fn (Subscription $subscription) => $subscription->season?->start_at)
                ->filter()
                ->min() ?? $this->user->created_at,
            'currentSeason' => $currentSeason,
            'currentSubscription' => $currentSeason
                ? $activeSubscriptions->firstWhere('season_id', $currentSeason->id)
                : null,
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
            StoreUserDocumentAction::handle($user, $this->medicalCertificate, 'medical');
            $this->medicalCertificate = null;
        }

        if ($this->parentalConsent !== null) {
            StoreUserDocumentAction::handle($user, $this->parentalConsent, 'parental_consent');
            $this->parentalConsent = null;
        }
    }
};
