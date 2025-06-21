<section id="schedule" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">Horaires et activités</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Rejoignez-nous pour nos séances d'entraînement régulières et nos tournois, bienvenues à tous les supporters les vendredis soir.
            </p>
        </div>
        
        <div class="max-w-4xl mx-auto">
            <div class="grid gap-4">
                @forelse($schedules ?? [] as $index => $schedule)
                    <div class="bg-white rounded-lg border border-gray-200 hover:border-club-blue hover:shadow-lg transition-all duration-300 animate-on-scroll group" 
                         style="transition-delay: {{ $index * 0.1 }}s;">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-6 space-y-4 sm:space-y-0">
                            <!-- Informations principales -->
                            <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-3 sm:space-y-0 sm:space-x-6">
                                <!-- Jour -->
                                <div class="flex items-center space-x-2">
                                    <div class="w-10 h-10 bg-club-blue rounded-full flex items-center justify-center group-hover:bg-club-blue-light transition-colors">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <span class="font-semibold text-gray-900 text-lg">{{ $schedule['day'] }}</span>
                                </div>
                                
                                <!-- Heure -->
                                <div class="flex items-center space-x-2">
                                    <div class="w-10 h-10 bg-club-yellow rounded-full flex items-center justify-center group-hover:bg-club-yellow-light transition-colors">
                                        <svg class="w-5 h-5 text-club-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <span class="text-gray-600 font-medium">{{ $schedule['time'] }}</span>
                                </div>
                                
                                <!-- Lieu (si disponible) -->
                                @if(isset($schedule['location']))
                                    <div class="flex items-center space-x-2">
                                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center group-hover:bg-gray-200 transition-colors">
                                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            </svg>
                                        </div>
                                        <span class="text-gray-500 text-sm">{{ $schedule['location'] }}</span>
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Activité et niveau -->
                            <div class="flex flex-col items-start sm:items-end space-y-2">
                                <span class="text-club-blue font-semibold text-lg">{{ $schedule['activity'] }}</span>
                                @if(isset($schedule['level']))
                                    <span class="@if($schedule['level'] === 'Débutant') bg-green-100 text-green-800 @elseif($schedule['level'] === 'Intermédiaire') bg-yellow-100 text-yellow-800 @else bg-red-100 text-red-800 @endif text-xs font-medium px-3 py-1 rounded-full">
                                        {{ $schedule['level'] }}
                                    </span>
                                @endif
                                @if(isset($schedule['coach']))
                                    <span class="text-gray-500 text-sm">Coach: {{ $schedule['coach'] }}</span>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Informations supplémentaires -->
                        @if(isset($schedule['description']) || isset($schedule['capacity']))
                            <div class="border-t border-gray-100 px-6 py-4 bg-gray-50 rounded-b-lg">
                                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center space-y-2 sm:space-y-0">
                                    @if(isset($schedule['description']))
                                        <p class="text-sm text-gray-600">{{ $schedule['description'] }}</p>
                                    @endif
                                    @if(isset($schedule['capacity']))
                                        <div class="flex items-center space-x-2 text-sm text-gray-500">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                            <span>{{ $schedule['capacity'] }} places max</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <!-- Horaires par défaut si aucune donnée fournie -->
                    <x-schedule-card :schedule="[
                        'day' => 'Lundi',
                        'time' => '20h00 - 22h00',
                        'activity' => 'Entraînement Libre',
                        'location' => 'Demeester 0',
                        'level' => 'Tous Niveaux',
                        'capacity' => 8,
                        'description' => 'Séance libre pour tous les membres du club'
                    ]" :index="0" />

                    <x-schedule-card :schedule="[
                        'day' => 'Lundi',
                        'time' => '20h30 - 22h00',
                        'activity' => 'Entraînement Libre',
                        'location' => 'Demeester -1',
                        'level' => 'Tous Niveaux',
                        'capacity' => 10,
                        'description' => 'Séance libre pour tous les membres du club'
                    ]" :index="1" />

                    <x-schedule-card :schedule="[
                        'day' => 'Lundi',
                        'time' => '18h00 - 20h00',
                        'activity' => 'Entraînement encadré',
                        'location' => 'Blocry G3',
                        'level' => 'Débutant',
                        'capacity' => 10,
                        'description' => 'Séance d\'entraînement encadrée pour les jeunes'
                    ]" :index="2" />

                    <x-schedule-card :schedule="[
                        'day' => 'Lundi',
                        'time' => '20h00 - 22h00',
                        'activity' => 'Entraînement encadré',
                        'location' => 'Blocry G3',
                        'level' => 'Tous Niveaux',
                        'capacity' => 10,
                        'description' => 'Séance d\'entraînement encadrée pour les adultes'
                    ]" :index="3" />
                    
                    <x-schedule-card :schedule="[
                        'day' => 'Mardi',
                        'time' => '20h30 - 22h00',
                        'activity' => 'Entraînement dirigé',
                        'location' => 'Demeester -1',
                        'level' => 'Intermédiaire',
                        'coach' => 'Aloïse Lejeune',
                        'capacity' => 10,
                        'description' => 'Perfectionnement pour les joueurs classés'
                    ]" :index="4" />
                    
                    <x-schedule-card :schedule="[
                        'day' => 'Mercredi',
                        'time' => '13h00 - 13h30',
                        'activity' => 'Entraînement dirigé',
                        'location' => 'Demeester -1',
                        'level' => 'Débutant',
                        'coach' => 'Éric Filée',
                        'capacity' => 8,
                        'description' => 'Initiation pour les jeunes'
                    ]" :index="5" />
                    
                    <x-schedule-card :schedule="[
                        'day' => 'Mercredi',
                        'time' => '13h30 - 15h00',
                        'activity' => 'Entraînement dirigé',
                        'location' => 'Demeester -1',
                        'level' => 'Intermédiaire',
                        'coach' => 'Éric Filée',
                        'capacity' => 8,
                        'description' => 'Perfectionnement pour les jeunes'
                    ]" :index="6" />
                    
                    <x-schedule-card :schedule="[
                        'day' => 'Vendredi',
                        'time' => '19h00 - 23h30',
                        'activity' => 'Interclubs',
                        'location' => 'Demeester (0 et -1)',
                        'description' => 'Matches de compétition à domicile. Venez nous supporter ! Chouette ambiance et beau jeu au programme'
                    ]" :index="7" />
                    
                    <x-schedule-card :schedule="[
                        'day' => 'Samedi',
                        'time' => '09h00 - 10h30',
                        'activity' => 'Entraînement dirigé',
                        'location' => 'Demeester -1',
                        'level' => 'Débutant',
                        'coach' => 'Jean-Pierre Fikany',
                        'capacity' => 8,
                        'description' => 'Initiation pour les jeunes'
                    ]" :index="8" />
                    
                    <x-schedule-card :schedule="[
                        'day' => 'Samedi',
                        'time' => '10h30 - 12h00',
                        'activity' => 'Entraînement dirigé',
                        'location' => 'Demeester -1',
                        'level' => 'Débutant',
                        'coach' => 'Jean-Pierre Fikany',
                        'capacity' => 8,
                        'description' => 'Perfectionnement pour les jeunes'
                    ]" :index="9" />
                @endforelse
            </div>
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
