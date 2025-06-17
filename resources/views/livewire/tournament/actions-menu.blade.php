@props(['tournament', 'statusesAllowed' => []])
<x-actions-menu>
    <div class="py-1">
        <!-- Update subscription as a user -->
        @can('updateSubscriptionAsUser', $tournament)
            @if (!$tournament->users->contains(auth()->user()->id))
                <a href="/admin/tournament/register/{{ $tournament->id }}/{{ auth()->user()->id }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="join" class="mr-2" />
                    {{ __('Register') }}
                </a>
            @elseif($tournament->users->contains(auth()->user()->id))
                <a href="/admin/tournament/unregister/{{ $tournament->id }}/{{ auth()->user()->id }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="leave" class="mr-2" />
                    {{ __('Unregister') }}
                </a>
            @endif
        @endcan
        {{-- Updates --}}
        @can('update', $tournament)
            <!-- Composant Livewire pour l'inscription de joueur -->
            <livewire:tournament.player-registration :tournament="$tournament" />


            <a href="{{ route('tournament.edit', $tournament) }}"
                class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                <x-ui.icon name="modify" class="mr-2" />
                {{ __('Modify') }}
            </a>
            <a href="#"
                class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                <x-ui.icon name="duplicate" class="mr-2" />
                {{ __('Duplicate (TO DO)') }}
            </a>
            <div class="border-t border-gray-200"></div>
            @if (in_array(\App\Enums\TournamentStatusEnum::DRAFT, $statusesAllowed))
                <a href="{{ route('tournament.changeStatus', [$tournament, \App\Enums\TournamentStatusEnum::DRAFT]) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="pen" class="mr-2" />
                    {{ __('Draft') }}
                </a>
            @endif
            @if (in_array(\App\Enums\TournamentStatusEnum::SETUP, $statusesAllowed))
                <a href="{{ route('tournament.changeStatus', [$tournament, \App\Enums\TournamentStatusEnum::SETUP]) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="setup" class="mr-2" />
                    {{ __('Setup') }}
                </a>
            @endif
            @if (in_array(\App\Enums\TournamentStatusEnum::PUBLISHED, $statusesAllowed))
                <a href="{{ route('tournament.changeStatus', [$tournament, \App\Enums\TournamentStatusEnum::PUBLISHED]) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="unlocked" class="mr-2" />
                    {{ __('Publish/Unlock') }}
                </a>
            @endif
            @if (in_array(\App\Enums\TournamentStatusEnum::PENDING, $statusesAllowed))
                <a href="{{ route('tournament.changeStatus', [$tournament, \App\Enums\TournamentStatusEnum::PENDING]) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="rocket-launch" class="mr-2" />
                    {{ __('Start') }}
                </a>
            @endif
            @if (in_array(\App\Enums\TournamentStatusEnum::CLOSED, $statusesAllowed))
                <a href="{{ route('tournament.changeStatus', [$tournament, \App\Enums\TournamentStatusEnum::CLOSED]) }}"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                    <x-ui.icon name="flag" class="mr-2" />
                    {{ __('Close') }}
                </a>
            @endif
            @if (in_array(\App\Enums\TournamentStatusEnum::CANCELLED, $statusesAllowed))
                <a href="{{ route('tournament.changeStatus', [$tournament, \App\Enums\TournamentStatusEnum::CANCELLED]) }}"
                    class="flex items-center px-4 py-2 text-sm text-red-700 hover:bg-gray-100 hover:text-red-900">
                    <x-ui.icon name="cancel" class="mr-2" />
                    {{ __('Cancel') }}
                </a>
            @endif
        @endcan

        <!-- Deletion -->
        @can('delete', $tournament)
            <div class="border-t border-gray-200"></div>

            <button @click="$dispatch('open-modal', 'confirm-tournament-deletion')"
                class="w-full flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100 hover:text-red-700">
                <x-ui.icon name="delete" class="mr-2" />
                {{ __('Delete') }}
            </button>
        @endcan
    </div>

    <x-slot name="quickActions">
        <a href="{{ route('tournaments.index') }}">
            <x-primary-button type="button">
                <span class="mr-2">{{ __('Tournaments list') }}</span>
                <x-ui.icon name="arrow-left" />
            </x-primary-button>
        </a>
    </x-slot>
</x-actions-menu>
