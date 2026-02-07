<x-app-layout :breadcrumbs="$breadcrumbs">
    


    {{-- Actions --}}
    @push('header-actions')
    <x-tournament.actions-menu>
        <x-menus.action-menu-item :href="route('rooms.create')"
            :icon="'plus'"
            :text="__('Create a new room')" />

    </x-tournament.actions-menu>
    @endpush

    <x-admin-block>
        <x-layout.page-header title="{{ __('Create a new room') }}" description="{{ __('Address will be used to help users locate an activity. Capacity defines the maximum amount of tables to be used for an activity.') }}" />
        
        <x-forms.room :room="$room"/>
        
    </x-admin-block>

</x-app-layout>
