<x-app-layout :breadcrumbs="$breadcrumbs">
    <div class="max-w-4xl mx-auto">
        <x-admin-block>
            <!-- En-tête avec retour -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    
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
                    <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                        {{ __(ucfirst($status)) }}
                    </span>
                </div>

                <h1 class="text-2xl font-bold text-club-blue mb-2">
                    {{ $subscription->user->fullName }}
                </h1>
                <p class="text-gray-600">
                    {{ $subscription->season->name }} • 
                    @if($subscription->is_competitive)
                        <span class="text-club-yellow font-medium">{{ __('Competitive') }}</span>
                    @else
                        <span>{{ __('Casual') }}</span>
                    @endif
                </p>
            </div>

            <!-- Informations financières -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">{{ __('Financial information') }}</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">{{ __('Amount due') }}</p>
                        <p class="text-2xl font-bold text-gray-900">{{ number_format($subscription->amount_due, 2) }} €</p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">{{ __('Amount paid') }}</p>
                        <p class="text-2xl font-bold text-green-600">{{ number_format($subscription->amount_paid, 2) }} €</p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">{{ __('Remaining') }}</p>
                        <p class="text-2xl font-bold text-orange-600">{{ number_format($subscription->amount_due - $subscription->amount_paid, 2) }} €</p>
                    </div>
                </div>
            </div>

            <!-- Packs d'entraînement -->
            @if($subscription->status !== 'cancelled')
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">{{ __('Training packs') }}</h2>
                
                <form method="POST" action="{{ route('admin.subscriptions.addTrainingPack', $subscription) }}">
                    @csrf
                    <div class="space-y-3">
                        @forelse($trainingPacks as $trainingPack)
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                <div class="flex items-center">
                                    <x-checkbox-input 
                                        name="training_packs[]"
                                        value="{{ $trainingPack->id }}"
                                        id="training-pack-{{ $trainingPack->id }}"
                                        :disabled="$subscription->status !== 'pending'"
                                        :checked="$subscription->trainingPacks->contains($trainingPack->id)"
                                        class="mr-3"
                                    />
                                    <label for="training-pack-{{ $trainingPack->id }}" class="text-sm font-medium text-gray-900 cursor-pointer">
                                        {{ $trainingPack->name }}
                                    </label>
                                </div>
                                @if($trainingPack->price)
                                    <span class="text-sm text-gray-600">{{ number_format($trainingPack->price, 2) }} €</span>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 italic">{{ __('No training packs available') }}</p>
                        @endforelse
                    </div>

                    @if($subscription->status === 'pending' && $trainingPacks->count() > 0)
                        <div class="flex justify-end mt-4">
                            <x-primary-button>{{ __('Save training packs') }}</x-primary-button>
                        </div>
                    @endif
                </form>
            </div>
            @endif

            <!-- Transitions de statut -->
            @if(collect($subscription->availableTransitions())->count() > 0)
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">{{ __('Status changes') }}</h2>
                
                <div class="flex flex-wrap gap-3">
                    @foreach($subscription->availableTransitions() as $action => $label)
                        <form method="POST" action="{{ route('admin.subscriptions.' . $action, $subscription) }}" class="inline">
                            @csrf
                            <button type="submit" 
                                class="px-4 py-2 bg-club-blue hover:bg-club-blue-light text-white rounded-lg font-medium transition-colors text-sm">
                                {{ __($label) }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Paiements -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">{{ __('Payments') }}</h2>
                
                @if($subscription->payments->count() > 0)
                    <div class="space-y-3 mb-4">
                        @foreach($subscription->payments as $payment)
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $payment->reference }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ $payment->amount_due }} €</p>
                                    <p class="text-xs text-gray-500">{{ $payment->created_at->format('d/m/Y H:i') }}</p>
                                </div>
                                @if($payment->paid_at)
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded">
                                        {{ __('Paid') }}
                                    </span>
                                @else
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded">
                                        {{ __('Pending') }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-500 italic mb-4">{{ __('No payments yet') }}</p>
                @endif

                <!-- Actions de paiement -->
                <div class="flex flex-wrap gap-3">
                    @if($subscription->payments->count() <= 0 && $subscription->status == 'confirmed')
                        <form method="POST" action="{{ route('admin.subscription.generatePayment', $subscription) }}" class="inline">
                            @csrf
                            <button type="submit" 
                                class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition-colors text-sm">
                                {{ __('Generate payment') }}
                            </button>
                        </form>
                    @endif

                    @if($subscription->payments->count() > 0 && $subscription->status == 'confirmed')
                        <form method="POST" action="{{ route('admin.subscriptions.sendPaymentInvite') }}" class="inline">
                            @csrf
                            <input type="hidden" name="payment_id" value="{{ $subscription->payments->first()->id }}">
                            <button type="submit" 
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors text-sm">
                                {{ __('Send payment invite') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Actions dangereuses -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-lg font-bold text-red-600 mb-4">{{ __('Danger zone') }}</h2>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ __('Soft delete subscription') }}</p>
                            <p class="text-xs text-gray-500">{{ __('Mark this subscription as deleted (can be restored)') }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.subscriptions.delete', $subscription) }}" class="inline">
                            @csrf
                            <button type="submit" 
                                onclick="return confirm('{{ __('Are you sure?') }}')"
                                class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition-colors text-sm">
                                {{ __('Soft delete') }}
                            </button>
                        </form>
                    </div>

                    <div class="flex items-center justify-between p-4 border border-red-200 rounded-lg bg-red-50">
                        <div>
                            <p class="text-sm font-medium text-red-900">{{ __('Unsubscribe member') }}</p>
                            <p class="text-xs text-red-600">{{ __('Permanently delete this subscription (cannot be undone)') }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.subscriptions.unsubscribe', [$subscription->season, $subscription->user]) }}" class="inline">
                            @csrf
                            <button type="submit" 
                                onclick="return confirm('{{ __('This action is irreversible. Are you absolutely sure?') }}')"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors text-sm">
                                {{ __('Unsubscribe') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </x-admin-block>
    </div>
</x-app-layout>