<x-tournament.tournament-layout :tournament="$tournament" :statusesAllowed="$statusesAllowed">

           {{-- actions menu --}}
    @push('header-actions')
    <x-tournament.actions-menu :tournament="$tournament" :statusesAllowed="$statusesAllowed ?? []">
            @include('admin.tournaments.partials.action-menu')
        </x-tournament.actions-menu>
    @endpush

    @include('admin.tournaments.partials.details')

    @push('modals')
    <!-- Modal de confirmation (à placer en dehors du push) -->
    <x-modal name="confirm-tournament-deletion" focusable>
        <form method="get" action="{{ route('tournaments.destroy', $tournament) }}" class="p-6">
            @csrf

            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Are you sure you want to delete this tournament?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('This action is irreversible. All associated data will be permanently removed.') }}
            </p>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Delete') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
    @endpush
</x-tournament.tournament-layout>
