<section id="schedule" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Horaires et activités</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Rejoignez-nous pour nos séances d'entraînement régulières et nos tournois, bienvenues à tous les supporters les vendredis soir.
            </p>
        </div>

        <!-- Affiché uniquement sur les smartphones -->
        <div class="block md:hidden">
            <x-schedule-mini-overview :schedules="$schedules ?? []" :compact="false" />
        </div>

        <!-- Affiché uniquement sur les tablettes (iPad par ex.) -->
        <div class="hidden md:block lg:hidden">
            <x-schedule-week-overview :schedules="$schedules ?? []" />
        </div>

        <!-- Affiché uniquement sur les ordinateurs -->
        <div class="hidden lg:block">
            <x-schedule-calendar-view :schedules="$schedules ?? []" />
        </div>
        
        <!-- Call to Action -->
        <div class="text-center mt-12 animate-on-scroll">
            <div class="bg-gradient-to-r from-club-blue to-club-blue-light rounded-2xl p-8 text-white">
                <h3 class="text-2xl font-bold mb-4">Prêt à Commencer ?</h3>
                <p class="text-xl mb-6 opacity-90">
                    Rejoignez-nous pour une séance d'essai gratuite !
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#contact" class="bg-club-yellow text-club-blue px-8 py-3 rounded-lg font-semibold hover:bg-club-yellow-light transition-colors">
                        Réserver une Séance d'Essai
                    </a>
                    <a href="#join" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-club-blue transition-colors">
                        Devenir Membre
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
