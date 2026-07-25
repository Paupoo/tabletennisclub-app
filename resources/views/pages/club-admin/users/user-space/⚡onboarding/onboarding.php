<?php

declare(strict_types=1);

use App\Actions\User\StoreUserDocumentAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Rules\ValidIban;
use App\Livewire\Concerns\HasPhotoUpload;
use App\Livewire\Concerns\ManagesGuardians;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use HasPhotoUpload, ManagesGuardians, Toast, WithFileUploads;

    // ── Step 1 — Identity ────────────────────────────────────────────────────

    public ?string $birthdate = null;

    // ── Step 2 — Address ─────────────────────────────────────────────────────

    public ?string $city_code = null;

    public string $city_name = '';

    public ?Gender $gender = null;

    // ── Step 3 — Optional extras ─────────────────────────────────────────────

    public ?string $iban = null;

    // ── Navigation ───────────────────────────────────────────────────────────

    public int $maxReachable = 1;

    public $medicalCertificate = null;

    public $parentalConsent = null;

    public string $phone_number = '';

    /** Fallback path of step 2: looking up a guardian already known to the club. */
    public bool $showGuardianSearch = false;

    public int $step = 1;

    public string $street = '';

    public User $user;

    public function completeAddressStep(): void
    {
        $this->validate([
            'street' => ['required', 'string', 'max:255'],
            'city_code' => ['required', 'integer', 'between:1000,9999'],
            'city_name' => ['required', 'string', 'max:100'],
        ]);

        $this->user->update([
            'street' => $this->street,
            'city_code' => $this->city_code,
            'city_name' => $this->city_name,
        ]);

        $this->maxReachable = max($this->maxReachable, 4);
        $this->step = 4;
    }

    public function completeGuardianStep(): void
    {
        if ($this->guardianIds === []) {
            $this->error(__('A legal guardian is required before you can continue.'));

            return;
        }

        $this->user->guardians()->sync($this->guardianIds);

        $this->maxReachable = max($this->maxReachable, 3);
        $this->step = 3;
    }

    public function completeIdentityStep(): void
    {
        $this->validate([
            'gender' => ['required'],
            'birthdate' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'phone_number' => ['required', 'string', 'max:20'],
        ]);

        $this->user->update([
            'gender' => $this->gender,
            'birthdate' => $this->birthdate,
            'phone_number' => $this->phone_number,
        ]);

        $next = $this->isMinor ? 2 : 3;
        $this->maxReachable = max($this->maxReachable, $next);
        $this->step = $next;
    }

    public function finish(): void
    {
        $this->validate([
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2024'],
            'iban' => ['nullable', new ValidIban],
            'medicalCertificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'parentalConsent' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        $this->handlePhotoUpload($this->user);

        if ($this->medicalCertificate !== null) {
            StoreUserDocumentAction::handle($this->user, $this->medicalCertificate, 'medical');
            $this->medicalCertificate = null;
        }

        if ($this->parentalConsent !== null) {
            StoreUserDocumentAction::handle($this->user, $this->parentalConsent, 'parental_consent');
            $this->parentalConsent = null;
        }

        if (filled($this->iban)) {
            $this->user->update(['iban' => $this->iban]);
        }

        $this->leaveWizard();
    }

    public function goToStep(int $target): void
    {
        if ($target === 2 && ! $this->isMinor) {
            return;
        }

        if ($target <= $this->maxReachable) {
            $this->step = $target;
        }
    }

    /**
     * Whether the member is a minor (< 18y) based on the entered birthdate.
     * Drives whether the parental consent upload is shown on the last step.
     */
    #[Computed]
    public function isMinor(): bool
    {
        return filled($this->birthdate) && Carbon::parse($this->birthdate)->age < 18;
    }

    public function mount(): void
    {
        $this->user = Auth::user();

        $this->gender = $this->user->gender;
        $this->birthdate = $this->user->birthdate?->format('Y-m-d');
        $this->phone_number = $this->user->phone_number ?? '';
        $this->street = $this->user->street ?? '';
        $this->city_code = $this->user->city_code;
        $this->city_name = $this->user->city_name ?? '';
        $this->iban = $this->user->iban;
        $this->currentPhoto = $this->user->photo;
        $this->guardianIds = $this->user->guardians()->pluck('guardians.id')->all();

        // Creating the guardian is the common path here — a newcomer's parent is
        // rarely already on file — so the form opens straight away.
        $this->showGuardianForm = $this->guardianIds === [];

        // Resume where the member left off: already-filled steps stay reachable.
        $identityComplete = $this->user->birthdate !== null
            && filled($this->user->phone_number);
        $addressComplete = filled($this->user->street)
            && filled($this->user->city_code)
            && filled($this->user->city_name);
        $guardianSatisfied = ! $this->user->requiresGuardian();

        $this->maxReachable = match (true) {
            ! $identityComplete => 1,
            ! $guardianSatisfied => 2,
            ! $addressComplete => 3,
            default => 4,
        };

        $this->step = $this->maxReachable;
    }

    public function render(): View
    {
        return $this->view()->layout('layouts.onboarding');
    }

    public function skipOptionalStep(): void
    {
        $this->leaveWizard();
    }

    public function with(): array
    {
        return [
            'genders' => Gender::options(),
        ];
    }

    /**
     * Here the family fills the form itself and always knows the guardian's
     * address, which the club needs for authorisations and payment reminders.
     * The admin form keeps it optional: the secretary often encodes a guardian
     * over the phone, with no address to hand.
     *
     * @return array<int, mixed>
     */
    protected function guardianEmailRules(): array
    {
        return ['required', 'email', 'max:255'];
    }

    private function leaveWizard(): void
    {
        session()->flash('success', __('Your profile is now complete. Welcome to the club!'));

        $this->redirectRoute('dashboard');
    }
};
