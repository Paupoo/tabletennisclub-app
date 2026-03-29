<?php

use App\Models\ClubAdmin\Users\User;
use Illuminate\Support\Collection;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public User $user;
    
    public Collection $theme_options;
    
    public string $theme_choice;

    public function mount(): void
    {
        $this->theme_options = collect([
                ['name' => __('Auto'), 'id' => 'auto', 'icon' => 'o-computer-desktop'],
                ['name' => __('Light'), 'id' => 'light'],
                ['name' => __('Dark'), 'id' => 'dark'],
            ]);

        $this->theme_choice = $this->user->theme ?? 'auto';

    }

    /**
     * Mise à jour du thème
     *
     * @param [type] $value
     * @return void
     */
    public function updatedThemeChoice($value)
    {
        // 1. On sauvegarde en DB pour que ce soit permanent
        $this->user->update(['theme' => $value]);

        // 2. On envoie un événement au navigateur pour changer le thème sans recharger
        $this->dispatch('set-theme', theme: $value);

        $this->success(__('Theme updated'));
    }
};
?>

<div class="col-span-6 grid gap-2 md:col-span-4">
    <div class="grid gap-6 lg:grid-cols-2">
        <x-group :options="$theme_options" class="btn-soft" label="Theme" wire:model.live="theme_choice" />
    </div>
</div>