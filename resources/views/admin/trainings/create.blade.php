<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête de page avec navigation -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Create a training') }}</h2>
                    <p class="text-gray-600 text-sm sm:text-base">{{ __('Plan and schedule new training sessions for your members.') }}</p>
                </div>

                <!-- Navigation contextuelle -->
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                    
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

        <!-- Notification d'erreur -->
        @if (session('error'))
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-red-800">
                            {{ session('error') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Notification de succès -->
        @if (session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">
                            {{ session('success') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Formulaire de création -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="p-6 sm:p-8">
                <!-- En-tête du formulaire -->
                <div class="mb-8">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0 w-10 h-10 bg-club-blue bg-opacity-10 rounded-full flex items-center justify-center mr-4">
                            <svg class="w-5 h-5 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 00-2 2v2a2 2 0 002 2m0 0h14m-14 0a2 2 0 002 2v2a2 2 0 01-2 2M7 7V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Training Session Details') }}</h3>
                            <p class="text-sm text-gray-600">{{ __('Configure your training session parameters') }}</p>
                        </div>
                    </div>
                    <div class="border-b border-gray-200"></div>
                </div>

                <!-- Information sur la création en lot -->
                <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-blue-800">{{ __('Bulk Training Creation') }}</h4>
                            <div class="mt-1 text-sm text-blue-700">
                                <p>{{ __('To create multiple training sessions, simply select the recurrence and extend the end date.') }}</p>
                                <p class="mt-1 font-medium">{{ __('Example: If your start date is a Monday and the recurrence is weekly, trainings will occur every Monday between start and end date.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulaire -->
                <div class="space-y-6">
                    <form id="training-form" action="{{ $training->id ? route('trainings.update', $training) : route('trainings.store') }}" method="POST" class="mt-6 space-y-6">
                    @csrf
                        <x-forms.training :levels="$levels" :training="$training" :types="$types" :rooms="$rooms" :seasons="$seasons" :users="$users"/>
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
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            {{ __('Create Training') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conseils avancés -->
        <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h4 class="text-sm font-medium text-yellow-800">{{ __('Training Management Tips') }}</h4>
                    <div class="mt-1 text-sm text-yellow-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>{{ __('Check room availability before scheduling') }}</li>
                            <li>{{ __('Consider member skill levels when assigning trainers') }}</li>
                            <li>{{ __('Plan training sessions in advance for better attendance') }}</li>
                            <li>{{ __('Use bulk creation for recurring weekly sessions') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </x-admin-block>
</x-app-layout>