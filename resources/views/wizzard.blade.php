<x-app-layout>
    <div class="bg-white rounded-lg shadow-lg overflow-hidden" x-data="wizard()" @wizard-next.window="nextStep()"
        @wizard-prev.window="prevStep()">

        <!-- Progress Bar -->
        <div class="h-1 bg-gray-200">
            <div class="h-full bg-blue-500 transition-all duration-300"
                :style="`width: ${((currentStep + 1) / steps.length) * 100}%`">
            </div>
        </div>

        <!-- Steps Indicator -->
        <div class="px-6 py-8">
            <div class="flex justify-between mb-8 relative">
                <template x-for="(step, idx) in steps" :key="step.id">
                    <div class="flex flex-col items-center flex-1 relative z-10">
                        <!-- Circle -->
                        <div
                            :class="`w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-all ${
                                                                                                                                                                                                                                                                                                                                                                                                                                                                        idx <= currentStep
                                                                                                                                                                                                                                                                                                                                                                                                                                                                            ? 'bg-blue-500 text-white'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                            : 'bg-gray-200 text-gray-600'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                    }`">
                            <span x-text="idx + 1"></span>
                        </div>
                        <!-- Label -->
                        <div class="text-xs font-medium text-gray-600 mt-2 text-center max-w-xs">
                            <span x-text="step.title"></span>
                        </div>
                    </div>
                </template>
                <!-- Connector Lines -->
                <div class="absolute top-5 left-0 right-0 flex justify-between px-5 -z-0">
                    <template x-for="(step, idx) in steps.slice(0, -1)" :key="'line-' + idx">
                        <div
                            :class="`flex-1 h-0.5 mx-2 ${
                                                                                                                                                                                                                                                                                                                                                                                                                                                                        idx < currentStep ? 'bg-blue-500' : 'bg-gray-200'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                    }`">
                        </div>
                    </template>
                </div>
            </div>

            <!-- Step Content -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-2" x-text="steps[currentStep].title">
                </h2>
                <p class="text-gray-600 mb-6" x-text="steps[currentStep].description">
                </p>

                <div class="mt-6">
                    <!-- Step 0: Personal Info -->
                    <template x-if="currentStep === 0">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Saison
                                </label>
                                <x-select-input x-model="formData.season">
                                    @php
                                        $seasons = App\Models\Season::orderBy('start_at')->get();
                                    @endphp
                                    @foreach ($seasons as $season)
                                        <option value="{{ $season->id }}">{{ $season->name }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                        </div>
                    </template>

                    <!-- Step 1: Company Info -->
                    <template x-if="currentStep === 1">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Nom de l'entreprise
                            </label>
                            <input type="text" x-model="formData.company" placeholder="Ma super entreprise"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        </div>
                    </template>

                    <!-- Step 2: Plan Selection -->
                    <template x-if="currentStep === 2">
                        <div class="space-y-3">
                            <template x-for="plan in ['starter', 'professional', 'enterprise']" :key="plan">
                                <label
                                    class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:border-blue-300 transition"
                                    :class="`border-${formData.plan === plan ? 'blue' : 'gray'}-200`"
                                    :style="`border-color: ${formData.plan === plan ? '#3b82f6' : '#e5e7eb'}`">
                                    <input type="radio" name="plan" :value="plan" x-model="formData.plan"
                                        class="w-4 h-4">
                                    <span class="ml-3 flex-1">
                                        <span class="font-medium text-gray-900"
                                            x-text="`${plan.charAt(0).toUpperCase() + plan.slice(1)}`"></span>
                                        <p class="text-sm text-gray-500" x-text="`Description du plan ${plan}`"></p>
                                    </span>
                                </label>
                            </template>
                        </div>
                    </template>

                    <!-- Step 3: Confirmation -->
                    <template x-if="currentStep === 3">
                        <div class="bg-gray-50 rounded-lg p-6 space-y-4">
                            <div class="flex justify-between pb-3 border-b border-gray-200">
                                <span class="text-gray-600">Nom:</span>
                                <span class="font-medium" x-text="formData.name || 'Non renseigné'"></span>
                            </div>
                            <div class="flex justify-between pb-3 border-b border-gray-200">
                                <span class="text-gray-600">Entreprise:</span>
                                <span class="font-medium" x-text="formData.company || 'Non renseigné'"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Plan:</span>
                                <span class="font-medium capitalize" x-text="formData.plan"></span>
                            </div>
                            <label class="flex items-center mt-4 pt-4 border-t border-gray-200">
                                <input type="checkbox" x-model="formData.newsletter" class="w-4 h-4">
                                <span class="ml-2 text-sm text-gray-700">S'abonner à la newsletter</span>
                            </label>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="flex gap-3 justify-between">
                <button @click="prevStep()" :disabled="isFirstStep"
                    :class="`flex items-center gap-2 px-6 py-2 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition ${isFirstStep ? 'cursor-not-allowed' : ''}`">
                    <i class="fas fa-chevron-left"></i>
                    Précédent
                </button>

                <button @click="nextStep()" :disabled="isLastStep"
                    :class="`flex items-center gap-2 px-6 py-2 bg-blue-500 text-white rounded-lg font-medium hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition ${isLastStep ? 'cursor-not-allowed' : ''}`">
                    <span x-text="isLastStep ? 'Terminer' : 'Suivant'"></span>
                    <i class="fas fa-chevron-right" x-show="!isLastStep"></i>
                </button>
            </div>

            <!-- Submit Button (Last Step) -->
            <template x-if="isLastStep">
                <button @click="submit()"
                    class="w-full mt-4 px-6 py-3 bg-green-500 text-white rounded-lg font-medium hover:bg-green-600 transition">
                    Soumettre
                </button>
            </template>
        </div>
    </div>

    <!-- Debug Info -->
    <div class="mt-6 text-xs text-gray-500 text-center">
        Étape <span x-text="currentStep + 1"></span>/<span x-text="steps.length"></span>
    </div>
    </div>

    <script>
        function wizard() {
            return {
                currentStep: 0,
                steps: [{
                        id: 'season-info',
                        title: 'Season',
                        description: 'Vérifions la saison'
                    },
                    {
                        id: 'licence',
                        title: 'Choisir une licence',
                        description: 'Sélectionnez la licence qui vous convient'
                    },
                    {
                        id: 'trainings',
                        title: 'Choisir vos entraînements',
                        description: 'L\'accès aux entraînements dirigés sont conditionnés à votre niveau et aux disponibilités. Le comité se réserve le droit de vous attribuer un autre entraînement en fonction de votre niveau afin de garantir une homogénéité dans les différents groupes afin que tous puissent progresser.'
                    },
                    {
                        id: 'confirm',
                        title: 'Confirmation',
                        description: 'Vérifiez vos informations'
                    }
                ],
                formData: {
                    season: '',
                    company: '',
                    plan: 'starter',
                    newsletter: false
                },

                get isFirstStep() {
                    return this.currentStep === 0;
                },

                get isLastStep() {
                    return this.currentStep === this.steps.length - 1;
                },

                nextStep() {
                    if (!this.isLastStep) {
                        this.currentStep++;
                    }
                },

                prevStep() {
                    if (!this.isFirstStep) {
                        this.currentStep--;
                    }
                },

                submit() {
                    console.log('Données soumises:', this.formData);
                    alert('Wizard complété!\n\nVérifiez la console pour les données.');
                }
            };
        }
    </script>

</x-app-layout>
