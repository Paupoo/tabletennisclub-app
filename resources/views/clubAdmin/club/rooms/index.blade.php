<x-app-layout :breadcrumbs="$breadcrumbs">

    {{-- Actions --}}
    @push('header-actions')
    <x-tournament.actions-menu>
        <x-menus.action-menu-item :href="route('rooms.create')"
            :icon="'plus'"
            :text="__('Create a new room')" />

    </x-tournament.actions-menu>
    @endpush

    {{-- Liste des salles --}}
    <livewire:admin.rooms.rooms-index room={{ $room }}/>

</x-app-layout>
