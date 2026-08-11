<footer class="bg-gray-900 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-3 gap-8">
            <div>
                <h3 class="text-2xl font-bold mb-4">🏓 {{ config('club.name') }}</h3>
                <p class="text-white/70 mb-4">
                    Votre destination de choix pour le tennis de table à Ottignies et environs. Rejoignez notre
                    communauté dès aujourd'hui !
                </p>
            </div>

            <div>
                <h4 class="text-lg font-semibold mb-4">Liens Rapides</h4>
                <ul class="space-y-2">
                    <li><a href="{{ route('home') }}" class="text-white/70 hover:text-white transition-colors">Accueil</a>
                    </li>
                    <li><a href="{{ route('results') }}"
                            class="text-white/70 hover:text-white transition-colors">{{ __('Results') }}</a></li>
                    <li><a href="{{ route('eventPosts') }}"
                            class="text-white/70 hover:text-white transition-colors">{{ __('Events') }}</a></li>
                    <li><a href="#contact" class="text-white/70 hover:text-white transition-colors">Contact</a></li>
                </ul>
            </div>

            {{-- A heading with nothing under it reads as a broken page, so the whole
                 column waits for the club record. Before the club sheet is filled —
                 which is the state a fresh install starts in — there is nothing to
                 announce. --}}
            @if($club)
            <div>
                <h4 class="text-lg font-semibold mb-4">Informations de contact</h4>
                <div class="space-y-2 text-white/70">
                    <div class="flex gap-4 items-start">
                        <p>
                            📍
                        </p>
                        <div class="flex flex-col gap-1">

                                <p>
                                    {{ $club->building_name }}
                                </p>
                                <p>
                                    {{ $club->street }}
                                </p>
                                <p>
                                    {{ $club->city_code }} {{ $club->city_name }}
                                </p>

                        </div>
                    </div>
                    @if($club->phone_contact)
                    <div class="flex gap-4 items-start" inert>
                        <p>📞</p>
                        <p>{{ $club->phone_contact }}</p>
                    </div>
                    @endif
                    @if($club->email_contact)
                    <div class="flex gap-4 items-start" inert>
                        <p>✉️</p>
                        <p><a
                                href="mailto:{{ $club->email_contact }}">{{ $club->email_contact }}</a>
                        </p>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <div class="border-t border-gray-800 mt-8 pt-8 text-center text-white/70">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <div class="flex flex-col sm:flex-row items-center space-y-2 sm:space-y-0 sm:space-x-4 text-sm">
                    <p class="flex items-center">
                        Made with
                        <x-icon name="s-heart" class="w-4 h-4 mx-1 text-red-400" />
                        by <span class="font-medium text-white ml-1">Aurélien Paulus</span>
                    </p>
                    <span class="hidden sm:inline text-white/40">•</span>
                    <p class="text-xs">
                        Powered by
                        <span class="text-blue-400 font-medium"><a
                                href="https://tailwindcss.com/">TailwindCSS</a></span> •
                        <span class="text-pink-400 font-medium"><a href="https://alpinejs.dev/">AlpineJS</a></span> •
                        <span class="text-red-400 font-medium"><a href="https://laravel.com/">Laravel</a></span> •
                        <span class="text-purple-400 font-medium"><a
                                href="https://livewire.laravel.com/">Livewire</a></span>
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row items-center space-y-2 sm:space-y-0 sm:space-x-4 text-sm">
                    <p>&copy; {{ date('Y') }} {{ config('club.name') }}. Tous droits réservés.</p>
                    <span class="hidden sm:inline text-white/40">•</span>
                    <a href="#" class="text-white/70 underline hover:text-white transition-colors text-xs"
                        onclick="showLicense(); return false;">
                        Licence MIT
                    </a>
                    <span class="hidden sm:inline text-white/40">•</span>
                    <a href="https://github.com/Paupoo/tabletennisclub-app"
                        target="_blank" rel="noopener noreferrer"
                        class="flex items-center gap-1 text-white/70 hover:text-white transition-colors text-xs">
                        <x-icon name="o-code-bracket" class="w-4 h-4" />
                        GitHub
                    </a>
                </div>
            </div>
        </div>

        {{-- Cookies consent --}}
        <div
            x-data="{ show: !document.cookie.includes('cookie_consent=true') }"
            x-show="show"
            x-transition:enter="transition ease-out duration-500"
            x-transition:enter-start="opacity-0 transform translate-y-full"
            x-transition:enter-end="opacity-100 transform translate-y-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform translate-y-0"
            x-transition:leave-end="opacity-0 transform translate-y-full"
            class="fixed bottom-0 left-0 w-full bg-club-yellow-light opacity-95 backdrop-filter backdrop-blur-lg shadow-lg rounded-t-lg p-6 z-50 md:flex md:items-center md:justify-between"
        >
            <p class="text-sm text-gray-800 md:w-3/4">
                Ce site utilise des cookies uniquement pour la gestion de la connexion. En continuant à utiliser ce site, vous acceptez cette utilisation. Pour plus d'informations, consultez notre
                <button class="underline text-club-blue font-semibold" @click="showPrivacyPolicy()">{{ __('privacy policy') }}</button>.
            </p>
            <div class="mt-4 md:mt-0 md:w-1/4 md:text-right">
                <button
                    @click="document.cookie = 'cookie_consent=true; expires=' + new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toUTCString() + '; path=/; SameSite=Strict'; show = false"
                    class="w-full md:w-auto px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500 focus:ring-opacity-50 font-bold"
                >
                    J'ai compris
                </button>
            </div>
        </div>

        <!-- Modal MIT licence -->
        <div id="licenseModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl text-decoration-line font-bold text-gray-900">Licence MIT</h3>
                        <button onclick="hideLicense()" class="text-gray-400 hover:text-gray-600">
                            <x-icon name="o-x-mark" class="w-6 h-6" />
                        </button>
                    </div>
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-600 mb-4">Copyright (c) {{ date('Y') }} Aurélien Paulus</p>
                        <p class="text-gray-700 text-sm leading-relaxed">
                            Permission is hereby granted, free of charge, to any person obtaining a copy
                            of this software and associated documentation files (the "Software"), to deal
                            in the Software without restriction, including without limitation the rights
                            to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
                            copies of the Software, and to permit persons to whom the Software is
                            furnished to do so, subject to the following conditions:
                        </p>
                        <p class="text-gray-700 text-sm leading-relaxed mt-4">
                            The above copyright notice and this permission notice shall be included in all
                            copies or substantial portions of the Software.
                        </p>
                        <p class="text-gray-700 text-sm leading-relaxed mt-4">
                            THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
                            IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
                            FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
                            AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
                            LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
                            OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
                            SOFTWARE.
                        </p>
                    </div>
                    <div class="mt-6 text-center">
                        <button onclick="hideLicense()"
                            class="bg-club-blue text-white px-6 py-2 rounded-lg hover:bg-club-blue-light transition-colors">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Privacy Policy -->
        <div id="privacyModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-900">{{ __('Privacy Policy') }}</h3>
                        <button onclick="hidePrivacyPolicy()" class="text-gray-400 hover:text-gray-600">
                            <x-icon name="o-x-mark" class="w-6 h-6" />
                        </button>
                    </div>
                    <div class="prose prose-sm max-w-none">
                        <p class="text-gray-600 mb-4">{{ __('Dernière mise à jour: ') }} {{ date('d/m/Y') }}</p>
                        <div class="text-gray-700 text-sm leading-relaxed space-y-4">
                            <p>
                                Nous utilisons uniquement des cookies essentiels au bon fonctionnement de notre site.
                                Ils nous permettent de :
                            </p>
                            <ul class="list-disc list-inside space-y-1 ml-4">
                                <li>{{ __('remember your cookie consent choice') }}</li>
                                <li>{{ __('log in to your member space and use all site features') }}</li>
                            </ul>
                            <p>
                                Nous n'utilisons pas de cookies publicitaires ni de traçage externe.
                            </p>
                            <p>
                                Actuellement, nous n'utilisons aucun système de mesures et analyse, de sorte que votre visite est totalement anonyme.
                            </p>
                            <p>
                                Nous récupérons uniquement les adresses IP des robots qui spamment notre formulaire de contact afin de les bloquer au fur et à mesure.
                            </p>
                            <p>
                                Le code de ce site est complètement open source et disponible
                                <a href="https://github.com/Paupoo/tabletennisclub-app" target="_blank" rel="noopener noreferrer" class="text-blue-600 underline">ici</a>.
                            </p>
                            <p>
                                Si vous avez la moindre question, vous pouvez simplement nous écrire via notre <a href="{{ route('home') . '#contact' }}">formulaire de contact</a>.
                            </p>
                        </div>
                    </div>
                    <div class="mt-6 text-center">
                        <button onclick="hidePrivacyPolicy()"
                            class="bg-club-blue text-white px-6 py-2 rounded-lg hover:bg-club-blue-light transition-colors">
                            Fermer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
