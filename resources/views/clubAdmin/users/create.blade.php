<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête de page avec navigation -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start space-y-4 sm:space-y-0">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ __('Create a member') }}</h2>
                    <p class="text-gray-600 text-sm sm:text-base">{{ __('Create a new member by filling out the form below.') }}</p>
                </div>

                <!-- Navigation contextuelle -->
                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                    <a href="{{ route('dashboard') }}"
                       class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto text-center flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2 2v10z"></path>
                        </svg>
                        {{ __('Dashboard') }}
                    </a>
                    
                    <a href="{{ route('users.index') }}"
                       class="bg-club-yellow hover:bg-club-yellow-light text-gray-700 px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto text-center flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        {{ __('Manage members') }}
                    </a>
                </div>
            </div>
        </div>

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
                            {!! session('success') !!}
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Member Information') }}</h3>
                            <p class="text-sm text-gray-600">{{ __('Fill in the new member information') }}</p>
                        </div>
                    </div>
                    <div class="border-b border-gray-200"></div>
                </div>

                <!-- Formulaire -->
                <form action="{{ route('users.store') }}" method="POST" class="space-y-6">
                    @csrf
                    
                    <!-- Composant formulaire utilisateur -->
                    <div class="grid grid-cols-1 gap-6">
                        <x-forms.user :user="$user" :rankings="$rankings" :teams="$teams" :sexes="$sexes"></x-forms.user>
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
                            <a href="{{ route('users.index') }}"
                               class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-2 rounded-lg font-medium transition-colors text-sm">
                                {{ __('Cancel') }}
                            </a>
                            
                            <button type="submit"
                                    class="bg-club-blue hover:bg-club-blue-light text-white px-6 py-2 rounded-lg font-medium transition-colors text-sm flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('Create new user') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Aide contextuelle (optionnel) -->
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h4 class="text-sm font-medium text-blue-800">{{ __('Creation Tips') }}</h4>
                    <div class="mt-1 text-sm text-blue-700">
                        <ul class="list-disc list-inside space-y-1">
                            <li>{{ __('Check that the email address is unique in the system') }}</li>
                            <li>{{ __('Passwords must contain at least 8 characters') }}</li>
                            <li>{{ __('Information can be modified later') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </x-admin-block>
</x-app-layout>