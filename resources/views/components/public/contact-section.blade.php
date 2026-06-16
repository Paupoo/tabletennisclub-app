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
                    @if (($club?->latitude && $club?->longitude) || ($club?->street && $club?->city_code && $club?->city_name))
                    <div class="flex items-start">
                        <div class="shrink-0 w-12 h-12 bg-club-blue rounded-lg flex items-center justify-center">
                            <x-icon name="o-map-pin" class="w-6 h-6 text-white" />
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Adresse</h3>
                            <p class="text-gray-600"> {{ $club->building_name }}</p>
                            <p class="text-gray-600">{{ $club->street }}</p>
                            <p class="text-gray-600">{{ $club->zip_code }} {{ $club->city_name }}</p>
                            @if ($club->latitude && $club->longitude)
                                <div class="mt-2 text-sm font-medium text-gray-500">

                                    <div id="map" 
                                        class="h-[250px] w-[250px] md:h-[350px] md:w-[350px] rounded-lg shadow-sm"
                                        data-lat="{{ $club->latitude ?? 50.667593 }}"
                                        data-lon="{{ $club->longitude ?? 4.589143 }}">
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if ($club?->phone_contact)
                        <div class="flex items-start">
                            <div class="shrink-0 w-12 h-12 bg-club-blue rounded-lg flex items-center justify-center">
                                <x-icon name="o-phone" class="w-6 h-6 text-white" />
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">{{ __('Phone') }}</h3>
                                <p inert class="text-gray-600">{{ $club->phone_contact ?? __('Not documented') }}</p>
                                <p class="text-sm text-gray-500">Lun-Ven: 16h-20h</p>
                            </div>
                        </div>
                    @endif

                    @if ($club?->email_contact)
                        <div class="flex items-start">
                            <div class="shrink-0 w-12 h-12 bg-club-yellow rounded-lg flex items-center justify-center">
                                <x-icon name="o-envelope" class="w-6 h-6 text-white" />
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">Email</h3>
                                <p inertclass="text-gray-600">{{ $club->email_contact }}</p>
                                <p class="text-sm text-gray-500">{{ __('Usually responds within 48 hours') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Formulaire de contact -->
            <div class="animate-on-scroll">
                <div class="bg-white rounded-2xl shadow-lg p-8 border border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Envoyez-nous un Message</h3>

                    <!-- Affichage des messages de succès -->
                    @if (session('success'))
                        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex">
                                <x-icon name="o-check" class="w-5 h-5 text-green-400 mr-2 mt-0.5" />
                                <p class="text-green-800">{{ session('success') }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Affichage des erreurs générales -->
                    @if (session('error'))
                        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex">
                                <x-icon name="o-x-mark" class="w-5 h-5 text-red-400 mr-2 mt-0.5" />
                                <p class="text-red-800">{{ session('error') }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- FORMULAIRE HYBRIDE : Alpine.js pour la logique + Submit classique -->
                    <x-public.contact-form />

                </div>
            </div>
        </div>
    </div>
</section>