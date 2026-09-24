<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Support\AccountProxy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Where the switcher sits: the member menu, or the head of the dashboard's
     * "Mon espace" — a guardian signing in lands on their own account and has
     * to see, first thing, that their child's is one step away.
     */
    public string $variant = 'menu';

    /** Take a ward's seat. Authorization lives in {@see AccountProxy::start()}. */
    public function actFor(int $userId): void
    {
        AccountProxy::start(User::findOrFail($userId));

        // A full page load rather than wire:navigate: everything on the screen
        // was rendered for somebody else.
        $this->redirect(route('dashboard'));
    }

    /** The person who signed in — the ward, once a proxy is held, is not them. */
    #[Computed]
    public function actor(): ?User
    {
        $actor = AccountProxy::origin() ?? Auth::user();

        return $actor instanceof User ? $actor : null;
    }

    #[Computed]
    public function isActing(): bool
    {
        return AccountProxy::isActing();
    }

    public function stopActing(): void
    {
        AccountProxy::stop();

        $this->redirect(route('dashboard'));
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function wards(): Collection
    {
        return $this->actor?->managedAccounts() ?? new Collection;
    }
}

?>

@if ($variant === 'tiles')
    {{-- `contents`: the tiles join the dashboard grid they are dropped into. --}}
    <div class="contents">
        @if ($this->isActing)
            @include('clubAdmin._dashboard_tile', [
                'icon' => 'o-arrow-uturn-left',
                'label' => __('Back to my account (:name)', ['name' => $this->actor?->first_name]),
                'sub' => __('You are acting for :ward.', ['ward' => auth()->user()->first_name]),
                'wireClick' => 'stopActing',
                'color' => 'secondary',
            ])
        @else
            @foreach ($this->wards as $ward)
                @include('clubAdmin._dashboard_tile', [
                    'icon' => 'o-user-group',
                    'label' => __('Act for :ward', ['ward' => $ward->first_name]),
                    'sub' => __('Switch to their account'),
                    'wireClick' => 'actFor(' . $ward->id . ')',
                    'wireKey' => 'ward-tile-' . $ward->id,
                    'color' => 'secondary',
                ])
            @endforeach
        @endif
    </div>
@else
{{-- One root per branch: Livewire allows a single root element, and x-menu-sub
     renders the <li> this component owes the menu's <ul>. --}}
@if ($this->isActing)
    <x-menu-sub icon="o-user-group"
        :title="__('Acting for :ward', ['ward' => auth()->user()->first_name])">
        <x-menu-item
            class="text-warning"
            icon="o-arrow-uturn-left"
            wire:click="stopActing"
            :title="__('Back to my account (:name)', ['name' => $this->actor?->first_name])" />
    </x-menu-sub>
@elseif ($this->wards->isNotEmpty())
    <x-menu-sub icon="o-user-group" :title="__('I am acting for')">
        @foreach ($this->wards as $ward)
            <x-menu-item
                :wire:key="'ward-' . $ward->id"
                icon="o-user"
                wire:click="actFor({{ $ward->id }})"
                :title="$ward->first_name . ' ' . $ward->last_name" />
        @endforeach
    </x-menu-sub>
@else
    {{-- A member with no ward owes the menu nothing, but Livewire still needs a
         root to anchor the component to. --}}
    <li hidden></li>
@endif
@endif
