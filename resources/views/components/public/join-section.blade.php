<section id="join" class="h-auto flex items-center py-20 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">{{ __('Ready to join us?') }}</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Devenez membre de notre famille de tennis de table dès aujourd'hui. Tous les niveaux sont les bienvenus&nbsp;!
            </p>
        </div>

        <!-- Avantages de l'adhésion -->
        <div class="bg-linear-to-r from-club-blue to-club-blue-light rounded-2xl p-8 text-white mb-12 animate-on-scroll">
            <div class="text-center mb-8">
                <h3 class="text-2xl font-bold mb-4">Avantages de l'affilation</h3>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="flex items-center">
                    <span class="text-club-yellow mr-3 text-xl">✓</span>
                    <span>{{ __('Access to tables and equipment advice') }}</span>
                </div>
                <div class="flex items-center">
                    <span class="text-club-yellow mr-3 text-xl">✓</span>
                    <span>{{ __('Weekly training sessions') }}</span>
                </div>
                <div class="flex items-center">
                    <span class="text-club-yellow mr-3 text-xl">✓</span>
                    <span>{{ __('Championship participation opportunities') }}</span>
                </div>
                <div class="flex items-center">
                    <span class="text-club-yellow mr-3 text-xl">✓</span>
                    <span>{{ __('Social events and club championships') }}</span>
                </div>
                <div class="flex items-center">
                    <span class="text-club-yellow mr-3 text-xl">✓</span>
                    <span>Assurance en cas de blessure</span>
                </div>
                <div class="flex items-center">
                    <span class="text-club-yellow mr-3 text-xl">✓</span>
                    <span>{{ __('Access to club facilities') }}</span>
                </div>
            </div>
        </div>

        <!-- Tarifs -->
        <div class="grid lg:grid-cols-2 gap-8 mb-12">
            <!-- Affiliation obligatoire -->
            <div class="bg-white rounded-2xl shadow-lg border-2 border-club-blue p-8 animate-on-scroll">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-club-blue rounded-full flex items-center justify-center mx-auto mb-4">
                        <x-icon name="o-user-group" class="w-8 h-8 text-white" />
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Affiliations</h3>
                    <p class="text-gray-600 mb-4">{{ __('Minimum - Recreational licence') }}</p>
                    <!-- Nouvelle présentation des tarifs -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-lg mx-auto">
                        <!-- Licence Récréative -->
                        <div
                            class="bg-club-yellow/10 border-1 border-club-yellow rounded-xl p-4 hover:shadow-md transition-shadow">
                            <div class="font-semibold text-black mb-1">
                                Licence Récréative
                            </div>
                            <div class="text-3xl font-bold text-club-blue mb-1">60€</div>
                            <p class="text-xs text-gray-600">Par an et par personne</p>
                        </div>
                        <!-- Licence Compétitive -->
                        <div
                            class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4 hover:shadow-md transition-shadow">
                            <div class="font-semibold text-black mb-1">
                                Licence Compétitive
                            </div>
                            <div class="text-3xl font-bold text-club-blue mb-1">125€</div>
                            <p class="text-xs text-gray-600">Par an et par personne</p>
                        </div>
                        <div class="space-y-3 mb-6 text-left">
                            <div class="flex items-center text-sm">
                                <span class="text-club-blue mr-2">•</span>
                                <span>{{ __('AFTT recreational licence') }}</span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-club-blue mr-2">•</span>
                                <span>{{ __('Civil liability insurance') }}</span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-club-blue mr-2">•</span>
                                <span>{{ __('Free access to free slots') }}</span>
                            </div>
                        </div>
                        <div class="space-y-3 mb-6 text-left">
                            <div class="flex items-center text-sm">
                                <span class="text-club-blue mr-2">•</span>
                                <span>{{ __('AFTT competitive licence') }}</span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-club-blue mr-2">•</span>
                                <span>{{ __('Civil liability insurance') }}</span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-club-blue mr-2">•</span>
                                <span>{{ __('Free access to free slots') }}</span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-club-blue mr-2">•</span>
                                <span>{{ __('Priority access to directed training') }}</span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="text-club-blue mr-2">•</span>
                                <span>{{ __('Competition participation') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                
            </div>




            <!-- Entraînements optionnels -->
            <div class="bg-white rounded-2xl shadow-lg border-2 border-club-yellow p-8 animate-on-scroll">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-club-yellow rounded-full flex items-center justify-center mx-auto mb-4">
                        <x-icon name="o-bolt" class="w-8 h-8 text-club-blue" />
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ __('Directed Training') }}</h3>
                    <p class="text-gray-600 mb-4">{{ __('Optional - With qualified coach') }}</p>
                </div>

                <div class="space-y-4 mb-6">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-semibold">{{ __('1st session') }}</span>
                            <span class="text-2xl font-bold text-club-blue">90€</span>
                        </div>
                        <p class="text-sm text-gray-600">{{ __('Per year - 1 session/week') }}</p>
                    </div>

                    <div class="bg-club-yellow/10 rounded-lg p-4 border border-club-yellow">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-semibold">{{ __('Additional sessions') }}</span>
                            <span class="text-2xl font-bold text-club-blue">80€</span>
                        </div>
                        <p class="text-sm text-gray-600">{{ __('Per year - 10€ discount') }}</p>
                        <div class="mt-2 text-xs text-club-blue font-medium">
                            💡 Valable aussi pour les membres d'une même famille
                        </div>
                    </div>
                </div>

                <div class="space-y-2 text-sm text-gray-600">
                    <div class="flex items-center">
                        <span class="text-club-yellow mr-2">•</span>
                        <span>{{ __('Personalised technical training') }}</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-club-yellow mr-2">•</span>
                        <span>{{ __('Progress tailored to your level') }}</span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-club-yellow mr-2">•</span>
                        <span>{{ __('Groups of 6 to 10 players maximum') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg p-8 animate-on-scroll" x-data="priceCalculator">
            <h3 class="text-2xl font-bold text-gray-900 mb-6 text-center">Calculez Votre Cotisation</h3>

            <div class="grid md:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <!-- Nombre de membres -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de membres de la
                            famille</label>
                        <select x-model="familyMembers" @change="validateCompetitors()"
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
                        <select x-model="competitors"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <template x-for="i in parseInt(familyMembers) + 1" :key="i - 1">
                                <option :value="i - 1"
                                    x-text="i-1 === 0 ? 'Aucun compétiteur' : (i-1) + ' compétiteur' + (i-1 > 1 ? 's' : '')">
                                </option>
                            </template>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            Licence récréative : 60€ | Licence compétitive : 125€
                        </p>
                    </div>

                    <!-- Séances d'entraînement -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre de séances d'entraînement dirigé souhaitées
                        </label>
                        <select x-model="trainingSessions"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-club-blue focus:border-transparent">
                            <option value="0">{{ __('No training sessions') }}</option>
                            <option value="1">{{ __('1 session per week') }}</option>
                            <option value="2">{{ __('2 sessions per week') }}</option>
                            <option value="3">{{ __('3 sessions per week') }}</option>
                        </select>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-6">
                    <h4 class="text-lg font-semibold mb-4">{{ __('Summary') }}</h4>

                    <div class="space-y-3 mb-4">
                        <div class="flex justify-between">
                            <span>Affiliation (<span x-text="familyMembers"></span> membre<span
                                    x-show="familyMembers > 1">s</span>)</span>
                            <span class="font-semibold" x-text="calculateBase() + '€'"></span>
                        </div>

                        <div x-show="trainingSessions > 0">
                            <div class="flex justify-between">
                                <span>1ère séance d'entraînement</span>
                                <span class="font-semibold" x-text="familyMembers > 1 ? '80 €' : '90 €'"></span>
                            </div>
                            <div x-show="trainingSessions > 1" class="flex justify-between">
                                <span>Séances supplémentaires (<span x-text="trainingSessions - 1"></span>)</span>
                                <span class="font-semibold" x-text="((trainingSessions - 1) * 80) + '€'"></span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t pt-3">
                        <div class="flex justify-between text-xl font-bold text-club-blue">
                            <span>Total annuel</span>
                            <span x-text="calculateTotal() + '€'"></span>
                        </div>
                        {{-- <p class="text-sm text-gray-600 mt-1">
                            Soit <span class="font-semibold" x-text="Math.round(calculateTotal() / 12) + '€'"></span> par mois
                        </p> --}}
                    </div>
                </div>
            </div>

            <div class="text-center mt-8">
                <button @click="goToContactWithData()"
                    class="bg-club-blue text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-club-blue-light transition-colors inline-flex items-center cursor-pointer">
                    <x-icon name="o-chat-bubble-left-ellipsis" class="w-5 h-5 mr-2" />
                    Nous Contacter pour S'inscrire
                </button>
            </div>
        </div>

        <!-- Informations complémentaires -->
        <div class="mt-12 bg-blue-50 rounded-lg p-6 animate-on-scroll">
            <div class="flex items-start">
                <div class="shrink-0">
                    <x-icon name="o-information-circle" class="w-6 h-6 text-club-blue mt-1" />
                </div>
                <div class="ml-3">
                    <h4 class="text-lg font-semibold text-gray-900 mb-2">Informations importantes</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• L'affiliation annuelle est obligatoire et inclut la licence fédérale. Il n'est pas
                            obligatoire de participer aux championnats (licence récréative)</li>
                        <li>{{ __('• Directed training sessions are optional and take place in groups') }}</li>
                        <li>{{ __('• Automatic discount from the 2nd session or 2nd family member') }}</li>
                        <li>{{ __('• Instalment payment option (contact us)') }}</li>
                        <li>{{ __('• Free trial session for new members') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>
