<x-app-layout :breadcrumbs="$breadcrumbs">
    <x-admin-block>
        <!-- En-tête avec informations principales -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-4 space-y-4 sm:space-y-0">
                <!-- Profil et nom -->
                <div class="flex items-center space-x-4">
                    <div class="flex-1">
                        <div class="flex items-center space-x-2 mb-2">
                            <h2 class="text-xl sm:text-2xl font-bold text-club-blue">
                                {{ $user->first_name }} {{ $user->last_name }}
                            </h2>
                        </div>
                    </div>
                </div>

                <!-- Stats rapides -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-4">
                    <div class="bg-blue-50 rounded-lg p-3 text-center border border-blue-200">
                        <div class="text-lg sm:text-xl font-bold text-blue-600">15</div>
                        <div class="text-xs text-blue-700">Victoires</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3 text-center border border-yellow-200">
                        <div class="text-lg sm:text-xl font-bold text-yellow-600">8</div>
                        <div class="text-xs text-yellow-700">Défaites</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3 text-center border border-green-200">
                        <div class="text-lg sm:text-xl font-bold text-green-600">2</div>
                        <div class="text-xs text-green-700">Performances</div>
                    </div>
                    <div class="bg-red-50 rounded-lg p-3 text-center border border-red-200">
                        <div class="text-lg sm:text-xl font-bold text-red-600">3</div>
                        <div class="text-xs text-red-700">Contres</div>
                    </div>
                </div>
            </div>

            <!-- Actions principales -->
            <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3" x-data="{ emailTemplateOpen: false, showDeleteModal: false }">
                @can('update', $user)
                    <a href="{{ route('users.edit', $user) }}"
                        class="inline-flex items-center justify-center px-3 py-2 bg-club-blue hover:bg-club-blue-light text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                            </path>
                        </svg>
                        {{ __('Edit') }}
                    </a>
                    @if ($user->email_verified_at == null)
                        <form action="{{ route('admin.users.invite-existing-user', $user) }}" method="post">
                            @csrf
                            <x-primary-button
                                class="inline-flex items-center justify-center px-3 py-2 bg-club-blue hover:bg-club-blue-light text-white text-sm font-medium rounded-lg transition-colors">
                                <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <x-ui.icon name="rocket-launch" />
                                </svg>
                                {{ __('Send invite') }}
                            </x-primary-button>
                        </form>
                    @else
                        <x-primary-button
                            class="inline-flex items-center justify-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">RESET
                            PASSWORD</x-primary-button>
                    @endif
                @endcan

                @can('delete', $user)
                    <x-primary-button @click="showDeleteModal = true"
                        class="inline-flex items-center justify-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                            </path>
                        </svg>
                        {{ __('Delete') }}
                    </x-primary-button>
                    <!-- Modal de confirmation de suppression -->
                    <div x-show="showDeleteModal" x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto"
                        style="display: none;">

                        <div class="flex items-center justify-center min-h-screen px-4">
                            <div x-show="showDeleteModal" @click.outside="showDeleteModal = false"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                x-transition:leave="transition ease-in duration-200"
                                x-transition:leave-start="opacity-100 transform scale-100"
                                x-transition:leave-end="opacity-0 transform scale-95"
                                class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-auto">
                                <div
                                    class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z">
                                        </path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                                    Confirmer la suppression
                                </h3>
                                <p class="text-sm text-gray-600 text-center mb-6">
                                    Êtes-vous sûr de vouloir supprimer le membre <strong>{{ $user->first_name }}
                                        {{ $user->last_name }}</strong> ? Cette action est irréversible.
                                </p>
                                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                                    <x-primary-button @click="showDeleteModal = false"
                                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                        Annuler
                                    </x-primary-button>
                                    <form action="{{ route('users.destroy', $user) }}" method="post">
                                        @csrf
                                        @method('DELETE')
                                        <x-primary-button
                                            class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm text-center">
                                            Supprimer définitivement
                                        </x-primary-button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endcan

                @can('sendEmail', $user)
                    <x-primary-button @click="emailTemplateOpen = !emailTemplateOpen"
                        class="inline-flex items-center justify-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors"
                        type="button">
                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                            </path>
                        </svg>
                        Envoyer un email
                        <svg class="w-4 h-4 ml-2 transition-transform" :class="{ 'rotate-180': emailTemplateOpen }"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                            </path>
                        </svg>
                    </x-primary-button>
                @endcan
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Colonne gauche: Infos personnelles et joueur -->
            <div class="lg:col-span-1 space-y-6">

                <!-- Informations joueur -->
                <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                    <h3 class="text-lg font-bold text-club-blue mb-4">Informations joueur</h3>
                    <div class="space-y-4">

                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                            <svg class="w-5 h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V4a2 2 0 114 0v2m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2">
                                </path>
                            </svg>
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">Licence</p>
                                <p class="text-sm text-gray-800">{{ $user->licence ?? 'Pas de licence' }} -
                                    <span
                                        class="text-sm font-semibold {{ $user->is_competitor ? 'text-green-700' : 'text-blue-600' }}">
                                        {{ $user->is_competitor ? 'Compétiteur' : 'Loisir' }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                            <x-ui.icon name="tag" />
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">{{ __('Categories') }} (TODO)
                                </p>
                                <x-ui.badge variant="open">Messieurs</x-ui.badge>
                                <x-ui.badge variant="success">Dame</x-ui.badge>
                                <x-ui.badge variant="warning">Veterans</x-ui.badge>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                            <x-ui.icon name="bolt" />
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">{{ __('Ranking') }}</p>
                                <p class="text-sm text-gray-800">{{ $user->ranking ?? 'NA' }}</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                            <x-ui.icon name="envelope-closed" />
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">Email</p>
                                <p class="text-sm text-gray-800 break-all">{{ $user->email }}</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                            <x-ui.icon name="phone" />
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">Téléphone</p>
                                <p class="text-sm text-gray-800">{{ $user->phone_number ?? 'Non renseigné' }}</p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                            <x-ui.icon name="home" />
                            <div>
                                <p class="text-xs font-medium text-gray-500 uppercase">Adresse</p>
                                <p class="text-sm text-gray-800">{{ $user->street ?? 'Non renseignée' }}</p>
                                <p class="text-sm text-gray-800">{{ $user->city_code }} {{ $user->city_name }}</p>
                            </div>
                        </div>

                        @if ($user->birthdate)
                            <div class="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <x-ui.icon name="birthday" />
                                <div>
                                    <p class="text-xs font-medium text-gray-500 uppercase">Né(e) le</p>
                                    <p class="text-sm text-gray-800">{{ $user->birthdate->format('d/m/Y') }} <span
                                            class="text-gray-500">({{ $user->age ?? 'Unknown' }} ans)</span></p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            <!-- Colonne centre/droite: Équipes et Souscriptions -->
            <div class="lg:col-span-2 space-y-6">
                @can('manageSubscription', $user)
                    <!-- Souscriptions -->
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6" x-data="{ subscriptionModal: false, confirmSubscription: false }"">
                        <h3 class="text-lg font-bold text-club-blue
                    mb-4">{{ __('Subscription') }}
                        </h3>

                        @if ($subscription)
                            <div class="space-y-4">
                                <div class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <div class="flex items-center justify-between mb-3" x-data="{ trainingModal: false, showPaymentInfo: false }">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 mb-4">Saison
                                                {{ $subscription->season->name }}</p>
                                            {{-- <p class="text-xs text-gray-600">
                                                @if ($subscription->is_competitive)
                                                    <span
                                                        class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800">Compétitif</span>
                                                @else
                                                    <span
                                                        class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">Loisirs</span>
                                                @endif
                                            </p> --}}
                                        </div>

                                        <!-- Status badge -->
                                        <div class="text-right">
                                            @php
                                                $statusColors = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'confirmed' => 'bg-blue-100 text-blue-800',
                                                    'paid' => 'bg-green-100 text-green-800',
                                                    'cancelled' => 'bg-red-100 text-red-800',
                                                    'refunded' => 'bg-gray-100 text-gray-800',
                                                ];
                                                $statusLabels = [
                                                    'pending' => 'En attente',
                                                    'confirmed' => 'Confirmée',
                                                    'paid' => 'Payée',
                                                    'cancelled' => 'Annulée',
                                                    'refunded' => 'Remboursée',
                                                ];
                                            @endphp
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$subscription->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ $statusLabels[$subscription->status] ?? $subscription->status }}
                                            </span>

                                            @if ($subscription->status === 'pending')
                                                <x-secondary-button type="button" @click="trainingModal = true">
                                                    {{ __('Modify') }}
                                                </x-secondary-button>
                                                <x-primary-button type="button" @click="trainingModal = true">
                                                    {{ __('Confirm') }}
                                                </x-primary-button>
                                            @elseif($subscription->status === 'confirmed')
                                                <x-secondary-button type="button" @click="confirmSubscription = true">
                                                    {{ __('Show payment info') }}
                                                </x-secondary-button>
                                            @endif

                                            <!-- Overlay -->
                                            <div x-show="trainingModal" x-transition.opacity
                                                class="fixed inset-0 bg-black/50 z-40" @click="trainingModal = false"
                                                style="display: none;">
                                            </div>

                                            <!-- Modals -->
                                            <div x-show="trainingModal" x-transition
                                                @keydown.escape.window="trainingModal = false"
                                                class="fixed inset-0 flex items-center justify-center z-50 p-4"
                                                style="display: none;">
                                                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 relative"
                                                    @click.outside="trainingModal = false">
                                                    <h2 class="text-lg font-bold text-gray-900 mb-4 text-center">
                                                        {{ __('Training packs') }}
                                                    </h2>

                                                    <form method="POST"
                                                        action="{{ route('admin.subscriptions.addTrainingPack', $subscription) }}">
                                                        @csrf
                                                        <div class="space-y-3">
                                                            @forelse($trainingPacks as $trainingPack)
                                                                <div
                                                                    class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                                                    <div class="flex items-center">
                                                                        <x-checkbox-input name="training_packs[]"
                                                                            value="{{ $trainingPack->id }}"
                                                                            id="training-pack-{{ $trainingPack->id }}"
                                                                            :disabled="$subscription->status !== 'pending'" :checked="$subscription->trainingPacks->contains(
                                                                                $trainingPack->id,
                                                                            )"
                                                                            class="mr-3" />
                                                                        <label for="training-pack-{{ $trainingPack->id }}"
                                                                            class="text-sm font-medium text-gray-900 cursor-pointer">
                                                                            {{ $trainingPack->name }}
                                                                        </label>
                                                                    </div>
                                                                    @if ($trainingPack->price)
                                                                        <span class="text-sm text-gray-600">
                                                                            {{ number_format($trainingPack->price, 2) }}&nbsp;€
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            @empty
                                                                <p class="text-sm text-gray-500 italic">
                                                                    {{ __('No training packs available') }}
                                                                </p>
                                                            @endforelse
                                                        </div>

                                                        @if ($trainingPacks->count() > 0)
                                                            <div class="flex justify-end mt-6 space-x-3">
                                                                <x-secondary-button type="button"
                                                                    @click="trainingModal = false"
                                                                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg font-medium transition text-sm">
                                                                    {{ __('Cancel') }}
                                                                </x-secondary-button>

                                                                <x-primary-button>
                                                                    {{ __('Save training packs') }}
                                                                </x-primary-button>
                                                            </div>
                                                        @endif
                                                    </form>
                                                </div>
                                            </div>

                                            {{-- Confirm subscriptions --}}
                                            <div x-show="confirmSubscription" x-transition
                                                @keydown.escape.window="confirmSubscription = false"
                                                class="fixed inset-0 flex items-center justify-center z-50 p-4"
                                                style="display: none;">
                                                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 relative"
                                                    @click.outside="confirmSubscription = false">
                                                    <h2 class="text-lg font-bold text-gray-900 mb-4 text-center">
                                                        {{ __('Confirm Subscription') }}
                                                    </h2>

                                                    <form method="POST"
                                                        action="{{ route('admin.subscriptions.addTrainingPack', $subscription) }}">
                                                        @csrf
                                                        <div class="space-y-3">
                                                            @forelse($trainingPacks as $trainingPack)
                                                                <div
                                                                    class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                                                    <div class="flex items-center">
                                                                        <x-checkbox-input name="training_packs[]"
                                                                            value="{{ $trainingPack->id }}"
                                                                            id="training-pack-{{ $trainingPack->id }}"
                                                                            :disabled="$subscription->status !== 'pending'" :checked="$subscription->trainingPacks->contains(
                                                                                $trainingPack->id,
                                                                            )"
                                                                            class="mr-3" />
                                                                        <label for="training-pack-{{ $trainingPack->id }}"
                                                                            class="text-sm font-medium text-gray-900 cursor-pointer">
                                                                            {{ $trainingPack->name }}
                                                                        </label>
                                                                    </div>
                                                                    @if ($trainingPack->price)
                                                                        <span class="text-sm text-gray-600">
                                                                            {{ number_format($trainingPack->price, 2) }}&nbsp;€
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            @empty
                                                                <p class="text-sm text-gray-500 italic">
                                                                    {{ __('No training packs available') }}
                                                                </p>
                                                            @endforelse
                                                        </div>

                                                        @if ($trainingPacks->count() > 0)
                                                            <div class="flex justify-end mt-6 space-x-3">
                                                                <x-secondary-button type="button"
                                                                    @click="confirmSubscription = false"
                                                                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg font-medium transition text-sm">
                                                                    {{ __('Cancel') }}
                                                                </x-secondary-button>

                                                                <x-primary-button>
                                                                    {{ __('Save training packs') }}
                                                                </x-primary-button>
                                                            </div>
                                                        @endif
                                                    </form>
                                                </div>
                                            </div>

                                            <div x-show="paymentInfoModal" x-transition
                                                @keydown.escape.window="paymentInfoModal = false"
                                                class="fixed inset-0 flex items-center justify-center z-50 p-4"
                                                style="display: none;">
                                                <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 relative"
                                                    @click.outside="paymentInfoModal = false">
                                                    <h2 class="text-lg font-bold text-gray-900 mb-4 text-center">
                                                        {{ __('Training packs') }}
                                                    </h2>

                                                    <form method="POST"
                                                        action="{{ route('admin.subscriptions.addTrainingPack', $subscription) }}">
                                                        @csrf
                                                        <div class="space-y-3">
                                                            @forelse($trainingPacks as $trainingPack)
                                                                <div
                                                                    class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                                                    <div class="flex items-center">
                                                                        <x-checkbox-input name="training_packs[]"
                                                                            value="{{ $trainingPack->id }}"
                                                                            id="training-pack-{{ $trainingPack->id }}"
                                                                            :disabled="$subscription->status !== 'pending'" :checked="$subscription->trainingPacks->contains(
                                                                                $trainingPack->id,
                                                                            )"
                                                                            class="mr-3" />
                                                                        <label for="training-pack-{{ $trainingPack->id }}"
                                                                            class="text-sm font-medium text-gray-900 cursor-pointer">
                                                                            {{ $trainingPack->name }}
                                                                        </label>
                                                                    </div>
                                                                    @if ($trainingPack->price)
                                                                        <span class="text-sm text-gray-600">
                                                                            {{ number_format($trainingPack->price, 2) }}&nbsp;€
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            @empty
                                                                <p class="text-sm text-gray-500 italic">
                                                                    {{ __('No training packs available') }}
                                                                </p>
                                                            @endforelse
                                                        </div>

                                                        @if ($trainingPacks->count() > 0)
                                                            <div class="flex justify-end mt-6 space-x-3">
                                                                <x-secondary-button type="button"
                                                                    @click="paymentInfoModal = false"
                                                                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg font-medium transition text-sm">
                                                                    {{ __('Cancel') }}
                                                                </x-secondary-button>

                                                                <x-primary-button>
                                                                    {{ __('Save training packs') }}
                                                                </x-primary-button>
                                                            </div>
                                                        @endif
                                                    </form>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <!-- Détails supplémentaires -->
                                    <div class="grid grid-cols-2 gap-2 mt-3 text-xs">
                                        <div class="bg-gray-50 p-2 rounded">
                                            <p class="text-gray-600">Cotisation:</p>
                                            <p class="font-semibold text-gray-900">
                                                {{ number_format($subscription->subscription_price, 2, ',', ' ') }} €

                                                @if ($subscription->is_competitive)
                                                    (Compétitif)
                                                @else
                                                    (Loisirs)
                                                @endif
                                            </p>
                                        </div>
                                        @if ($subscription->trainings_count > 0)
                                            <div class="bg-gray-50 p-2 rounded">
                                                <p class="text-gray-600">{{ $subscription->trainings_count }}
                                                    entraînements:</p>
                                                <p class="font-semibold text-gray-900">
                                                    {{ number_format($subscription->trainings_count * $subscription->training_unit_price, 2, ',', ' ') }}
                                                    €</p>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Détails montants -->
                                    @if ($subscription->status === 'confirmed')
                                        <div class="grid grid-cols-3 gap-3 pt-3 border-t border-gray-200">
                                            <div class="text-center">
                                                <p class="text-xs text-gray-600 mb-1">Montant dû</p>
                                                <p class="text-sm font-bold text-gray-900">
                                                    {{ $subscription->amount_due }}&nbsp;€</p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-xs text-gray-600 mb-1">Payé</p>
                                                <p class="text-sm font-bold text-green-600">
                                                    {{ $subscription->amount_paid }}&nbsp;€</p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-xs text-gray-600 mb-1">Solde</p>
                                                @php
                                                    $balance = $subscription->amount_due - $subscription->amount_paid;
                                                @endphp
                                                <p
                                                    class="text-sm font-bold {{ $balance <= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $subscription->amount_due - $subscription->amount_paid }}&nbsp;€
                                                </p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                    </path>
                                </svg>
                                <p class="text-sm text-gray-700">Aucune souscription pour le moment</p>
                                <!-- Modal de confirmation de suppression -->
                                <div x-data="{ subscriptionModal: false, licenceType: '' }" class="relative">
                                    <!-- Bouton d'ouverture -->
                                    <x-primary-button @click="subscriptionModal = true" type="button" class="mt-4">
                                        {{ __('Subscribe') }}
                                    </x-primary-button>

                                    <!-- Overlay -->
                                    <div x-show="subscriptionModal" x-transition.opacity
                                        class="fixed inset-0 bg-black/50 z-40" @click="subscriptionModal = false"></div>

                                    <!-- Contenu de la modal -->
                                    <div x-show="subscriptionModal" x-transition
                                        @keydown.escape.window="subscriptionModal = false"
                                        class="fixed inset-0 flex items-center justify-center z-50 p-4"
                                        style="display:none;">
                                        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 relative"
                                            @click.outside="subscriptionModal = false"
                                            x-transition:enter="transition ease-out duration-300"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-200"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95">
                                            <!-- En-tête -->
                                            <div class="flex flex-col items-center text-center">
                                                <div
                                                    class="flex items-center justify-center w-12 h-12 bg-blue-100 rounded-full mb-4">
                                                    <svg class="w-6 h-6 text-blue-600" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 11c0 1.104-.896 2-2 2H8a2 2 0 110-4h2a2 2 0 012 2zm0 0c0 1.104.896 2 2 2h2a2 2 0 000-4h-2a2 2 0 00-2 2z" />
                                                    </svg>
                                                </div>

                                                <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                                    {{ __('Choose your licence type') }}
                                                </h3>
                                                <p class="text-sm text-gray-600 mb-6">
                                                    {{ __('If you want to play in competition and participate to the interclubs, please select the competitive licence.') }}
                                                </p>
                                            </div>

                                            <!-- Formulaire -->
                                            <form method="POST"
                                                action="{{ route('admin.seasons.subscribe', $currentSeason) }}"
                                                class="space-y-6">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $user->id }}">

                                                <!-- Options -->
                                                <div class="space-y-3">
                                                    <label
                                                        class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                                        <span
                                                            class="text-sm font-medium text-gray-700">{{ __('Competitive licence') }}</span>
                                                        <input type="radio" name="type" value="competitive"
                                                            x-model="licenceType"
                                                            class="text-blue-600 focus:ring-blue-500">
                                                    </label>

                                                    <label
                                                        class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-50">
                                                        <span
                                                            class="text-sm font-medium text-gray-700">{{ __('Leisure licence') }}</span>
                                                        <input type="radio" name="type" value="casual"
                                                            x-model="licenceType"
                                                            class="text-blue-600 focus:ring-blue-500">
                                                    </label>
                                                </div>

                                                <!-- Boutons d’action -->
                                                <div
                                                    class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3 pt-4">
                                                    <x-secondary-button type="button" @click="subscriptionModal = false"
                                                        class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg font-medium transition text-sm">
                                                        {{ __('Cancel') }}
                                                    </x-secondary-button>

                                                    <x-secondary-button type="submit" :disabled="licenceType === ''"
                                                        class="flex-1 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white px-4 py-2 rounded-lg font-medium transition text-sm">
                                                        {{ __('Confirm') }}
                                                    </x-secondary-button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        @endif
                    </div>
                @endcan


                <!-- Équipes -->
                @if ($user->teams->count() > 0)
                    <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
                        <h3 class="text-lg font-bold text-club-blue mb-4">
                            Équipe{{ $user->teams->count() > 1 ? 's' : '' }} ({{ $user->teams->count() }})
                        </h3>

                        <div class="space-y-4">
                            @foreach ($user->teams as $team)
                                <div class="p-3 rounded-lg border border-gray-200">
                                    <!-- En-tête équipe -->
                                    <div class="flex items-center justify-between mb-3">
                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-900">{{ $team->name }}
                                                {{ $team->league->level }} • Division {{ $team->league->division }}
                                            </h4>
                                        </div>
                                    </div>

                                    <!-- Liste des joueurs -->
                                    <div class="flex flex-wrap gap-2">
                                        @forelse ($team->users->sortBy('ranking') as $teammate)
                                            <a href="{{ route('users.show', $teammate) }}"
                                                class="flex items-center space-x-2 px-2.5 py-1.5 bg-gray-50 rounded-full text-xs">

                                                <span class="font-medium text-gray-700">
                                                    {{ $teammate->first_name }} {{ $teammate->last_name[0] . '.' }}
                                                    @if ($teammate->id === $user->id)
                                                        <span class="text-gray-500">(vous)</span>
                                                    @endif
                                                </span>
                                                <span class="text-gray-500">{{ $teammate->ranking }}</span>
                                            </a>
                                        @empty
                                            <p class="text-sm text-gray-500">Pas encore de joueurs</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="bg-white border border-gray-200 rounded-lg p-6 text-center">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                        <p class="text-sm text-gray-600">Ce joueur n'est actuellement membre d'aucune équipe</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Section Modification du Mot de Passe -->
        @can('updatePassword', $user)
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
                <div class="flex items-start space-x-3 mb-6">
                    <div class="flex-shrink-0 mt-1">
                        <svg class="w-6 h-6 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                            </path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg sm:text-xl font-bold text-club-blue">{{ __('Update Password') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Ensure your account is using a long, random password to stay secure.') }}
                        </p>
                    </div>
                </div>

                <form method="post" action="{{ route('password.update') }}" class="space-y-6">
                    @csrf
                    @method('put')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                            <x-text-input id="update_password_current_password" name="current_password" type="password"
                                class="mt-1 block w-full" autocomplete="current-password" />
                            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="update_password_password" :value="__('New Password')" />
                            <x-text-input id="update_password_password" name="password" type="password"
                                class="mt-1 block w-full" autocomplete="new-password" />
                            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
                            <x-text-input id="update_password_password_confirmation" name="password_confirmation"
                                type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4 pt-4 border-t border-gray-200">
                        <x-primary-button type="submit"
                            class="bg-club-blue hover:bg-club-blue-light text-white px-6 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base">
                            <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                </path>
                            </svg>
                            {{ __('Save') }}
                        </x-primary-button>

                        @if (session('status') === 'password-updated')
                            <p x-data="{ show: true }" x-show="show"
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                x-transition:leave="transition ease-in duration-200"
                                x-transition:leave-start="opacity-100 transform scale-100"
                                x-transition:leave-end="opacity-0 transform scale-95" x-init="setTimeout(() => show = false, 3000)"
                                class="inline-flex items-center px-3 py-1 bg-green-100 text-green-700 text-sm rounded-lg">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                                {{ __('Saved.') }}
                            </p>
                        @endif
                    </div>
                </form>
            </div>
        @endcan

        <!-- Section Suppression du Compte -->
        @can('delete', $user)
            <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6" x-data="{ showDeleteModal2: false }">
                <div class="flex items-start space-x-3 mb-6">
                    <div class="flex-shrink-0 mt-1">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z">
                            </path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-lg sm:text-xl font-bold text-red-600">{{ __('Delete Account') }}</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
                        </p>
                    </div>
                </div>

                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z">
                                </path>
                            </svg>
                            <span class="text-sm font-medium text-red-800">Zone dangereuse</span>
                        </div>
                        <x-primary-button @click="showDeleteModal2 = true"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                            <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                </path>
                            </svg>
                            {{ __('Delete Account') }}
                        </x-primary-button>
                    </div>
                </div>

                <!-- Modal de confirmation de suppression de compte -->
                <div x-show="showDeleteModal2" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex items-center justify-center min-h-screen px-4">
                        <div x-show="showDeleteModal2" x-transition:enter="transition ease-out duration-300"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md mx-auto">

                            <form method="post" action="{{ route('profile.destroy') }}">
                                @csrf
                                @method('delete')

                                <div
                                    class="flex items-center justify-center w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z">
                                        </path>
                                    </svg>
                                </div>

                                <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">
                                    {{ __('Are you sure you want to delete your account?') }}
                                </h3>

                                <p class="text-sm text-gray-600 text-center mb-6">
                                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                                </p>

                                <div class="mb-6">
                                    <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />
                                    <x-text-input id="password" name="password" type="password"
                                        class="mt-1 block w-full" placeholder="{{ __('Password') }}" required />
                                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
                                </div>

                                <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-3">
                                    <x-primary-button type="button" @click="showDeleteModal2 = false"
                                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                        {{ __('Cancel') }}
                                    </x-primary-button>
                                    <x-primary-button type="submit"
                                        class="flex-1 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                                        <svg class="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                        {{ __('Delete Account') }}
                                    </x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endcan

        <!-- Section Liste des matchs -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mt-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg sm:text-xl font-bold text-club-blue">Matchs joués</h3>
                <div class="text-sm text-gray-500">
                    Saison 2024-2025
                </div>
            </div>

            <!-- Statistiques rapides en haut -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-green-50 rounded-lg p-3 text-center border border-green-200">
                    <div class="text-lg font-bold text-green-600">13</div>
                    <div class="text-xs text-green-700">Victoires</div>
                </div>
                <div class="bg-red-50 rounded-lg p-3 text-center border border-red-200">
                    <div class="text-lg font-bold text-red-600">3</div>
                    <div class="text-xs text-red-700">Défaites</div>
                </div>
                <div
                    class="bg-club-blue bg-opacity-10 rounded-lg p-3 text-center border border-club-blue border-opacity-20">
                    <div class="text-lg font-bold text-club-blue">81%</div>
                    <div class="text-xs text-club-blue">Taux victoire</div>
                </div>
            </div>

            <!-- Liste des matchs -->
            <div class="space-y-4">
                <!-- Match 1 - Victoire -->
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Journée 8 - Division 2B</p>
                                    <p class="text-xs text-gray-500">TC Rochefort vs TC Malonne</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Adversaire</p>
                                <p class="text-sm font-medium">B4</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Résultat</p>
                                <p class="text-lg font-bold text-green-600">3-1</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Date</p>
                                <p class="text-sm">15/03/24</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 2 - Défaite -->
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Journée 7 - Division 2B</p>
                                    <p class="text-xs text-gray-500">TC Salzinnes vs TC Malonne</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Adversaire</p>
                                <p class="text-sm font-medium">B6</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Résultat</p>
                                <p class="text-lg font-bold text-red-600">1-3</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Date</p>
                                <p class="text-sm">08/03/24</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Match 3 - Victoire -->
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-3 sm:space-y-0">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3">
                                <div class="flex-shrink-0">
                                    <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">Journée 6 - Division 2B</p>
                                    <p class="text-xs text-gray-500">TC Malonne vs TC Ciney</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Adversaire</p>
                                <p class="text-sm font-medium">D6</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Résultat</p>
                                <p class="text-lg font-bold text-green-600">3-0</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-gray-500">Date</p>
                                <p class="text-sm">01/03/24</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bouton "Voir plus" -->
            <div class="mt-6 text-center">
                <x-primary-button
                    class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                        </path>
                    </svg>
                    Voir tous les matchs (16)
                </x-primary-button>
            </div>
        </div>
    </x-admin-block>
</x-app-layout>
