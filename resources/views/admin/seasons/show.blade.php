<x-app-layout :breadcrumbs="$breadcrumbs">
    <div x-data="{ showSubscribeModal: false, showStats: false, showActionsMenu: false }">
        <x-admin-block>
            <!-- En-tête de page avec informations principales -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ $season->name }}</h2>
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                            <span>{{ $season->start_at->format('d/m/Y') }} -
                                {{ $season->end_at->format('d/m/Y') }}</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                        
                        <!-- Bouton toggle statistiques -->
                        <button
                            @click="showStats = !showStats"
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base w-full sm:w-auto flex items-center justify-center"
                        >
                            <!-- Icône qui tourne selon l'état -->
                            <svg
                                x-bind:class="showStats ? 'rotate-180' : 'rotate-0'"
                                class="w-4 h-4 mr-2 transition-transform duration-200"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
    
                            <!-- Texte du bouton qui change selon l'état -->
                            <span x-text="showStats ? '{{ __('Hide Statistics') }}' : '{{ __('Show Statistics') }}'"></span>
                        </button>
                        @can('subscribe', $season)
                            <button @click="showSubscribeModal = true"
                                class="inline-flex items-center justify-center px-4 py-2 bg-club-blue hover:bg-club-blue-light text-white rounded-lg font-medium transition-colors text-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('Subscribe a member') }}
                            </button>
                        @endcan
                    </div>
                </div>

                <!-- Bloc des statistiques avec transition -->
            <div
                x-show="showStats"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 transform -translate-y-2"
                x-transition:enter-end="opacity-100 transform translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 transform translate-y-0"
                x-transition:leave-end="opacity-0 transform -translate-y-2"
                class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6"
            >
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 border-t border-gray-200">
                    <div
                        class="bg-club-blue bg-opacity-10 rounded-lg p-3 text-center border border-club-blue border-opacity-20">
                        <div class="text-2xl font-bold text-white">{{ $season->users()->count() }}</div>
                        <div class="text-xs text-white font-medium">{{ __('Total subscriptions') }}</div>
                    </div>
                    <div class="bg-club-yellow rounded-lg p-3 text-center border border-club-yellow">
                        <div class="text-2xl font-bold text-club-blue">
                            {{ $subscriptions->where('is_competitive', true)->count() }}
                        </div>
                        <div class="text-xs text-club-blue font-medium">{{ __('Competitors') }}</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3 text-center border border-green-200">
                        <div class="text-2xl font-bold text-green-600">
                            {{ $subscriptions->where('status', 'paid')->count() }}
                        </div>
                        <div class="text-xs text-green-700 font-medium">{{ __('Paid') }}</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3 text-center border border-yellow-200">
                        <div class="text-2xl font-bold text-yellow-600">
                            {{ $subscriptions->where('status', 'pending')->count() }}
                        </div>
                        <div class="text-xs text-yellow-700 font-medium">{{ __('Pending') }}</div>
                    </div>
                </div>
            </div>

            <!-- Affichage des erreurs -->
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start space-x-3">
                        <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z">
                            </path>
                        </svg>
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-red-800 mb-2">{{ __('Errors found') }}</h3>
                            <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Liste des inscrits -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="p-4 sm:p-6 border-b border-gray-200">
                    <h3 class="text-lg font-bold text-club-blue">{{ __('Subscribed members') }}</h3>
                </div>

                <!-- Vue Desktop : Tableau (caché sur mobile) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Member') }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Type') }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Status') }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    {{ __('Amount due') }}
                                </th>
                                @can('update', $season)
                                    <th
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        {{ __('Actions') }}
                                    </th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($subscriptions as $subscription)
                                <tr class="hover:bg-gray-50 align-top">
                                    {{-- Membre --}}
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-gray-900">
                                            {{ $subscription->user->fullName }}
                                        </div>
                                    </td>

                                    {{-- Type --}}
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @if ($subscription->is_competitive)
                                            <span class="px-2 py-0.5 inline-flex text-xs font-semibold rounded-full bg-club-yellow text-club-blue">
                                                {{ __('Competitive') }}
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 inline-flex text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                {{ __('Casual') }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Statut --}}
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @php
                                            $status = $subscription->getStatus();
                                            $statusColors = [
                                                'paid' => 'bg-green-100 text-green-800',
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'confirmed' => 'bg-blue-100 text-blue-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                'refunded' => 'bg-gray-100 text-gray-800',
                                            ];
                                        @endphp
                                        <span class="px-2 py-0.5 inline-flex text-xs font-semibold rounded-full {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ __(ucfirst($status)) }}
                                        </span>
                                    </td>

                                    {{-- Montant --}}
                                    <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900 font-medium">
                                        {{ number_format($subscription->amount_due - $subscription->amount_paid, 2) }} €
                                    </td>

                                    {{-- Actions --}}
                                    @can('manageSubscription', $season)
                                        <td class="px-4 py-2 whitespace-nowrap text-right text-sm">
                                            <a href="{{ route('admin.subscriptions.show', $subscription) }}" 
                                            class="text-club-blue hover:text-club-blue-light font-medium">
                                                {{ __('Manage') }} →
                                            </a>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-600">
                                        {{ __('No subscriptions yet for this season') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

                <!-- Vue Mobile : Cartes (visible uniquement sur mobile) -->
                <div class="md:hidden divide-y divide-gray-200">
                    @forelse($subscriptions as $subscription)
                        <div class="p-4 hover:bg-gray-50" x-data="{ showActions: false }">
                            <!-- En-tête de la carte -->
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h4 class="text-sm font-semibold text-gray-900">
                                        {{ $subscription->user->fullName }}
                                    </h4>
                                </div>
                                <div class="ml-2">
                                    @if ($subscription->is_competitive)
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-club-yellow text-club-blue">
                                            {{ __('Competitive') }}
                                        </span>
                                    @else
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            {{ __('Casual') }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Informations principales -->
                            <div class="space-y-2 mb-3">
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Status') }}</span>
                                    @php
                                        $status = $subscription->getStatus();
                                        $statusColors = [
                                            'paid' => 'bg-green-100 text-green-800',
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'confirmed' => 'bg-blue-100 text-blue-800',
                                            'cancelled' => 'bg-red-100 text-red-800',
                                            'refunded' => 'bg-gray-100 text-gray-800',
                                        ];
                                    @endphp
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ __(ucfirst($status)) }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs text-gray-500 uppercase tracking-wider">{{ __('Amount due') }}</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ number_format($subscription->amount_due - $subscription->amount_paid, 2) }}
                                        €
                                    </span>
                                </div>
                            </div>

                            <!-- Actions (si permission) -->
                            @can('update', $subscription)
                                <div class="mt-3 pt-3 border-t border-gray-200">
                                    <!-- Bouton pour afficher/masquer les actions sur mobile -->
                                    <button @click="showActions = !showActions"
                                        class="w-full px-3 py-2 bg-gray-50 text-gray-700 text-sm font-medium rounded hover:bg-gray-100 flex items-center justify-between">
                                        <span>{{ __('Actions') }}</span>
                                        <svg class="w-5 h-5 transition-transform" :class="{ 'rotate-180': showActions }"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

                                    <!-- Conteneur des actions (affiché/masqué par Alpine.js) -->
                                    <div x-show="showActions" x-collapse class="mt-3 space-y-3">
                                        {{-- Liste des entraînements --}}
                                        @if ($subscription->status !== 'cancelled')
                                        <div>
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                                                {{ __('Trainings') }}
                                            </p>
                                            <form method="POST"
                                                action="{{ route('admin.subscriptions.addTrainingPack', $subscription) }}"
                                                class="flex flex-col justify-start gap-2">
                                                @csrf
                                                @foreach ($trainingPacks as $trainingPack)
                                                    <div class="flex flex-row gap-4 justify-start">
                                                        <x-checkbox-input value="{{ $trainingPack->id }}" 
                                                            name="training_packs[]" id="{{ $trainingPack->id }}" 
                                                            :disabled="$subscription->status !== 'pending'" 
                                                            :checked="$subscription->trainingPacks->contains($trainingPack->id)" />
                                                        <x-input-label for="{{ $trainingPack->id }}">{{ $trainingPack->name }}</x-input-label>
                                                    </div>
                                                @endforeach
                                                @if (collect($subscription->availableTransitions())->has('confirm'))
                                                    <x-primary-button class="w-fit">{{ __('Save') }}</x-primary-button>
                                                @endif
                                            </form>
                                        </div>
                                        @endif
                                        {{-- Changements de status --}}
                                        @if (collect($subscription->availableTransitions())->count() > 0)
                                            <div>
                                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                                                    {{ __('Status changes') }}
                                                </p>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach ($subscription->availableTransitions() as $action => $label)
                                                        <form method="POST"
                                                            action="{{ route('admin.subscriptions.' . $action, $subscription) }}">
                                                            @csrf
                                                            <button type="submit"
                                                                class="px-3 py-1.5 border border-gray-300 rounded text-gray-700 text-xs hover:bg-gray-100">
                                                                {{ __($label) }}
                                                            </button>
                                                        </form>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Autres actions --}}
                                        <div>
                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">
                                                {{ __('Other actions') }}
                                            </p>
                                            <div class="flex flex-wrap gap-2">
                                                @if ($subscription->payments->count() <= 0)
                                                    <form method="POST"
                                                        action="{{ route('admin.subscription.generatePayment', $subscription) }}">
                                                        @csrf
                                                        <button type="submit"
                                                            class="px-3 py-1.5 border border-gray-300 rounded text-gray-700 text-xs hover:bg-gray-100">
                                                            {{ __('Generate payment') }}
                                                        </button>
                                                    </form>
                                                @endif

                                                @if ($subscription->payments->count() > 0 && $subscription->status == 'confirmed')
                                                    <form method="POST"
                                                        action="{{ route('admin.subscriptions.sendPaymentInvite') }}">
                                                        @csrf
                                                        <input type="hidden" name="payment_id"
                                                            value="{{ $subscription->payments->first()->id }}">
                                                        <button type="submit"
                                                            class="px-3 py-1.5 border border-gray-300 rounded text-gray-700 text-xs hover:bg-gray-100">
                                                            {{ __('Send payment invite') }}
                                                        </button>
                                                    </form>
                                                @endif

                                                <form method="POST"
                                                    action="{{ route('admin.subscriptions.unsubscribe', $subscription) }}">
                                                    @csrf
                                                    <button type="submit"
                                                        class="px-3 py-1.5 border border-gray-300 rounded text-gray-700 text-xs hover:bg-gray-100">
                                                        {{ __('Soft delete') }}
                                                    </button>
                                                </form>

                                                <form method="POST"
                                                    action="{{ route('admin.subscriptions.destroy', [$season, $subscription->user]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="px-3 py-1.5 border border-gray-300 rounded text-red-700 text-xs hover:bg-red-50 font-medium">
                                                        {{ __('Unsubscribe (destroy)') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endcan
                        </div>
                    @empty
                        <div class="px-4 py-12 text-center">
                            <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                </path>
                            </svg>
                            <p class="text-gray-600">{{ __('No subscriptions yet for this season') }}</p>
                        </div>
                    @endforelse
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

                    <form action="{{ route('admin.seasons.subscribe', $season) }}" method="POST" class="space-y-4">
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

                        <!-- Type d'inscription -->
                        <div>
                            <x-input-label :value="__('Subscription type')" class="mb-3" />
                            <div class="space-y-3">
                                <label
                                    class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="type" value="casual" checked
                                        class="text-club-blue focus:ring-club-blue">
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">{{ __('Casual') }}</div>
                                        <div class="text-xs text-gray-500">{{ __('For non-competitive members') }}
                                        </div>
                                    </div>
                                </label>

                                <label
                                    class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="type" value="competitive"
                                        class="text-club-blue focus:ring-club-blue">
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">{{ __('Competitive') }}</div>
                                        <div class="text-xs text-gray-500">{{ __('For interclub members') }}</div>
                                    </div>
                                </label>
                            </div>
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
