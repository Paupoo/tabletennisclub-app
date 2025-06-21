<x-app-layout :breadcrumbs="$breadcrumbs">

    <x-slot name="header">
        <div class="flex">
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                {{ __('Create a new tournament') }}
            </h2>
            <!-- Menu d'actions -->
            <div class="ml-auto mt-4 md:mt-0 flex flex-wrap gap-3">
                

                <div class="inline-block h-4/5 min-h-[1em] w-0.5 mx-2 my-auto self-stretch bg-neutral-300 dark:bg-white/10"></div>
                <!-- Boutons d'accès rapide -->
                <a href="{{ route('tournaments.index') }}"><x-primary-button>{{ __('Show Tournaments') }}</x-primary-button></a>
            </div>
        </div>
    </x-slot>
    <x-admin-block>
        <x-forms.tournament :rooms="$rooms" :tournament="$tournament" />
    </x-admin-block>


</x-app-layout>