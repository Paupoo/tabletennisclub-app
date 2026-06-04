<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Gender;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasPhotoUpload;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithFileUploads, HasBreadcrumbs, HasPhotoUpload;

    public User $user;

    public bool $drawer = false;

    public string $activeTeamTab = '';

    // Identity
    #[Rule('required|string|max:255')]
    public string $first_name = '';

    #[Rule('required|string|max:255')]
    public string $last_name = '';

    #[Rule('required')]
    public ?Gender $gender = Gender::MEN;

    #[Rule('nullable|date')]
    public ?string $birthdate = null;

    // Contact
    public string $email = '';

    #[Rule('nullable|string|max:20')]
    public ?string $phone_number = null;

    #[Rule('nullable|string|max:255')]
    public ?string $street = null;

    #[Rule('nullable|integer|between:1000,9999')]
    public ?string $city_code = null;

    #[Rule('nullable|string|max:100')]
    public ?string $city_name = null;

    #[Rule('nullable|string|max:20')]
    public ?string $guardian_phone_number = null;

    #[Rule(['nullable', new \App\Domains\Shared\Rules\ValidIban])]
    public ?string $iban = null;

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
        $this->guardian_phone_number = $user->guardian_phone_number;
        $this->iban = $user->iban;
        $this->currentPhoto = $user->photo;
        $this->activeTeamTab = 'team-' . $this->user->teams->first()?->id;
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
            $validated = $this->validate();
        } catch (ValidationException $e) {
            $this->error(__('Please check the form fields.'));
            throw $e;
        }

        unset($validated['photo']);

        $this->handlePhotoUpload($this->user);

        $this->user->update($validated);

        $this->drawer = false;

        $this->success(__('Profile updated.'));
    }

    public function requestErasure(): void
    {
        abort_unless(Auth::user()->is($this->user), 403);

        $this->success(__('Erasure request sent. The admin will process it shortly.'));
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

    public function render(): View
    {
        return $this->view();
    }
};
