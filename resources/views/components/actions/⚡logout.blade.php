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


{{-- No wrapper: x-menu-item already renders the <li>, and this component sits
directly inside the menu's <ul>. The <div> that used to wrap it made a screen
reader miscount the list. --}}
<x-menu-item
    class="text-error"
    icon="o-power"
    wire:click="logout"
    :title="__('Logout')" />