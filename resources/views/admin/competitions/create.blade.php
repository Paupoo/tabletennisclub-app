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

                        {{-- Competition Type --}}
                        <div>
                            <x-input-label for="total_players" :value="__('Competition Type')" />
                            <x-select-input id="total_players" name="total_players" class="block w-full mt-1" :value="old('total_players')"
                                required autofocus>

                                {!! $competition_types !!}

                            </x-select-input>
                            <x-input-error class="mt-2" :messages="$errors->get('total_players')" />
                        </div>

                        {{-- Competition Date --}}
                        <div>
                            <x-input-label for="competition_date" :value="__('Competition Date')" />
                            <x-text-input id="competition_date" name="competition_date" type="datetime-local" min="{{ today()->format('Y-m-d\TH:i:s') }}" max="{{ now()->addMonth(6)->format('Y-m-d\TH:i:s') }}" class="block w-full mt-1"
                                :value="old('competition_date', now()->format('Y-m-d\TH:i'))" required autofocus autocomplete="competition_date"></x-text-input>
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
                            <x-text-input id="competition_week_number" name="competition_week_number" type="number" min="1" max="52" step="1" class="block w-full mt-1"
                                :value="old('competition_week_number')" required autofocus autocomplete="competition_week_number"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('competition_week_number')" />
                        </div>    

                        {{-- Club's Team --}}
                        <div>
                            <x-input-label for="club_team" :value="__('Club\'s team')" />
                            <x-select-input id="club_team" name="club_team" type="text" class="block w-full mt-1"
                                :value="old('club_team')" required autofocus autocomplete="club_team">
                                @foreach ($teams as $team)
                                    <option value="{{ $team->id }}">Otttignies {{ $team->name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error class="mt-2" :messages="$errors->get('club_team')" />
                        </div>    

                        {{-- Opposing Team --}}
                        <div>
                            <x-input-label for="opposing_team" :value="__('Opposing team')" />
                            <x-text-input id="opposing_team" name="opposing_team" type="text" class="block w-full mt-1"
                                :value="old('opposing_team')" required autofocus autocomplete="opposing_team"></x-text-input>
                            <x-input-error class="mt-2" :messages="$errors->get('opposing_team')" />
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