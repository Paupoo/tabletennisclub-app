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
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Rules\ValidIban;
use App\Domains\Shared\Rules\ValidPhone;
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

    /**
     * The member's own address, and therefore their login. Null is a normal
     * state, not an incomplete one: a child is reached through their guardian.
     */
    #[Validate()]
    public ?string $email = null;

    public ?int $existingFamilyGroupId = null;

    /** @var array<int> Linked family member ids (source of truth, synced on save). */
    public array $familyMemberIds = [];

    // Family

    public string $familySearch = '';

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

    #[Rule('required|string')]
    public string $last_name = '';

    #[Validate()]
    public ?string $licence = null;

    // Security
    #[Validate()]
    public string $password = '';

    public string $password_confirmation = '';

    #[Validate()]
    public string $phone_number = '';

    public ?string $ranking = null;

    #[Rule('required|string')]
    public string $street = '';

    public ?User $user = null;

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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // The default message names the field and stops there, which leaves the
            // secretary encoding a child with no idea that linking a guardian is
            // what lifts the requirement.
            'email.required' => __('Enter an address, or link a guardian: a member without an address is reached through their guardian.'),
            'phone_number.required' => __('Enter a number, or link a guardian: a member without a number is reached through their guardian.'),
        ];
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
            $this->email = $user->email;
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
            $this->licence = $user->licence;
            $this->ranking = $user->ranking ?? Ranking::NA->value;
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
        // Inviting is its own right, not a side effect of being allowed to edit.
        Gate::authorize('sendEmail', User::class);

        if (! SendInvitationAction::handle($this->user)) {
            $this->error(__('This member has no address of their own yet, so they cannot be invited.'));

            return;
        }

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
            // An address identifies a login, and a member reached through their
            // guardian has none — so it is the guardian, not the age, that lifts
            // the requirement. Asking for a birthdate instead would leave a child
            // encoded without one blocked for a reason nobody could act on.
            'email' => [
                $this->guardianIds === [] ? 'required' : 'nullable',
                'email',
                ValidationRule::unique('users', 'email')->ignore($this->user?->id),
            ],
            'licence' => [
                'nullable',
                'digits:6',
                ValidationRule::unique('users', 'licence')->ignore($this->user?->id),
            ],
            // Same reasoning as the email address, and the same trigger: the club
            // reaches a member through their guardian or directly, never neither.
            // The guardian form makes their phone number mandatory, so lifting the
            // requirement here never leaves the member unreachable.
            //
            // Unlike the email address the property is a plain string, so a field
            // left alone arrives as '' rather than null. `nullable` would let it
            // through and ValidPhone would still reject it, so the format rules
            // only apply once something has actually been typed.
            'phone_number' => [
                $this->guardianIds === [] ? 'required' : 'nullable',
                ValidationRule::when(filled($this->phone_number), ['string', 'max:20', new ValidPhone]),
            ],
            // Si l'utilisateur existe, on autorise 'nullable', sinon 'required'.
            // Un compte sans adresse n'a pas de login non plus : exiger un mot de
            // passe pour une fiche à laquelle personne ne se connectera bloquerait
            // la création sans rien protéger.
            'password' => [
                $this->needsLogin() ? 'required' : 'nullable',
                'confirmed',
                Password::defaults(),
            ],
            'password_confirmation' => [
                $this->needsLogin() ? 'required' : 'nullable',
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
                ValidationRule::in(array_column(Ranking::cases(), 'value')),
            ],
        ];
    }

    public function save(): void
    {
        Gate::authorize($this->user?->exists ? 'update' : 'create', $this->user ?? User::class);

        // An emptied input arrives as '', and `nullable` only ever short-circuits
        // on null — Livewire does not go through the middleware that converts one
        // into the other. Left as is, `email` would reject the empty string as a
        // malformed address, which is the very refusal this form is meant to drop.
        $this->email = blank($this->email) ? null : trim($this->email);

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
        $licence = $this->licence;
        $ranking = $this->ranking;
        // A member reached through their guardian leaves the field alone, and the
        // property is a plain string, so what arrives is ''. The scope listing
        // incomplete profiles looks for NULL, and an empty string would hide them
        // from the very list meant to surface them.
        $phoneNumber = $this->phone_number !== '' ? $this->phone_number : null;
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
                    phone_number: $phoneNumber,
                    street: $this->street,
                    city_code: $this->city_code,
                    city_name: $this->city_name,
                    birthdate: $this->birthdate,
                    guardian_phone_number: $this->user->guardian_phone_number,
                    iban: $this->iban,
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
                    phone_number: $phoneNumber,
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

        // Not a courtesy check: the broker resolves a null address with
        // `whereNull('email')`, so it hands back whichever managed account comes
        // first and then breaks on a reset token that cannot hold a null address.
        if ($this->user->email === null) {
            $this->error(__('This member has no address of their own yet, so no link can be sent.'));

            return;
        }

        PasswordBroker::sendResetLink(['email' => $this->user->email]);

        $this->success(__('Password reset link sent to :email.', ['email' => $this->user->email]));
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

    public function with(): array
    {
        return [
            'genders' => Gender::options(),
            'rankings' => Ranking::options(),
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

    /**
     * Whether this save has to hand the member a way in.
     *
     * Only a member being created with an address of their own: an existing one
     * already has whatever password they were given, and a member without an
     * address has no login to protect in the first place.
     */
    private function needsLogin(): bool
    {
        return ! ($this->user?->exists ?? false) && filled($this->email);
    }
};
