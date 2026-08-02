<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait HasPhotoUpload
{
    public ?string $currentPhoto = null;

    public bool $deleteModal = false;

    public int $imageKey = 0;

    public $photo;

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

    protected function handlePhotoUpload(User $user): void
    {
        if (! $this->photo instanceof TemporaryUploadedFile) {
            return;
        }

        if ($user->photo) {
            $oldPath = str_replace('/storage/', '', $user->photo);
            Storage::disk('public')->delete($oldPath);
        }

        $path = $this->photo->store('users', 'public');

        $user->update(['photo' => "/storage/{$path}"]);

        $this->currentPhoto = "/storage/{$path}";
        $this->photo = null;
    }
}
