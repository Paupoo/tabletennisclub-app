<x-app-layout :breadcrumbs="$breadcrumbs">
    <div x-data="{ showSubscribeModal: false }">
    <x-admin-block>
        <!-- En-tête de page avec navigation -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Edit training') }}</h2>
                    <p class="text-gray-600 text-sm sm:text-base">{{ __('Update training session details and schedule.') }}</p>
                </div>

                <!-- Navigation contextuelle -->
                <div class="flex flex-col xs:flex-row space-y-2 sm:space-y-0 xs:space-x-2 lg:space-x-3 gap-2">
                    <button @click="showSubscribeModal = true"
                                class="inline-flex items-center justify-center px-4 py-2 bg-club-blue hover:bg-club-blue-light text-white rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto text-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('Subscribe a member (TO DO)') }}
                            </button>
                    <a href="#"
                       class="bg-red-600 hover:bg-red-500 text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto text-center flex items-center justify-center">
                        <x-ui.icon name="cancel" class="mr-2" />
                        {{ __('Cancel training (TODO)') }}
                    </a>
                    <a href="{{ route('trainings.index') }}"
                       class="bg-club-yellow hover:bg-club-yellow-light text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto text-center flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 00-2 2v2a2 2 0 002 2m0 0h14m-14 0a2 2 0 002 2v2a2 2 0 01-2 2M7 7V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        {{ __('Manage Trainings') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        @if (session('error'))
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        @if (session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        <!-- Formulaire d’édition -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-6 sm:p-8">
                <!-- En-tête du formulaire -->
                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-club-blue bg-opacity-10 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-5 h-5 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m6 0a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6a2 2 0 012-2m6 0V9a2 2 0 00-2-2M9 9a2 2 0 00-2 2v2a2 2 0 002 2"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Training Session Details') }}</h3>
                            <p class="text-sm text-gray-600">{{ __('Modify your training session parameters') }}</p>
                        </div>
                    </div>
                    <div class="border-b border-gray-200"></div>
                </div>

                <!-- Formulaire -->
                <div class="space-y-6">
                    <form id="training-form" action="{{ $training->id ? route('trainings.update', $training) : route('trainings.store') }}" method="POST" class="mt-6 space-y-6">
                        @csrf
                        @method('PUT')
                            <x-forms.training :levels="$levels" :training="$training" :types="$types" :rooms="$rooms" :seasons="$seasons" :users="$users" :trainingPacks="$trainingPacks"/>
                    </form>
                </div>

                <!-- Actions du formulaire -->
                <div class="flex flex-col sm:flex-row sm:justify-between items-center pt-6 border-t border-gray-200 space-y-3 sm:space-y-0">
                    <div class="text-sm text-gray-500">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            {{ __('All fields marked with * are required') }}
                        </span>
                    </div>
                    
                    <div class="flex space-x-3">
                        <a href="{{ route('trainings.index') }}"
                           class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg font-medium transition-colors text-sm">
                            {{ __('Cancel') }}
                        </a>
                        
                        <button type="submit" form="training-form"
                                class="bg-club-blue hover:bg-club-blue-light text-white px-6 py-2 rounded-lg font-medium transition-colors text-sm flex items-center">
                            {{ __('Update Training') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conseils -->
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <div>
                    <h4 class="text-sm font-medium text-yellow-800">{{ __('Training Management Tips') }}</h4>
                    <ul class="mt-1 text-sm text-yellow-700 list-disc list-inside space-y-1">
                        <li>{{ __('Check room availability before scheduling') }}</li>
                        <li>{{ __('Consider member skill levels when assigning trainers') }}</li>
                        <li>{{ __('Update schedules regularly to avoid conflicts') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </x-admin-block>

    <!-- Modal d'inscription d'un membre -->
        <div x-show="showSubscribeModal" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50"
            style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div x-show="showSubscribeModal" @click.outside="showSubscribeModal = false"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 transform scale-95"
                    x-transition:enter-end="opacity-100 transform scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 transform scale-100"
                    x-transition:leave-end="opacity-0 transform scale-95"
                    class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-auto">

                    <div
                        class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-club-blue bg-opacity-10 rounded-full">
                        <svg class="w-6 h-6 text-club-blue" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z">
                            </path>
                        </svg>
                    </div>

                    <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                        {{ __('Subscribe a member') }}
                    </h3>

                    <p class="text-sm text-gray-600 text-center mb-6">
                        {{ __('Select a member and subscription type') }}
                    </p>

                    <form action="#" method="POST" class="space-y-4">
                        @csrf
                        <!-- Sélection du membre -->
                        <div>
                            <x-input-label for="user_id" :value="__('Member')" />
                            <select name="user_id" id="user_id"
                                class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-club-blue focus:ring focus:ring-club-blue focus:ring-opacity-50"
                                required>
                                <option value="" disabled selected>{{ __('Select a member') }}</option>
                                @foreach ($notSubscribedUsers as $notSubscribedUser)
                                    <option value="{{ $notSubscribedUser->id }}">{{ $notSubscribedUser->fullName }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3 pt-4">
                            <button type="button" @click="showSubscribeModal = false"
                                class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit"
                                class="flex-1 bg-club-blue hover:bg-club-blue-light text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                {{ __('Subscribe') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        </div>
</x-app-layout>
