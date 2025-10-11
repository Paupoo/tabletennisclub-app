<x-app-layout :breadcrumbs="$breadcrumbs">
    <div x-data="{ showSubscribeModal: false }">
        <x-admin-block>
            <!-- En-tête de page avec informations principales -->
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-club-blue mb-2">{{ $season->name }}</h2>
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>{{ $season->start_at->format('d/m/Y') }} - {{ $season->end_at->format('d/m/Y') }}</span>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                        <a href="{{ route('admin.seasons.index') }}"
                           class="inline-flex items-center justify-center px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-lg font-medium transition-colors text-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            {{ __('All Seasons') }}
                        </a>
                        
                        @can('subscribe', $season)
                            <button @click="showSubscribeModal = true"
                                    class="inline-flex items-center justify-center px-4 py-2 bg-club-blue hover:bg-club-blue-light text-white rounded-lg font-medium transition-colors text-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                {{ __('Subscribe a member') }}
                            </button>
                        @endcan
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 border-t border-gray-200">
                    <div class="bg-club-blue bg-opacity-10 rounded-lg p-3 text-center border border-club-blue border-opacity-20">
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
                        <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
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
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Member') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Type') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Status') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Amount due') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($subscriptions as $subscription)
                                <tr class="hover:bg-gray-50" x-data="{ showActions: false }">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">{{ $subscription->user->fullName }}</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($subscription->is_competitive)
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-club-yellow text-club-blue">
                                                {{ __('Competitive') }}
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                {{ __('Casual') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
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
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ __(ucfirst($status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium">
                                            {{ number_format($subscription->amount_due - $subscription->amount_paid, 2) }} €
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="relative inline-block text-left">
                                            <button @click="showActions = !showActions"
                                                    class="inline-flex items-center px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors text-xs">
                                                {{ __('Actions') }}
                                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </button>
                                            
                                            <!-- Dropdown menu -->
                                            <div x-show="showActions"
                                                 @click.away="showActions = false"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="opacity-100 scale-100"
                                                 x-transition:leave-end="opacity-0 scale-95"
                                                 class="origin-top-right absolute right-0 mt-2 w-56 rounded-lg shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10"
                                                 style="display: none;">
                                                <div class="py-1">
                                                    <form action="{{ route('admin.subscriptions.confirm', $subscription) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            ✓ {{ __('Confirm') }}
                                                        </button>
                                                    </form>
                                                    
                                                    <form action="{{ route('admin.subscriptions.unconfirm', $subscription) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                            ⟲ {{ __('Set back to pending') }}
                                                        </button>
                                                    </form>
                                                    
                                                    <form action="{{ route('admin.subscriptions.markPaid', $subscription) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50">
                                                            € {{ __('Mark as paid') }}
                                                        </button>
                                                    </form>
                                                    
                                                    @if($subscription->payments->count() <= 0)
                                                        <form action="{{ route('admin.subscription.generatePayment', $subscription) }}" method="POST">
                                                            @csrf
                                                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">
                                                                + {{ __('Generate payment') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                    
                                                    @if($subscription->payments->count() > 0)
                                                        <form action="{{ route('admin.subscriptions.sendPaymentInvite') }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="payment_id" value="{{ $subscription->payments->first()->id }}">
                                                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">
                                                                ✉ {{ __('Send payment invite') }}
                                                            </button>
                                                        </form>
                                                    @endif
                                                    
                                                    <div class="border-t border-gray-100 my-1"></div>
                                                    
                                                    <form action="{{ route('admin.subscriptions.markRefunded', $subscription) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-orange-700 hover:bg-orange-50">
                                                            ↶ {{ __('Mark as refunded') }}
                                                        </button>
                                                    </form>
                                                    
                                                    <form action="{{ route('admin.subscriptions.cancel', $subscription) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                                            ✕ {{ __('Cancel') }}
                                                        </button>
                                                    </form>
                                                    
                                                    <form action="{{ route('admin.subscriptions.delete', $subscription) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                                            🗑 {{ __('Soft delete') }}
                                                        </button>
                                                    </form>
                                                    
                                                    <form action="{{ route('admin.subscriptions.unsubscribe', [$season, $subscription->user]) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-900 hover:bg-red-100 font-medium">
                                                            ⚠ {{ __('Unsubscribe (destroy)') }}
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center">
                                        <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        <p class="text-gray-600">{{ __('No subscriptions yet for this season') }}</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-admin-block>

        <!-- Modal d'inscription d'un membre -->
        <div x-show="showSubscribeModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 overflow-y-auto bg-black bg-opacity-50" 
             style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4">
                <div x-show="showSubscribeModal"
                     @click.outside="showSubscribeModal = false"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95"
                     class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-auto">
                    
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-club-blue bg-opacity-10 rounded-full">
                        <svg class="w-6 h-6 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
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
                            <select name="user_id" 
                                    id="user_id" 
                                    class="mt-1 block w-full border-gray-300 rounded-lg shadow-sm focus:border-club-blue focus:ring focus:ring-club-blue focus:ring-opacity-50"
                                    required>
                                <option value="" disabled selected>{{ __('Select a member') }}</option>
                                @foreach($notSubscribedUsers as $notSubscribedUser)
                                    <option value="{{ $notSubscribedUser->id }}">{{ $notSubscribedUser->fullName }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Type d'inscription -->
                        <div>
                            <x-input-label :value="__('Subscription type')" class="mb-3" />
                            <div class="space-y-3">
                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" 
                                           name="type" 
                                           value="casual" 
                                           checked
                                           class="text-club-blue focus:ring-club-blue">
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">{{ __('Casual') }}</div>
                                        <div class="text-xs text-gray-500">{{ __('For non-competitive members') }}</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" 
                                           name="type" 
                                           value="competitive"
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
                            <button type="button" 
                                    @click="showSubscribeModal = false" 
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