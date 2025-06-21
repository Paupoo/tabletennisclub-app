<x-app-layout :breadcrumbs="$breadcrumbs">

    @push('header-actions')
        <x-tournament.actions-menu>
            <x-menus.action-menu-item :href="route('tournaments.create')"
                :icon="'plus'"
                :text="__('Create tournament')" />

        </x-tournament.actions-menu>
    @endpush
    <x-admin-block>
        <livewire:tournaments-table>
    </x-admin-block>
</x-app-layout>
