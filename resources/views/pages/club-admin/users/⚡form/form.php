<?php

declare(strict_types=1);

use App\Actions\User\AnonymizeUserAction;
use App\Actions\User\CreateUserAction;
use App\Actions\User\SendInvitationAction;
use App\Actions\User\UpdateUserAction;
use App\Data\User\CreateUserData;
use App\Data\User\UpdateUserData;
use App\Domains\ClubAdmin\Users\Models\FamilyGroup;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Rules\ValidIban;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasPhotoUpload;
use App\Livewire\Concerns\ManagesGuardians;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasPhotoUpload, ManagesGuardians, Toast, WithFileUploads;

    public string $anonymizeConfirmText = '';

    public bool $anonymizeModal = false;

    #[Rule('nullable|date')]
    public ?string $birthdate = null;

    #[Rule('required|integer|between:1000,9999')]
    public string $city_code = '';

    #[Rule('required|string')]
    public string $city_name = '';

    public ?string $committee_role = null;

    /**
     * Délégations held, as Role values. Duties, not a statutory title: they may be
     * handed to anyone, committee member or not, and they stack.
     *
     * The rule keeps the payload well-formed; SyncUserRolesAction is what refuses
     * a base role slipped in here, since that is a rule about the domain rather
     * than about this form.
     *
     * @var array<int, string>
     */
    #[Rule(['array'])]
    public array $delegations = [];

    #[Validate()]
    public string $email = '';

    public ?int $existingFamilyGroupId = null;

    // Family

    public string $familySearch = '';

    /** @var array<int> Linked family member ids (source of truth, synced on save). */
    public array $familyMemberIds = [];

    // Personal Info

    #[Rule('required|string')]
    public string $first_name = '';

    #[Rule('required')]
    public ?Gender $gender = Gender::MEN;

    // Club equipment

    #[Rule('required|boolean')]
    public bool $has_key = false;

    #[Rule(['nullable', new ValidIban])]
    public ?string $iban = null;

    public bool $is_admin = false;

    // Permissions

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

    /**
     * The family group the edited user is (or will be) part of: their own group,
     * or the group of an already-linked member when they are joining an existing
     * family. Null while no family is established on either side.
     */
    private function allowedFamilyGroupId(): ?int
    {
        if ($this->existingFamilyGroupId !== null) {
            return $this->existingFamilyGroupId;
        }

        if ($this->familyMemberIds === []) {
            return null;
        }

        return FamilyGroup::query()
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $this->familyMemberIds))
            ->value('id');
    }

    public function attachFamilyMember(int $userId): void
    {
        $candidate = User::findOrFail($userId);

        if (FamilyGroup::conflictsWith($candidate, $this->allowedFamilyGroupId())) {
            $this->error(__(':name is already part of another family. Remove them from their current family first.', ['name' => $candidate->first_name . ' ' . $candidate->last_name]));

            return;
        }

        // Joining someone who already has a family links the whole family.
        $idsToLink = [$userId, ...$candidate->familyMembers()->pluck('id')->all()];

        foreach ($idsToLink as $idToLink) {
            if ($idToLink !== $this->user?->id && ! in_array($idToLink, $this->familyMemberIds, true)) {
                $this->familyMemberIds[] = $idToLink;
            }
        }

        $this->familySearch = '';
        unset($this->familyMembers, $this->familySearchResults);
    }

    #[Computed()]
    public function CommitteeRoleOptions(): array
    {
        return CommitteeRolesEnum::getOptions();
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    #[Computed()]
    public function delegationOptions(): array
    {
        return array_map(static fn (Role $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
        ], Role::delegations());
    }

    /**
     * Pre-checks the duties usually handed over with a statutory title.
     *
     * A suggestion, never an automatism: the admin can uncheck any of them, and a
     * treasurer who does not handle the cash box is a legitimate situation. Only
     * adds — unchecking something then picking another title must not bring it back.
     */
    public function updatedCommitteeRole(): void
    {
        if ($this->committee_role === null || $this->committee_role === '') {
            return;
        }

        $title = CommitteeRolesEnum::tryFrom($this->committee_role);

        if (! $title instanceof CommitteeRolesEnum) {
            return;
        }

        $suggested = array_map(
            static fn (Role $role): string => $role->value,
            Role::suggestedFor($title),
        );

        $this->delegations = array_values(array_unique([...$this->delegations, ...$suggested]));
    }

    public function confirmAnonymize(): void
    {
        Gate::authorize('anonymize', $this->user);

        if (strtoupper($this->anonymizeConfirmText) !== 'ANONYMIZE') {
            $this->error(__('Type ANONYMIZE to confirm.'));

            return;
        }

        AnonymizeUserAction::handle($this->user);

        $this->anonymizeModal = false;
        $this->anonymizeConfirmText = '';

        $this->success(__('User anonymized. All personal data has been erased.'), redirectTo: route('admin.users.index'));
    }

    public function detachFamilyMember(int $userId): void
    {
        $this->familyMemberIds = array_values(
            array_filter($this->familyMemberIds, fn (int $id): bool => $id !== $userId)
        );

        unset($this->familyMembers, $this->familySearchResults);
    }

    /**
     * Family members currently linked to the user (from in-memory selection).
     *
     * @return Collection<int, User>
     */
    #[Computed()]
    public function familyMembers(): Collection
    {
        if ($this->familyMemberIds === []) {
            return collect();
        }

        return User::whereIn('id', $this->familyMemberIds)->get();
    }

    /**
     * Club members matching the family search box, excluding the member being
     * edited and already-linked family members. Any age is eligible.
     *
     * @return Collection<int, User>
     */
    #[Computed()]
    public function familySearchResults(): Collection
    {
        $term = trim($this->familySearch);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return User::query()
            ->when($this->user?->exists, fn ($query) => $query->whereKeyNot($this->user->id))
            ->whereNotIn('id', $this->familyMemberIds)
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
     * Whether the competitive/recreative toggle can be persisted. The licence
     * type is stored on the member's subscription for the active season (see
     * UpdateUserAction), so without such a subscription there is nowhere to
     * save it and the toggle must be locked. Creation always allows it.
     */
    #[Computed()]
    public function canEditLicenceType(): bool
    {
        if (! $this->user?->exists) {
            return true;
        }

        $seasonId = Season::current()?->id;

        if ($seasonId === null) {
            return false;
        }

        return $this->user->subscriptions()->where('season_id', $seasonId)->affiliated()->exists();
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

    public function mount(?User $user): void
    {
        // Defense in depth: the route already gates this behind the committee
        // middleware, but guard the component itself in case it is mounted elsewhere.
        Gate::authorize($user?->exists ? 'update' : 'create', $user ?? User::class);

        if ($user && $user->exists) {
            $this->first_name = $user->first_name ?? '';
            $this->last_name = $user->last_name ?? '';
            $this->gender = $user->gender ?? '';
            $this->email = $user->email ?? '';
            $this->street = $user->street ?? '';
            $this->city_code = $user->city_code ?? '';
            $this->city_name = $user->city_name ?? '';
            $this->phone_number = $user->phone_number ?? '';
            $this->birthdate = $user->birthdate?->format('Y-m-d');
            $this->iban = $user->iban;
            $this->guardianIds = $user->guardians()->pluck('guardians.id')->all();
            $this->existingFamilyGroupId = $user->familyGroups()->first()?->id;
            $this->familyMemberIds = $user->familyMembers()->pluck('id')->all();
            $this->currentPhoto = $user->photo;
            $this->licence_type = $user->is_competitor ? 'competitive' : 'recreative';
            $this->licence = $user->licence;
            $this->ranking = $user->ranking ?? 'NA';
            $this->is_competitor = $user->is_competitor;
            $this->is_committee_member = $user->hasRole(Role::COMMITTEE->value);
            $this->is_admin = $user->hasRole(Role::ADMINISTRATOR->value);
            $this->has_key = (bool) ($user->has_key ?? false);
            $this->committee_role = $user->committee_role?->value;
            $this->delegations = $user->roles
                ->pluck('name')
                ->filter(static fn (string $name): bool => Role::tryFrom($name)?->isDelegation() ?? false)
                ->values()
                ->all();
        }
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->user?->exists
                ? __('Update ') . $this->first_name . ' ' . $this->last_name
                : __('Create new user'));
    }

    public function resendInvitation(): void
    {
        abort_unless($this->user !== null, 404);
        Gate::authorize('update', $this->user);

        SendInvitationAction::handle($this->user);

        $this->success(__('Invitation re-sent to :email.', ['email' => $this->user->email]));
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
            'email' => [
                'required',
                'email',
                ValidationRule::unique('users', 'email')->ignore($this->user?->id),
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
                Password::defaults(),
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
                    $targetIsAdmin = $this->user?->hasRole(Role::ADMINISTRATOR->value) ?? false;

                    if ((bool) $value !== $targetIsAdmin && ! $actor?->hasRole(Role::ADMINISTRATOR->value)) {
                        $fail(__('Only an administrator can change the administrator status.'));

                        return;
                    }

                    if ($targetIsAdmin && ! $value) {
                        $remainingAdmins = User::role(Role::ADMINISTRATOR->value)->whereKeyNot($this->user->id)->count();

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

    public function save(): void
    {
        Gate::authorize($this->user?->exists ? 'update' : 'create', $this->user ?? User::class);

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

        $allowedFamilyGroupId = $this->allowedFamilyGroupId();

        foreach ($this->familyMemberIds as $familyMemberId) {
            $candidate = User::find($familyMemberId);

            if ($candidate && FamilyGroup::conflictsWith($candidate, $allowedFamilyGroupId)) {
                $this->error(__(':name is already part of another family. Remove them from their current family first.', ['name' => $candidate->first_name . ' ' . $candidate->last_name]));

                return;
            }
        }

        $minorWithoutGuardian = $this->isMinor && $this->guardianIds === [];

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
                    is_competitor: $this->is_competitor,
                    is_committee_member: $this->is_committee_member,
                    is_admin: $this->is_admin,
                    has_key: $this->has_key,
                    licence: $licence,
                    ranking: $ranking,
                    committee_role: $committeeRole,
                    password: $this->password !== '' ? $this->password : null,
                    guardianIds: $this->guardianIds,
                    familyMemberIds: $this->familyMemberIds,
                    delegations: $this->delegations,
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
                    is_committee_member: $this->is_committee_member,
                    is_admin: $this->is_admin,
                    has_key: $this->has_key,
                    licence: $licence,
                    ranking: $ranking,
                    committee_role: $committeeRole,
                    password: $this->password !== '' ? $this->password : null,
                    guardianIds: $this->guardianIds,
                    familyMemberIds: $this->familyMemberIds,
                    delegations: $this->delegations,
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

    public function sendPasswordResetLink(): void
    {
        abort_unless($this->user !== null, 404);
        Gate::authorize('updatePassword', $this->user);

        PasswordBroker::sendResetLink(['email' => $this->user->email]);

        $this->success(__('Password reset link sent to :email.', ['email' => $this->user->email]));
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

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->users()
            ->current($this->user?->exists ? __('Edit') : __('Create'));
    }
};
