<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            {{ __('Create a competition') }}
        </h2>
    </x-slot>

    <x-admin-block>
        <div class="flex gap-4">
            <form action="{{ route('dashboard') }}" method="GET">
                <x-primary-button>{{ __('Dashboard') }}</x-primary-button>
            </form>
            <form action="{{ route('competitions.index') }}" method="GET">
                <x-primary-button>{{ __('Manage competitiones') }}</x-primary-button>
            </form>
        </div>
    </x-admin-block>


    
    <div class="pt-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                @if (session('success'))
                    <x-notification-success>{{ session('success') }}</x-notification-success>
                @endif
                <div class="w-full p-6 text-gray-900 dark:text-gray-100 lg:w-1/2">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Create a new competition') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Create a new competition from this form.') }}
                        </p>
                    </header>

                    <form action="{{ route('competitions.store') }}" method="POST" class="mt-6 space-y-6">
                        @csrf

                        {{-- Competition Date --}}
                        <div>
                            <x-input-label for="total_players" :value="__('Competition Type')" />
                            <x-select-input id="total_players" name="total_players" class="block w-full mt-1" :value="old('total_players')"
                                required autofocus>

                                {!! $competition_types !!}

                            </x-select-input>
                            <x-input-error class="mt-2" :messages="$errors->get('total_players')" />
                        </div>

                        {{-- Competition Type --}}
                        <div>
                            <x-input-label for="competition_date" :value="__('Competition Date')" />
                            <x-text-input id="competition_date" name="competition_date" type="datetime-local" class="block w-full mt-1"
                                :value="old('competition_date')" required autofocus autocomplete="competition_date"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('competition_date')" />
                        </div>

                        {{-- Competition Address --}}
                        <div>
                            <x-input-label for="competition_address" :value="__('Competition Address')" />
                            <x-text-input id="competition_address" name="competition_address" type="text" class="block w-full mt-1"
                                :value="old('competition_address')" required autofocus autocomplete="competition_address"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('competition_address')" />
                        </div>                        

                        {{-- Competition Week Number --}}
                        <div>
                            <x-input-label for="competition_week_number" :value="__('Competition Week Number')" />
                            <x-text-input id="competition_week_number" name="competition_week_number" type="number" class="block w-full mt-1"
                                :value="old('competition_week_number')" required autofocus autocomplete="competition_week_number"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('competition_week_number')" />
                        </div>    

                        {{-- Visited Team --}}
                        <div>
                            <x-input-label for="visited_team" :value="__('Visited team')" />
                            <x-text-input id="visited_team" name="visited_team" type="text" class="block w-full mt-1"
                                :value="old('visited_team')" required autofocus autocomplete="visited_team"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('visited_team')" />
                        </div>    

                        {{-- Visiting Team --}}
                        <div>
                            <x-input-label for="visiting_team" :value="__('Visiting team')" />
                            <x-text-input id="visiting_team" name="visiting_team" type="text" class="block w-full mt-1"
                                :value="old('visiting_team')" required autofocus autocomplete="visiting_team"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('visiting_team')" />
                        </div>    
                        
                        <div>
                            <x-primary-button>{{ __('Create new competition') }}</x-primary-button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>