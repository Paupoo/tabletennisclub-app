<form action="{{ route('contact.store') }}" method="POST" x-data="contactForm('{{ old('interest') }}', {{ old('membership_family_members', 1) }}, {{ old('membership_competitors', 0) }}, {{ old('membership_training_sessions', 0) }})">
    @csrf

    <x-forms.antispam-fields />
    
    <div class="grid md:grid-cols-2 gap-6 mb-6">
        <div>
            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
            <input type="text" id="first_name" name="first_name" required value="{{ old('first_name') }}"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors"
                placeholder="Votre nom prénom">
            @error('first_name')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Nom de famille *</label>
            <input type="text" id="last_name" name="last_name" required value="{{ old('last_name') }}"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors"
                placeholder="Votre nom de famille">
            @error('last_name')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Adresse Email *</label>
            <input type="email" id="email" name="email" required value="{{ old('email') }}"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors"
                placeholder="votre@email.com">
            @error('email')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
        <div class="mb-6">
            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Numéro de Téléphone</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors"
                placeholder="06 12 34 56 78">
            @error('phone')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="mb-6" x-data="{ selectedInterest: '{{ old('interest') }}' }">
        <label for="interest" class="block text-sm font-medium text-gray-700 mb-2">Je suis intéressé par *</label>
        <select id="interest" name="interest" required @change="onRequestTypeChange" x-model="selectedInterest"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors">
            <option value="">Sélectionnez une option</option>
            @foreach (\App\Enums\ContactReasonEnum::cases() as $contactReason)
            <option value="{{ $contactReason->name }}" {{ old('interest') == $contactReason->name ? 'selected' : '' }}>{{ $contactReason->getLabel() }}</option>    
            @endforeach
        </select>
        @error('interest')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div x-show="showMembershipFields" x-transition
        class="mb-6 bg-blue-50 p-6 rounded-lg space-y-4 border border-blue-200">
        <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                </path>
            </svg>
            Informations sur votre adhésion
        </h4>

        <!-- Nombre de membres -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de membres de la famille</label>
            <select x-model="familyMembers" @change="validateCompetitors()" name="membership_family_members"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                <option value="1">1 personne</option>
                <option value="2">2 personnes</option>
                <option value="3">3 personnes</option>
                <option value="4">4 personnes</option>
                <option value="5">5 personnes ou plus</option>
            </select>
        </div>

        <!-- Nombre de compétiteurs -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Nombre de membres souhaitant participer aux compétitions
            </label>
            <select x-model="competitors" name="membership_competitors"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                <template x-for="i in parseInt(familyMembers) + 1" :key="i - 1">
                    <option :value="i - 1"
                        x-text="i-1 === 0 ? 'Aucun compétiteur' : (i-1) + ' compétiteur' + (i-1 > 1 ? 's' : '')">
                    </option>
                </template>
            </select>
            <p class="text-xs text-gray-500 mt-1">
                Licence récréative : 60€ | Licence compétition : 125€
            </p>
        </div>

        <!-- Séances d'entraînement -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Nombre de séances d'entraînement souhaitées
            </label>
            <select x-model="trainingSessions" name="membership_training_sessions"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                <option value="0">Aucune séance d'entraînement</option>
                <option value="1">1 séance par semaine</option>
                <option value="2">2 séances par semaine</option>
                <option value="3">3 séances par semaine</option>
            </select>
        </div>

        <!-- CONSERVATION TOTALE DU CALCULATEUR DE COÛT -->
        <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
            <h5 class="font-semibold text-gray-900 mb-3 flex items-center">
                Estimation du coût annuel
            </h5>

            <!-- Détail des coûts -->
            <div class="space-y-2 mb-3 text-sm text-gray-600">
                <div x-show="getRecreationalMembers() > 0" class="flex justify-between">
                    <span>Licence récréative (<span x-text="getRecreationalMembers()"></span> membre<span
                            x-show="getRecreationalMembers() > 1">s</span>)</span>
                    <span x-text="(getRecreationalMembers() * 60) + '€'"></span>
                </div>
                <div x-show="competitors > 0" class="flex justify-between">
                    <span>Licence compétition (<span x-text="competitors"></span> membre<span
                            x-show="competitors > 1">s</span>)</span>
                    <span x-text="(competitors * 125) + '€'"></span>
                </div>
                <div x-show="trainingSessions > 0" class="flex justify-between">
                    <span>Séances d'entraînement (<span x-text="trainingSessions"></span> séance<span
                            x-show="trainingSessions > 1">s</span>)</span>
                    <span x-text="calculateTrainingCost() + '€'"></span>
                </div>
            </div>

            <div class="border-t pt-3">
                <div class="flex justify-between items-center">
                    <span class="text-xl font-bold text-club-blue">Total annuel</span>
                    <span class="text-2xl font-bold text-club-blue" x-text="calculateTotal() + '€'"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-6">
        <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
        <textarea id="message" name="message" rows="4" required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors resize-none"
            placeholder="Parlez-nous de votre expérience au tennis de table ou de toute question que vous avez...">{{ old('message') }}</textarea>
        @error('message')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div class="mb-6">
        <label class="flex items-start">
            <input type="checkbox" name="consent" required {{ old('consent') ? 'checked' : '' }}
                class="mt-1 mr-3 h-4 w-4 text-club-blue focus:ring-club-blue border-gray-300 rounded-sm">
            <span class="text-sm text-gray-600">
                J'accepte que mes données soient utilisées pour me recontacter concernant ma demande. *
            </span>
        </label>
        @error('consent')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
    </div>

    <!-- Champs cachés pour les données calculées -->
    <input type="hidden" name="membership_total_cost" :value="showMembershipFields ? calculateTotal() : 0">

    <!-- BOUTON DE SOUMISSION CLASSIQUE (plus de logique AJAX) -->
    <button type="submit"
        class="w-full bg-club-blue text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-blue-700 transition-colors transform hover:scale-[1.02]">
        <span class="flex items-center justify-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
            </svg>
            Envoyer le Message
        </span>
    </button>

    <p class="text-xs text-gray-500 mt-4 text-center">
        * Champs obligatoires
    </p>
</form>
