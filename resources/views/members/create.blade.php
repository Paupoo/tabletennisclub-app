<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('New user') }}
        </h2>
    </x-slot>


    
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <section>
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Create a new user') }}
                        </h2>
                
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __("Fill this form to add a new user.") }}
                        </p>
                    </header>

                    <form action="{{ route('members.store') }}" method="POST">
                    <div class="mt-6">
                        
                        {{-- Last Name --}}
                         <div>
                             <x-input-label for="last_name" :value="__('Last Name')" />
                        <x-text-input id="last_name" name="last_name" type="text" class="block w-full mt-1" :value="old('last_name')" required autofocus autocomplete="last_name" />
                        <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
                    </div>

                    {{-- First Name --}}
                    <div>
                        <x-input-label for="first_name" :value="__('First Name')" />
                        <x-text-input id="first_name" name="first_name" type="text" class="block w-full mt-1" :value="old('first_name')" required autofocus autocomplete="first_name" />
                        <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
                    </div>

                    {{-- Role --}}
                    <div>
                        <x-input-label for="role" :value="__('Role')" />
                        <x-text-input id="role" name="role" type="text" class="block w-full mt-1" :value="old('role')" autofocus autocomplete="role" />
                        <x-input-error class="mt-2" :messages="$errors->get('role')" />
                    </div>

                    {{-- Licence --}}
                    <div>
                        <x-input-label for="licence" :value="__('Licence')" />
                        <x-text-input id="licence" name="licence" type="text" class="block w-20 mt-1" :value="old('licence')" autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('licence')" />
                    </div>
                    
                    {{-- Ranking --}}
                    <div>
                        <x-input-label for="ranking" :value="__('Ranking')" />
                        <x-text-input id="ranking" name="ranking" type="text" class="block mt-1 w-14" :value="old('ranking')" autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('ranking')" />
                    </div>

                    {{-- Team --}}
                    <div>
                        <x-input-label for="team" :value="__('Team')" />
                        <x-text-input id="team" name="team" type="text" class="block mt-1 w-14" :value="old('team')" autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('team')" />
                        </div>            
                    </div>

                    <div class="mt-4">
                        <x-primary-button>Create</x-primary-button>
                    </div>
                        
                </form>

                    </section>
            </div>

        </div>
    </div>
</x-app-layout>
