<?php

declare(strict_types=1);

use App\Enums\CommitteeRolesEnum;
use App\Enums\Gender;
use App\Models\ClubAdmin\Users\User;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
use PHPUnit\Event\Code\Throwable;

new class extends Component
{
    use Toast, WithFileUploads;

    #[Rule('nullable')]
    public Carbon $birthdate;

    #[Rule('required|integer|between:1000,9999')]
    public string $city_code = '';

    #[Rule('required|string')]
    public string $city_name = '';

    public ?string $currentPhoto = null; // photo persistée

    public bool $deleteModal = false;

    #[Rule('required|email')]
    public string $email = '';

    // Personal Info

    #[Rule('required|string')]
    public string $first_name = '';

    #[Rule('required')]
    public ?Gender $gender = Gender::MEN;

    public int $imageKey = 0; // pour gérer l'état de la photo et son delete en JS

    // Permissions

    #[Rule('required|boolean')]
    public bool $is_active = false;

    #[Rule('required|boolean')]
    public bool $is_admin = false;

    #[Rule('required|boolean')]
    public bool $is_committee_member = false;

    public ?string $committee_role = null;

    // Registration

    #[Rule('required|boolean')]
    public bool $is_competitor = false;

    #[Rule('required|string')]
    public string $last_name = '';

    #[Validate()]
    public ?string $licence = null;

    #[Rule('nullable|string')]
    public ?string $licence_type = null;

    #[Rule('nullable|string')]
    public ?string $parent_phone_number = null;

    // Security
    #[Validate()]
    public string $password = '';

    public string $password_confirmation = '';

    #[Rule('required|string')]
    public string $phone_number = '';

    public $photo = null;          // upload Livewire uniquement

    public ?string $ranking = null;

    #[Rule('required|string')]
    public string $street = '';

    public array $trainings_ids = [];

    public ?User $user = null;

    /**
     * Effacer la photo
     */
    public function deletePhoto(): void
    {
        if (! $this->user || ! $this->user->photo) {
            return;
        }

        $oldPath = str_replace('/storage/', '', $this->user->photo);
        Storage::disk('public')->delete($oldPath);

        $this->user->update(['photo' => null]);

        $this->currentPhoto = null;
        $this->photo = null;

        $this->imageKey++;
        $this->deleteModal = false;

        $this->success(__('Photo deleted'));
    }

    public function mount(?User $user): void
    {
        if ($user && $user->exists) {
            $this->first_name = $user->first_name;
            $this->last_name = $user->last_name;
            $this->gender = $user->gender;
            $this->email = $user->email;
            $this->street = $user->street;
            $this->city_code = $user->city_code;
            $this->city_name = $user->city_name;
            $this->phone_number = $user->phone_number;
            $this->birthdate = $user->birthdate;
            $this->parent_phone_number = $user->parent_phone_number;
            $this->currentPhoto = $user->photo;
            $this->licence_type = $user->is_competitor ? 'competitive' : 'recreative';
            $this->licence = $user->licence;
            $this->ranking = $user->ranking ?? 'N/A';
            $this->is_competitor = $user->is_competitor;
            $this->is_active = $user->is_active;
            $this->is_committee_member = $user->is_committee_member;
            $this->is_admin = $user->is_admin;
            $this->committee_role = $user->committee_role?->value;
        }
    }

    public function render(): View
    {
        return $this->view()
            ->title($this->user?->exists
                ? __('Update ') . $this->first_name . ' ' . $this->last_name
                : __('Create new user'));
    }

    public function updatedLicenceType(string $value): void
{
    $this->is_competitor = $value === 'competitive';

    // On nettoie uniquement les erreurs, pas les valeurs
    $this->resetErrorBag(['licence', 'ranking']);
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
                ValidationRule::when($this->is_committee_member, ['required', new Enum(CommitteeRolesEnum::class)])
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
            'photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2024',
            ],
            'ranking' => [
                'string',
                function ($attribute, $value, $fail) {

                    $isCompetitive = $this->licence_type === 'competitive' || $this->is_competitor;

                    // obligatoire si compétitif
                    if ($isCompetitive && empty($value)) {
                        $fail('Ranking is required for competitive players.');
                        return;
                    }

                    // interdiction de NA si compétitif
                    if ($isCompetitive && $value === 'NA') {
                        $fail('Ranking N/A is not allowed for competitors.');
                    }
                },
            ],
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
        }

        // Ici on est certain que $validated existe et est valide
        if ($this->licence_type === 'recreative') {
            $validated['licence'] = null;
            $validated['ranking'] = 'N/A';        }

        if ($this->user) {
            unset($validated['password_confirmation']);
            unset($validated['photo']);

            // $validated['password'] = Hash::make($validated['password']);

            $this->handlePhotoUpload($this->user);

            if (! empty($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']); // Ne pas écraser le mot de passe s'il est vide
            }

            $this->user->update($validated);

            $this->success('User ' . $this->user->first_name . ' created with success', redirectTo: route('admin.users.index'));
        } else {
            unset($validated['password_confirmation']);
            unset($validated['photo']);

            $validated['password'] = Hash::make($validated['password']);

            $newUser = User::create($validated);
            if ($this->photo) {
                $url = $this->photo->store('users', 'public');
                $newUser->update(['photo' => "/storage/{$url}"]);
            }

            $this->success('User ' . $newUser->first_name . ' created with success', redirectTo: route('admin.users.index'));
        }
    }

    #[Computed()]
    public function CommitteeRoleOptions(): array
    {
        return CommitteeRolesEnum::getOptions();
    }

    public function with(): array
    {
        return [
            'licence_types' => collect([['id' => 'recreative', 'name' => __('Recreative')], ['id' => 'competitive', 'name' => __('Competitive')]]),
            'genders' => Gender::options(),
            'rankings' => [['id' => 'NA', 'name' => 'N/A'], ['id' => 'B0', 'name' => 'B0'], ['id' => 'B2', 'name' => 'B2'], ['id' => 'B4', 'name' => 'B4'], ['id' => 'B6', 'name' => 'B6'], ['id' => 'C0', 'name' => 'C0'], ['id' => 'C2', 'name' => 'C2'], ['id' => 'C4', 'name' => 'C4'], ['id' => 'C6', 'name' => 'C6'], ['id' => 'D0', 'name' => 'D0'], ['id' => 'D2', 'name' => 'D2'], ['id' => 'D4', 'name' => 'D4'], ['id' => 'D6', 'name' => 'D6'], ['id' => 'E0', 'name' => 'E0'], ['id' => 'E2', 'name' => 'E2'], ['id' => 'E4', 'name' => 'E4'], ['id' => 'E6', 'name' => 'E6'], ['id' => 'NC', 'name' => 'NC']],
            'trainings' => collect([
                [
                    'id' => 1,
                    'day' => __('Monday'),
                    'group' => __('Free'),
                    'availablePlaces' => null,
                ],
                [
                    'id' => 2,
                    'day' => __('Monday'),
                    'group' => __('Directed - Starters'),
                    'availablePlaces' => '2',
                ],
                // [
                //     'id' => 3,
                //     'day' => __('Tuesday'),
                //     'group' => __('Directed - Advanced'),
                //     'availablePlaces' => 0
                // ],
                [
                    'id' => 4,
                    'day' => __('Wednesday'),
                    'group' => __('Directed - Kids'),
                    'availablePlaces' => 3,
                ],
                [
                    'id' => 5,
                    'day' => __('Wednesday'),
                    'group' => __('Directed - Kids'),
                    'availablePlaces' => 1,
                ],
                // [
                //     'id' => 6,
                //     'day' => __('Saturday'),
                //     'group' => __('Directed - Starters'),
                //     'availablePlaces' => 0
                // ],
                [
                    'id' => 7,
                    'day' => __('Saturday'),
                    'group' => __('Directed - Advanced'),
                    'availablePlaces' => 4,
                ],
            ]),
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
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->users()
                ->current($this->user?->exists ? __('Edit') : __('Create'))
                ->toArray(),
        ];
    }

    /**
     * Gère l'upload et la suppression de l'ancienne image
     */
    protected function handlePhotoUpload(User $user): void
    {
        if (! $this->photo instanceof TemporaryUploadedFile) {
            return;
        }

        // supprimer ancienne
        if ($user->photo) {
            $oldPath = str_replace('/storage/', '', $user->photo);
            Storage::disk('public')->delete($oldPath);
        }

        // stocker nouvelle
        $path = $this->photo->store('users', 'public');

        $user->update([
            'photo' => "/storage/{$path}",
        ]);

        $this->currentPhoto = "/storage/{$path}";
        $this->photo = null;
    }
};
