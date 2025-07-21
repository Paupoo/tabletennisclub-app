<nav class="{{ $fixed ?? true ? 'fixed' : '' }} w-full bg-white/95 backdrop-blur-xs z-50 shadow-xs" x-data="navigation">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <div class="shrink-0">
                    <a href="{{ route('home') }}">
                        <h1 class="text-2xl md:text-xl lg:text-2xl font-bold text-club-blue">🏓 CTT Ottignies-Blocry</h1>
                    </a>
                </div>
            </div>
            
            <!-- Desktop Navigation -->
            <div class="hidden md:block">
                <div class="ml-10 flex items-baseline space-x-4">
                    <a href="{{ route('home') }}" class="text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('home') ? 'text-club-blue' : '' }}">
                        Accueil
                    </a>
                    <a href="{{ route('results') }}" class="text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('results') ? 'text-club-blue' : '' }}">
                        Résultats
                    </a>
                    <a href="{{ route('events') }}" class="text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('events') ? 'text-club-blue' : '' }}">
                        Événements
                    </a>
                    </a>
                    <a href="{{ route('public.articles.index') }}" class="text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors {{ request()->routeIs('events') ? 'text-club-blue' : '' }}">
                        Nouvelles
                    </a>
                    <a href="{{ route('home') }}#contact" class="text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    @auth
                        <a href="{{ route('dashboard') }}" class="bg-club-yellow text-black px-4 py-2 rounded-md text-sm font-medium hover:bg-club-yellow-light transition-colors">
                        {{ __('My account') }}
                        </a>
                    @endauth
                    @guest
                        <a href="{{ route('home') }}#join" class="bg-club-blue text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-club-blue-light transition-colors">
                        Rejoindre
                        </a>
                        <a href="{{ route('login') }}" class="bg-club-yellow text-black px-4 py-2 rounded-md text-sm font-medium hover:bg-club-yellow-light transition-colors">
                        {{ __('Login') }}
                        </a>
                    @endguest
                </div>
            </div>
            
            <!-- Mobile menu button -->
            <div class="md:hidden">
                <button @click="toggleMobileMenu()" class="text-gray-900 hover:text-club-blue">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile Navigation -->
    <div x-show="mobileMenuOpen" x-transition @click.away="closeMobileMenu()" class="md:hidden bg-white border-t">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <a href="{{ route('home') }}" @click="closeMobileMenu()" class="block text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-base font-medium">Accueil</a>
            <a href="{{ route('results') }}" @click="closeMobileMenu()" class="block text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-base font-medium">Résultats</a>
            <a href="{{ route('events') }}" @click="closeMobileMenu()" class="block text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-base font-medium">Événements</a>
            <a href="{{ route('public.articles.index') }}" @click="closeMobileMenu()" class="block text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-base font-medium">Nouvelles</a>
            <a href="{{ route('home') }}#contact" @click="closeMobileMenu()" class="block text-gray-900 hover:text-club-blue px-3 py-2 rounded-md text-base font-medium">Contact</a>
            @guest
                <a href="{{ route('home') }}#join" @click="closeMobileMenu()" class="block bg-club-blue text-white px-3 py-2 rounded-md text-base font-medium">Rejoindre</a>
                <a href="{{ route('login') }}" @click="closeMobileMenu()" class="block bg-club-yellow text-black px-3 py-2 rounded-md text-base font-medium">{{ __('Login') }}</a>
            @endguest
            @auth
                <a href="{{ route('dashboard') }}" @click="closeMobileMenu()" class="block bg-club-yellow text-black px-3 py-2 rounded-md text-base font-medium">{{ __('My Account') }}</a>
            @endauth
        </div>
    </div>
</nav>
