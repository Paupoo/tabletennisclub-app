<section id="contact" class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-2 gap-12 items-start">
            <!-- Informations de contact -->
            <div class="animate-on-scroll">
                <h2 class="text-4xl font-bold text-gray-900 mb-6">Contactez-Nous</h2>
                <p class="text-xl text-gray-600 mb-8">
                    Des questions ? Envie de nous rendre visite ? Nous serions ravis de vous entendre !
                </p>
                
                <div class="space-y-6">
                    <div class="flex items-start">
                        <div class="shrink-0 w-12 h-12 bg-club-blue rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Adresse</h3>
                            <p class="text-gray-600">{{ config('club.address', '123 Avenue du Centre Sportif') }}</p>
                            <p class="text-gray-600">75001 Paris, France</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="shrink-0 w-12 h-12 bg-club-blue rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Téléphone</h3>
                            <p class="text-gray-600">{{ config('club.phone', '(555) 123-4567') }}</p>
                            <p class="text-sm text-gray-500">Lun-Ven: 9h-18h</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="shrink-0 w-12 h-12 bg-club-blue rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Email</h3>
                            <p class="text-gray-600">{{ config('club.email', 'info@acettc.com') }}</p>
                            <p class="text-sm text-gray-500">Réponse sous 24h</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="shrink-0 w-12 h-12 bg-club-yellow rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Horaires d'Ouverture</h3>
                            <div class="text-gray-600 space-y-1">
                                <p>Lun-Ven: 18h-22h</p>
                                <p>Sam-Dim: 9h-18h</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulaire de contact -->
            <div class="animate-on-scroll">
                <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Envoyez-nous un Message</h3>
                    
                    <form action="{{ route('contact.store') }}" method="POST" x-data="{ submitted: false, loading: false }" @submit.prevent="
                        loading = true;
                        fetch('{{ route('contact.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(Object.fromEntries(new FormData($event.target)))
                        }).then(response => response.json()).then(data => {
                            loading = false;
                            submitted = true;
                            setTimeout(() => submitted = false, 5000);
                            $event.target.reset();
                        }).catch(error => {
                            loading = false;
                            console.error('Error:', error);
                        });
                    ">
                        @csrf
                        <div class="grid md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nom Complet *</label>
                                <input type="text" id="name" name="name" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors"
                                       placeholder="Votre nom complet">
                            </div>
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Adresse Email *</label>
                                <input type="email" id="email" name="email" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors"
                                       placeholder="votre@email.com">
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Numéro de Téléphone</label>
                            <input type="tel" id="phone" name="phone" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors"
                                   placeholder="06 12 34 56 78">
                        </div>
                        
                        <div class="mb-6">
                            <label for="interest" class="block text-sm font-medium text-gray-700 mb-2">Je suis intéressé par *</label>
                            <select id="interest" name="interest" required 
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors">
                                <option value="">Sélectionnez une option</option>
                                <option value="membership">Devenir membre</option>
                                <option value="coaching">Réserver un essai</option>
                                <option value="tournament">Informations sur les compétitions</option>
                                <option value="rental">Devenir supporter ?</option>
                                <option value="partnership">Partenariat/Sponsoring</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                        
                        <div class="mb-6">
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message *</label>
                            <textarea id="message" name="message" rows="4" required
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent transition-colors resize-none" 
                                      placeholder="Parlez-nous de votre expérience au tennis de table ou de toute question que vous avez..."></textarea>
                        </div>
                        
                        <div class="mb-6">
                            <label class="flex items-start">
                                <input type="checkbox" required class="mt-1 mr-3 h-4 w-4 text-club-blue focus:ring-club-blue border-gray-300 rounded-sm">
                                <span class="text-sm text-gray-600">
                                    J'accepte que mes données soient utilisées pour me recontacter concernant ma demande. *
                                </span>
                            </label>
                        </div>
                        
                        <button type="submit" 
                                :disabled="loading"
                                class="w-full bg-club-blue text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-club-blue-light transition-colors transform hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none" 
                                :class="{ 'bg-club-yellow hover:bg-club-yellow': submitted }">
                            <span x-show="!loading && !submitted" class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                Envoyer le Message
                            </span>
                            <span x-show="loading" class="flex items-center justify-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Envoi en cours...
                            </span>
                            <span x-show="submitted" class="flex items-center justify-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Message Envoyé !
                            </span>
                        </button>
                        
                        <p class="text-xs text-gray-500 mt-4 text-center">
                            * Champs obligatoires
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
