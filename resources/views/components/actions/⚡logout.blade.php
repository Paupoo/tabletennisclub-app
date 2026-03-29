<?php
declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public function logout(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('login'), navigate: true);
    }
}

?>


<div>
    <x-menu-item 
        class="text-error" 
        icon="o-power" 
        wire:click="logout"
        title="{{ __('Logout') }}" />
</div>