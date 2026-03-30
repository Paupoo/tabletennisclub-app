<?php

use App\Models\ClubAdmin\Users\User;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithFileUploads;

    public User $user;

    public bool $drawer = false;

    public bool $deleteModal = false;

    public string $activeTeamTab;

    public int $imageKey = 0; // pour g\C3\A9rer l'\C3\A9tat de la photo et son delete en JS

    #[Rule('required|email')]
    public string $email;

    #[Rule('required|string')]
    public string $street;

    #[Rule('required|integer|between:1000,9999')]
    public string $city_code;

    #[Rule('required|string')]
    public string $city_name;

    #[Rule('required|string')]
    public string $phone_number;

    #[Rule('string|nullable')]
    public ?string $guardian_phone_number = null;

    public $photo = null;          // upload Livewire uniquement

    public ?string $currentPhoto = null; // photo persist\C3\A9e

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

    /**
     * G\C3\A8re l'upload et la suppression de l'ancienne image
     */
    protected function handlePhotoUpload(User $user): void
    {
        if (! $this->photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
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
            'photo' => "/storage/$path",
        ]);

        $this->currentPhoto = "/storage/$path";
        $this->photo = null;
    }

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->email = $user->email;
        $this->street = $user->street;
        $this->city_code = $user->city_code;
        $this->city_name = $user->city_name;
        $this->phone_number = $user->phone_number;
        $this->guardian_phone_number = $user->guardian_phone_number;
        $this->currentPhoto = $user->photo;
        $this->activeTeamTab = 'team-' . $this->user->teams->first()?->id;
        
    }

    /**
     * Complex rules
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2024',
            ],
        ];
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()->home()->add(__('My profile'), null, null)->toArray(),
        ];
    }

    public function render(): View
    {
        return view('pages.club-admin.users.user-space.⚡profile.profile', $this->user);
    }

    public function save(): void
    {
        try {
            $validated = $this->validate();

            // logique normale...

        } catch (ValidationException $e) {

            $this->error(
                'Une erreur est survenue. Veuillez v\C3\A9rifier les champs du formulaire.'
            );

            throw $e; // important pour conserver l'affichage des erreurs sous les champs
        } catch (Throwable $e) {

            report($e);

            $this->error(
                'Une erreur inattendue est survenue. Veuillez r\C3\A9essayer.'
            );
        }

        if ($this->user) {
            unset($validated['photo']);

            $this->handlePhotoUpload($this->user);

            $this->user->update($validated);

            $this->drawer = false;

            $this->success('User '.$this->user->first_name.' created with success');
        }
    }
}