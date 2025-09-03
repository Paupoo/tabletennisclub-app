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
                            <p class="text-gray-600"> {{ config('app.club_building_name') }}</p>
                            <p class="text-gray-600">{{ config('app.club_street') }}</p>
                            <p class="text-gray-600">{{ config('app.club_zip_code') }} {{ config('app.club_city') }}</p>
                            <div class="mt-2 text-sm font-medium text-gray-500">
                                <iframe lg:width="425" lg:height="350" width="255" height="210" src="https://www.openstreetmap.org/export/embed.html?bbox=4.585273861885072%2C50.665709466584%2C4.593122005462647%2C50.66949034073618&amp;layer=mapnik" style="border: 1px solid black"></iframe><br/><a href="https://www.openstreetmap.org/?#map=18/50.667600/4.589198&amp;layers=N" target="_blank" rel="noopener noreferrer">Afficher une carte plus grande</a>
                            </div>
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
                            <p inert class="text-gray-600">{{ config('app.club_phone_number') }}</p>
                            <p class="text-sm text-gray-500">Lun-Ven: 16h-20h</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="shrink-0 w-12 h-12 bg-club-yellow rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Email</h3>
                            <p inertclass="text-gray-600">{{ config('app.club_email') }}</p>
                            <p class="text-sm text-gray-500">Réponse en général dans les 48h</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulaire de contact -->
            <div class="animate-on-scroll">
                <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Envoyez-nous un Message</h3>
                    
                    <!-- Affichage des messages de succès -->
                    @if(session('success'))
                        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex">
                                <svg class="w-5 h-5 text-green-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <p class="text-green-800">{{ session('success') }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Affichage des erreurs générales -->
                    @if(session('error'))
                        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex">
                                <svg class="w-5 h-5 text-red-400 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                <p class="text-red-800">{{ session('error') }}</p>
                            </div>
                        </div>
                    @endif
                    
                    <!-- FORMULAIRE HYBRIDE : Alpine.js pour la logique + Submit classique -->
                    <x-contact-form />
                    
                </div>
            </div>
        </div>
    </div>

    {{-- <style>
        .club-blue { color: #1e40af; }
        .bg-club-blue { background-color: #1e40af; }
        .bg-club-yellow { background-color: #fbbf24; }
        .text-club-blue { color: #1e40af; }
        .focus\:ring-club-blue:focus { --tw-ring-color: #1e40af; }
    </style> --}}
</section>