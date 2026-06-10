<?php

declare(strict_types=1);

use App\Actions\User\AnonymizeUserAction;
use App\Actions\User\CreateUserAction;
use App\Actions\User\SendInvitationAction;
use App\Actions\User\UpdateUserAction;
use App\Data\User\CreateUserData;
use App\Data\User\UpdateUserData;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Gender;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasPhotoUpload;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithFileUploads, HasBreadcrumbs, HasPhotoUpload;

    #[Rule('nullable|date')]
    public ?string $birthdate = null;

    #[Rule('required|integer|between:1000,9999')]
    public string $city_code = '';

    #[Rule('required|string')]
    public string $city_name = '';

    public ?string $committee_role = null;

    #[Rule('required|email')]
    public string $email = '';

    // Personal Info

    #[Rule('required|string')]
    public string $first_name = '';

    #[Rule('required')]
    public ?Gender $gender = Gender::MEN;

    // Permissions

    #[Rule('required|boolean')]
    public bool $is_active = false;

    #[Rule('required|boolean')]
    public bool $is_coach = false;

    public bool $is_admin = false;

    #[Rule('required|boolean')]
    public bool $is_committee_member = false;

    // Registration

    #[Rule('required|boolean')]
    public bool $is_competitor = false;

    #[Rule('required|string')]
    public string $last_name = '';

    #[Validate()]
    public ?string $licence = null;

    #[Rule('nullable|string')]
    public ?string $licence_type = null;

    #[Rule(['nullable', new \App\Domains\Shared\Rules\ValidIban])]
    public ?string $iban = null;

    // Guardian (legal representatives for minors)

    /** @var array<int> Linked guardian ids (source of truth, synced on save). */
    public array $guardianIds = [];

    public string $guardianSearch = '';

    public bool $showGuardianForm = false;

    public string $guardianFirstName = '';

    public string $guardianLastName = '';

    public string $guardianPhone = '';

    public ?string $guardianEmail = null;

    public ?string $guardianIban = null;

    // Security
    #[Validate()]
    public string $password = '';

    public string $password_confirmation = '';

    #[Rule('required|string')]
    public string $phone_number = '';

    public ?string $ranking = null;

    #[Rule('required|string')]
    public string $street = '';

    public ?User $user = null;

    public bool $anonymizeModal = false;

    public string $anonymizeConfirmText = '';

    #[Computed()]
    public function CommitteeRoleOptions(): array
    {
        return CommitteeRolesEnum::getOptions();
    }

    /**
     * Whether the currently entered birthdate makes the member a minor (< 18y).
     */
    #[Computed()]
    public function isMinor(): bool
    {
        return $this->birthdate !== null
            && $this->birthdate !== ''
            && Carbon::parse($this->birthdate)->age < 18;
    }

    /**
     * Guardians currently linked to the member (from in-memory selection).
     *
     * @return \Illuminate\Support\Collection<int, Guardian>
     */
    #[Computed()]
    public function linkedGuardians(): \Illuminate\Support\Collection
    {
        if ($this->guardianIds === []) {
            return collect();
        }

        return Guardian::whereIn('id', $this->guardianIds)->get();
    }

    /**
     * Existing guardians matching the search box, excluding already-linked ones.
     *
     * @return \Illuminate\Support\Collection<int, Guardian>
     */
    #[Computed()]
    public function guardianSearchResults(): \Illuminate\Support\Collection
    {
        $term = trim($this->guardianSearch);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Guardian::query()
            ->whereNotIn('id', $this->guardianIds)
            ->where(function ($query) use ($term): void {
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('last_name')
            ->limit(8)
            ->get();
    }

    /**
     * Adult club members matching the search box, who can be linked as a guardian.
     * Excludes the member being edited, minors, and members already a guardian.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    #[Computed()]
    public function memberSearchResults(): \Illuminate\Support\Collection
    {
        $term = trim($this->guardianSearch);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return User::query()
            ->when($this->user?->exists, fn ($query) => $query->whereKeyNot($this->user->id))
            ->whereNotIn('id', Guardian::whereNotNull('user_id')->pluck('user_id'))
            ->where(function ($query): void {
                $query->whereNull('birthdate')
                    ->orWhereDate('birthdate', '<=', now()->subYears(18));
            })
            ->where(function ($query) use ($term): void {
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderBy('last_name')
            ->limit(8)
            ->get();
    }

    public function attachGuardian(int $guardianId): void
    {
        if (! in_array($guardianId, $this->guardianIds, true)) {
            $this->guardianIds[] = $guardianId;
        }

        $this->guardianSearch = '';
        unset($this->linkedGuardians, $this->guardianSearchResults, $this->memberSearchResults);
    }

    /**
     * Link an existing club member as a guardian: reuse or create a Guardian
     * record pre-filled from the member's data, keyed by user_id.
     */
    public function attachMemberAsGuardian(int $userId): void
    {
        Gate::authorize('create', Guardian::class);

        $member = User::findOrFail($userId);

        $guardian = Guardian::firstOrCreate(
            ['user_id' => $member->id],
            [
                'first_name' => $member->first_name,
                'last_name'  => $member->last_name,
                'phone'      => $member->phone_number,
                'email'      => $member->email,
                'iban'       => $member->iban,
            ],
        );

        if (! in_array($guardian->id, $this->guardianIds, true)) {
            $this->guardianIds[] = $guardian->id;
        }

        $this->guardianSearch = '';
        unset($this->linkedGuardians, $this->guardianSearchResults, $this->memberSearchResults);
    }

    public function detachGuardian(int $guardianId): void
    {
        $this->guardianIds = array_values(
            array_filter($this->guardianIds, fn (int $id): bool => $id !== $guardianId)
        );

        unset($this->linkedGuardians, $this->guardianSearchResults, $this->memberSearchResults);
    }

    public function createGuardian(): void
    {
        Gate::authorize('create', Guardian::class);

        $validated = $this->validate([
            'guardianFirstName' => ['required', 'string', 'max:255'],
            'guardianLastName'  => ['required', 'string', 'max:255'],
            'guardianPhone'     => ['required', 'string', 'max:30'],
            'guardianEmail'     => ['nullable', 'email', 'max:255'],
            'guardianIban'      => ['nullable', new \App\Domains\Shared\Rules\ValidIban],
        ]);

        $guardian = Guardian::create([
            'first_name' => $validated['guardianFirstName'],
            'last_name'  => $validated['guardianLastName'],
            'phone'      => $validated['guardianPhone'],
            'email'      => $validated['guardianEmail'] ?? null,
            'iban'       => $validated['guardianIban'] ?? null,
        ]);

        $this->guardianIds[] = $guardian->id;

        $this->reset([
            'guardianFirstName',
            'guardianLastName',
            'guardianPhone',
            'guardianEmail',
            'guardianIban',
            'showGuardianForm',
        ]);

        unset($this->linkedGuardians, $this->guardianSearchResults, $this->memberSearchResults);

        $this->success(__('Guardian added and linked.'));
    }

    public function mount(?User $user): void
    {
        if ($user && $user->exists) {
            $this->first_name   = $user->first_name ?? '';
            $this->last_name    = $user->last_name ?? '';
            $this->gender       = $user->gender ?? '';
            $this->email        = $user->email ?? '';
            $this->street       = $user->street ?? '';
            $this->city_code    = $user->city_code ?? '';
            $this->city_name    = $user->city_name ?? '';
            $this->phone_number = $user->phone_number ?? '';
            $this->birthdate = $user->birthdate?->format('Y-m-d');
            $this->iban = $user->iban;
            $this->guardianIds = $user->guardians()->pluck('guardians.id')->all();
            $this->currentPhoto = $user->photo;
            $this->licence_type = $user->is_competitor ? 'competitive' : 'recreative';
            $this->licence = $user->licence;
            $this->ranking = $user->ranking ?? 'NA';
            $this->is_competitor = $user->is_competitor;
            $this->is_active = $user->is_active;
            $this->is_committee_member = $user->is_committee_member;
            $this->is_coach = $user->is_coach;
            $this->is_admin = $user->is_admin;
            $this->committee_role = $user->committee_role?->value;
        }
    }


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->users()
            ->current($this->user?->exists ? __("Edit") : __("Create"));
    }

        public function render(): View
    {
        return $this->view()
            ->title($this->user?->exists
                ? __('Update ') . $this->first_name . ' ' . $this->last_name
                : __('Create new user'));
    }

    // Hook déclenché par wire:model.live à chaque modification du champ
    // public function updatedLicence(?string $value): void
    // {
    //         $this->validateOnly('licence');
    // }

    /**
     * Pour utiliser l'objet Password, on utilise la méthode rules() protégée.
     * Note : Livewire fusionne automatiquement les #[Rule] et cette méthode.
     *
     * @return array{password: array<Password|string>}
     */
    public function rules(): array
    {
        return [
            'committee_role' => [
                'nullable',
                ValidationRule::when($this->is_committee_member, ['required', new Enum(CommitteeRolesEnum::class)]),
            ],
            'licence' => [
                'nullable',
                ValidationRule::when(
                    $this->licence_type === 'competitive',
                    ['required', 'digits:6', ValidationRule::unique('users', 'licence')->ignore($this->user?->id)]
                ),
            ],
            'password' => [
                // Si l'utilisateur existe, on autorise 'nullable', sinon 'required'
                $this->user?->exists
                    ? 'nullable'
                    : 'required',
                'confirmed',
                Password::min(8)->letters()->numbers()->symbols()->uncompromised(),
            ],
            'password_confirmation' => [
                $this->user?->exists
                    ? 'nullable'
                    : 'required',
            ],
            'is_admin' => [
                'required',
                'boolean',
                function ($attribute, $value, $fail): void {
                    $actor = Auth::user();
                    $targetIsAdmin = $this->user?->is_admin ?? false;

                    if ((bool) $value !== $targetIsAdmin && ! $actor?->is_admin) {
                        $fail(__('Only an administrator can change the administrator status.'));

                        return;
                    }

                    if ($this->user?->is_admin && ! $value) {
                        $remainingAdmins = User::where('is_admin', true)->whereKeyNot($this->user->id)->count();

                        if ($remainingAdmins === 0) {
                            $fail(__('Cannot remove the last administrator. Promote another user first.'));
                        }
                    }
                },
            ],
            'photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2024',
            ],
            'ranking' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail): void {
                    $isCompetitive = $this->licence_type === 'competitive' || $this->is_competitor;

                    if ($isCompetitive && empty($value)) {
                        $fail('Ranking is required for competitive players.');

                        return;
                    }

                    if ($isCompetitive && $value === 'NA') {
                        $fail('Ranking N/A is not allowed for competitors.');
                    }
                },
            ],
        ];
    }

    public function confirmAnonymize(): void
    {
        abort_unless(Auth::user()->is_admin && Auth::user()->isNot($this->user), 403);

        if (strtoupper($this->anonymizeConfirmText) !== 'ANONYMIZE') {
            $this->error(__('Type ANONYMIZE to confirm.'));

            return;
        }

        AnonymizeUserAction::handle($this->user);

        $this->anonymizeModal = false;
        $this->anonymizeConfirmText = '';

        $this->success(__('User anonymized. All personal data has been erased.'), redirectTo: route('admin.users.index'));
    }

    public function resendInvitation(): void
    {
        abort_unless($this->user !== null, 404);
        Gate::authorize('update', $this->user);

        SendInvitationAction::handle($this->user);

        $this->success(__('Invitation re-sent to :email.', ['email' => $this->user->email]));
    }

    public function sendPasswordResetLink(): void
    {
        abort_unless($this->user !== null, 404);
        Gate::authorize('updatePassword', $this->user);

        PasswordBroker::sendResetLink(['email' => $this->user->email]);

        $this->success(__('Password reset link sent to :email.', ['email' => $this->user->email]));
    }

    public function save(): void
    {
        try {
            $validated = $this->validate();
        } catch (ValidationException $e) {
            $this->error(
                'Une erreur est survenue. Veuillez vérifier les champs du formulaire.'
            );

            throw $e;
        } catch (Throwable $e) {
            report($e);

            $this->error(
                'Une erreur inattendue est survenue. Veuillez réessayer.'
            );
        }

        // Règles « membre mineur » (droit belge, < 18 ans).
        // Hard block : un mineur sans tuteur légal ne peut pas être affilié (actif).
        // Warn : sinon on enregistre mais on avertit qu'un tuteur manque.
        $minorWithoutGuardian = $this->isMinor && $this->guardianIds === [];

        if ($minorWithoutGuardian && $this->is_active) {
            $this->addError('is_active', __('A minor cannot be set as an active member without a legal guardian. Please add a guardian first.'));
            $this->error(__('A minor cannot be affiliated without a legal guardian.'));

            return;
        }

        $actor = Auth::user();
        $licence = $this->licence_type === 'recreative' ? null : $this->licence;
        $ranking = $this->licence_type === 'recreative' ? 'NA' : $this->ranking;
        $committeeRole = ($this->is_committee_member && $this->committee_role !== null && $this->committee_role !== '')
            ? CommitteeRolesEnum::from($this->committee_role)
            : null;

        if ($this->user) {
            $this->handlePhotoUpload($this->user);

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
                    guardian_phone_number: $this->user->guardian_phone_number,
                    iban: $this->iban,
                    is_active: $this->is_active,
                    is_competitor: $this->is_competitor,
                    is_committee_member: $this->is_committee_member,
                    is_admin: $this->is_admin,
                    is_coach: $this->is_coach,
                    licence: $licence,
                    ranking: $ranking,
                    committee_role: $committeeRole,
                    password: $this->password !== '' ? $this->password : null,
                    guardianIds: $this->guardianIds,
                ),
                $actor,
            );

            if ($minorWithoutGuardian) {
                $this->warning(__('Member saved, but this minor has no legal guardian linked. Please add one.'));

                return;
            }

            $this->success('User ' . $this->user->first_name . ' updated with success', redirectTo: route('admin.users.index'));
        } else {
            $newUser = CreateUserAction::handle(
                new CreateUserData(
                    first_name: $this->first_name,
                    last_name: $this->last_name,
                    email: $this->email,
                    gender: $this->gender,
                    phone_number: $this->phone_number,
                    street: $this->street,
                    city_code: $this->city_code,
                    city_name: $this->city_name,
                    birthdate: $this->birthdate,
                    is_active: $this->is_active,
                    is_competitor: $this->is_competitor,
                    is_committee_member: $this->is_committee_member,
                    is_admin: $this->is_admin,
                    is_coach: $this->is_coach,
                    licence: $licence,
                    ranking: $ranking,
                    committee_role: $committeeRole,
                    password: $this->password !== '' ? $this->password : null,
                    guardianIds: $this->guardianIds,
                ),
                $actor,
            );

            if ($this->photo) {
                $url = $this->photo->store('users', 'public');
                $newUser->update(['photo' => "/storage/{$url}"]);
            }

            if ($minorWithoutGuardian) {
                $this->warning(__('Member created, but this minor has no legal guardian linked. Please add one.'));

                return;
            }

            $this->success('User ' . $newUser->first_name . ' created with success', redirectTo: route('admin.users.index'));
        }
    }

    public function updatedLicenceType(string $value): void
    {
        $this->is_competitor = $value === 'competitive';

        // On nettoie uniquement les erreurs, pas les valeurs
        $this->resetErrorBag(['licence', 'ranking']);
    }

    public function with(): array
    {
        return [
            'licence_types' => collect([['id' => 'recreative', 'name' => __('Recreative')], ['id' => 'competitive', 'name' => __('Competitive')]]),
            'genders' => Gender::options(),
            'rankings' => [['id' => 'NA', 'name' => 'N/A'], ['id' => 'B0', 'name' => 'B0'], ['id' => 'B2', 'name' => 'B2'], ['id' => 'B4', 'name' => 'B4'], ['id' => 'B6', 'name' => 'B6'], ['id' => 'C0', 'name' => 'C0'], ['id' => 'C2', 'name' => 'C2'], ['id' => 'C4', 'name' => 'C4'], ['id' => 'C6', 'name' => 'C6'], ['id' => 'D0', 'name' => 'D0'], ['id' => 'D2', 'name' => 'D2'], ['id' => 'D4', 'name' => 'D4'], ['id' => 'D6', 'name' => 'D6'], ['id' => 'E0', 'name' => 'E0'], ['id' => 'E2', 'name' => 'E2'], ['id' => 'E4', 'name' => 'E4'], ['id' => 'E6', 'name' => 'E6'], ['id' => 'NC', 'name' => 'NC']],
            'quotes' => [
                [
                    'text' => "A stranger is just a friend you haven't met yet.",
                    'author' => 'Will Rogers',
                ],
                [
                    'text' => 'Coming together is a beginning; keeping together is progress; working together is success.',
                    'author' => 'Henry Ford',
                ],
                [
                    'text' => 'Alone we can do so little; together we can do so much.',
                    'author' => 'Helen Keller',
                ],
                [
                    'text' => 'The strength of the team is each individual member. The strength of each member is the team.',
                    'author' => 'Phil Jackson',
                ],
                [
                    'text' => 'Every new friend is a new adventure... the start of more memories.',
                    'author' => 'Patrick Lindsay',
                ],
                [
                    'text' => 'Growth is never by mere chance; it is the result of forces working together.',
                    'author' => 'James Cash Penney',
                ],
                [
                    'text' => "Le plus beau métier d'homme est le métier d'unir les hommes.",
                    'author' => 'Antoine de Saint-Exupéry',
                ],
                [
                    'text' => 'Chacun est responsable de tous. Chacun est seul responsable de tous.',
                    'author' => 'Antoine de Saint-Exupéry',
                ],
                [
                    'text' => 'On ne peut rien faire sans les autres.',
                    'author' => 'Paul Éluard',
                ],
                [
                    'text' => "La fraternité n'est qu'une vaine lueur si elle n'est pas une action.",
                    'author' => 'Albert Camus',
                ],
                [
                    'text' => "Le sport est une causerie entre le corps et l'esprit, mais le club est une conversation entre les hommes.",
                    'author' => 'Jean Giraudoux',
                ],
                [
                    'text' => "Le sport, c'est l'école de la solidarité et de la fraternité.",
                    'author' => 'Abdou Diouf',
                ],
                [
                    'text' => "Dans une équipe, il n'y a pas de passagers, il n'y a qu'un équipage.",
                    'author' => 'Aimé Jacquet',
                ],
                [
                    'text' => "Le sport n'est pas seulement une affaire de muscles, c'est une affaire de cœur et de partage.",
                    'author' => 'Guy Drut',
                ],
                [
                    'text' => "L'esprit d'équipe, c'est des hommes qui se respectent et qui se font confiance.",
                    'author' => 'Bernard Laporte',
                ],
            ],
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

};
