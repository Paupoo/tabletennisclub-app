<footer class="bg-gray-900 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid md:grid-cols-3 gap-8">
            <div>
                <h3 class="text-2xl font-bold mb-4">🏓 CTT Ottignies-Blocry</h3>
                <p class="text-gray-400 mb-4">
                    Votre destination de choix pour le tennis de table à Ottignies et environs. Rejoignez notre communauté dès aujourd'hui !
                </p>
            </div>
            
            <div>
                <h4 class="text-lg font-semibold mb-4">Liens Rapides</h4>
                <ul class="space-y-2">
                    <li><a href="{{ route('home') }}" class="text-gray-400 hover:text-white transition-colors">Accueil</a></li>
                    <li><a href="{{ route('results') }}" class="text-gray-400 hover:text-white transition-colors">Résultats</a></li>
                    <li><a href="{{ route('events') }}" class="text-gray-400 hover:text-white transition-colors">Événements</a></li>
                    <li><a href="#contact" class="text-gray-400 hover:text-white transition-colors">Contact</a></li>
                </ul>
            </div>
            
            <div>
                <h4 class="text-lg font-semibold mb-4">Informations de Contact</h4>
                <div class="space-y-2 text-gray-400">
                    <div class="flex gap-4 items-start">
                        <p>
                            📍
                        </p>
                        <div class="flex flex-col gap-1">
                            <a href="{{ config('app.club_osm_link') }}" target="_blank" rel="noopener noreferrer"><p>
                                {{ config('app.club_street', 'some street 123') }}
                            </p>
                            <p>
                                {{ config('app.club_zip_code', '0000') }} {{ config('app.club_city', 'Somewhere') }}</p>
                            </p>
                            </a>
                        </div>
                    </div>
                    <div class="flex gap-4 items-start" inert>
                        <p>📞</p>
                        <p> {{ config('app.club_phone_number', '+32 123 12 34 56') }}</p>
                    </div>
                    <div class="flex gap-4 items-start">
                        <p>✉️</p>
                        <p><a href="mailto:{{ config('app.club_email', 'nomail@nomail.com') }}">{{ config('app.club_email', 'nomail@nomail.com') }}</a></p>
                    </div>  
                </div>
            </div>
        </div>
        
        <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
    <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
        <div class="flex flex-col sm:flex-row items-center space-y-2 sm:space-y-0 sm:space-x-4 text-sm">
            <p class="flex items-center">
                Made with 
                <svg class="w-4 h-4 mx-1 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                </svg>
                by <span class="font-medium text-white ml-1">Aurélien Paulus</span>
            </p>
            <span class="hidden sm:inline text-gray-600">•</span>
            <p class="text-xs">
                Powered by 
                <span class="text-blue-400 font-medium"><a href="https://tailwindcss.com/">TailwindCSS</a></span> • 
                <span class="text-pink-400 font-medium"><a href="https://alpinejs.dev/">AlpineJS</a></span> • 
                <span class="text-red-400 font-medium"><a href="https://laravel.com/">Laravel</a></span> • 
                <span class="text-purple-400 font-medium"><a href="https://livewire.laravel.com/">Livewire</a></span>
            </p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center space-y-2 sm:space-y-0 sm:space-x-4 text-sm">
            <p>&copy; {{ date('Y') }} CTT Ottignies-Blocry. Tous droits réservés.</p>
            <span class="hidden sm:inline text-gray-600">•</span>
            <a href="#" class="text-gray-400 hover:text-white transition-colors text-xs" 
               onclick="showLicense(); return false;">
                Licence MIT
            </a>
        </div>
    </div>
</div>

<!-- Modal de licence MIT -->
<div id="licenseModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-2xl w-full max-h-[80vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-900">Licence MIT</h3>
                <button onclick="hideLicense()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
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

<script>
function showLicense() {
    document.getElementById('licenseModal').classList.remove('hidden');
    document.getElementById('licenseModal').classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function hideLicense() {
    document.getElementById('licenseModal').classList.add('hidden');
    document.getElementById('licenseModal').classList.remove('flex');
    document.body.style.overflow = 'auto';
}

// Fermer la modal en cliquant à l'extérieur
document.getElementById('licenseModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideLicense();
    }
});

// Fermer la modal avec la touche Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('licenseModal').classList.contains('hidden')) {
        hideLicense();
    }
});
</script>
    </div>
</footer>
