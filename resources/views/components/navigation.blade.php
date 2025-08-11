<nav class="{{ $fixed ?? true ? 'fixed' : '' }} w-full bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm z-50 shadow-sm dark:shadow-gray-800/20 border-b border-gray-200/20 dark:border-gray-700/50 transition-colors duration-200" 
     x-data="{ ...navigation, ...darkMode }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <div class="shrink-0">
                    <a href="{{ route('home') }}">
                        <div class="flex flex-row gap-2 items-center">
                            <x-logo class="block w-auto text-club-blue dark:text-club-yellow fill-current h-9 group-hover:text-club-blue-light dark:group-hover:text-club-yellow-light transition-colors duration-200" />
                            
                            <h1 class="text-2xl md:text-xl lg:text-2xl font-bold text-club-blue dark:text-club-yellow transition-colors duration-200">
                                CTT Ottignies-Blocry
                            </h1>
                        </div>
                    </a>
                </div>
            </div>
            
            <!-- Desktop Navigation -->
            <div class="hidden md:block">
                <div class="ml-10 flex items-baseline space-x-4">
                    <a href="{{ route('home') }}" 
                       class="text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 {{ request()->routeIs('home') ? 'text-club-blue dark:text-club-yellow' : '' }}">
                        Accueil
                    </a>
                    <a href="{{ route('results') }}" 
                       class="text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 {{ request()->routeIs('results') ? 'text-club-blue dark:text-club-yellow' : '' }}">
                        Résultats
                    </a>
                    <a href="{{ route('events') }}" 
                       class="text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 {{ request()->routeIs('events') ? 'text-club-blue dark:text-club-yellow' : '' }}">
                        Événements
                    </a>
                    <a href="{{ route('public.articles.index') }}" 
                       class="text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 {{ request()->routeIs('public.articles.index') ? 'text-club-blue dark:text-club-yellow' : '' }}">
                        Nouvelles
                    </a>
                    <a href="{{ route('home') }}#contact" 
                       class="text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                        {{ __('Contact') }}
                    </a>
                    
                    <!-- Bouton toggle mode sombre -->
                    <button @click="toggle()" 
                            class="text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow p-2 rounded-md transition-colors duration-200"
                            title="Basculer le mode sombre">
                        <!-- Icône soleil (mode clair) -->
                        <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <!-- Icône lune (mode sombre) -->
                        <svg x-show="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>
                    
                    @auth
                        <a href="{{ route('dashboard') }}" 
                           class="bg-club-yellow dark:bg-club-blue text-black dark:text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-club-yellow-light dark:hover:bg-club-blue-light transition-colors duration-200">
                            {{ __('My account') }}
                        </a>
                    @endauth
                    @guest
                        <a href="{{ route('home') }}#join" 
                           class="bg-club-blue dark:bg-club-yellow text-white dark:text-black px-4 py-2 rounded-md text-sm font-medium hover:bg-club-blue-light dark:hover:bg-club-yellow-light transition-colors duration-200">
                            Rejoindre
                        </a>
                        <a href="{{ route('login') }}" 
                           class="bg-club-yellow dark:bg-club-blue text-black dark:text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-club-yellow-light dark:hover:bg-club-blue-light transition-colors duration-200">
                            {{ __('Login') }}
                        </a>
                    @endguest
                </div>
            </div>
            
            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center space-x-2">
                <!-- Toggle mode sombre mobile -->
                <button @click="toggle()" 
                        class="text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow p-2 rounded-md transition-colors duration-200">
                    <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <svg x-show="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>
                
                <button @click="toggleMobileMenu()" 
                        class="text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow transition-colors duration-200">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile Navigation -->
    <div x-show="mobileMenuOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform -translate-y-2"
         @click.away="closeMobileMenu()" 
         class="md:hidden bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-lg dark:shadow-gray-800/20">
        <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <a href="{{ route('home') }}" 
               @click="closeMobileMenu()" 
               class="block text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow hover:bg-gray-50 dark:hover:bg-gray-800 px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">
               Accueil
            </a>
            <a href="{{ route('results') }}" 
               @click="closeMobileMenu()" 
               class="block text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow hover:bg-gray-50 dark:hover:bg-gray-800 px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">
               Résultats
            </a>
            <a href="{{ route('events') }}" 
               @click="closeMobileMenu()" 
               class="block text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow hover:bg-gray-50 dark:hover:bg-gray-800 px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">
               Événements
            </a>
            <a href="{{ route('public.articles.index') }}" 
               @click="closeMobileMenu()" 
               class="block text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow hover:bg-gray-50 dark:hover:bg-gray-800 px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">
               Nouvelles
            </a>
            <a href="{{ route('home') }}#contact" 
               @click="closeMobileMenu()" 
               class="block text-gray-900 dark:text-gray-100 hover:text-club-blue dark:hover:text-club-yellow hover:bg-gray-50 dark:hover:bg-gray-800 px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">
               Contact
            </a>
            @guest
                <a href="{{ route('home') }}#join" 
                   @click="closeMobileMenu()" 
                   class="block bg-club-blue dark:bg-club-yellow text-white dark:text-black hover:bg-club-blue-light dark:hover:bg-club-yellow-light px-3 py-2 rounded-md text-base font-medium transition-colors duration-200 mt-2">
                   Rejoindre
                </a>
                <a href="{{ route('login') }}" 
                   @click="closeMobileMenu()" 
                   class="block bg-club-yellow dark:bg-club-blue text-black dark:text-white hover:bg-club-yellow-light dark:hover:bg-club-blue-light px-3 py-2 rounded-md text-base font-medium transition-colors duration-200">
                   {{ __('Login') }}
                </a>
            @endguest
            @auth
                <a href="{{ route('dashboard') }}" 
                   @click="closeMobileMenu()" 
                   class="block bg-club-yellow dark:bg-club-blue text-black dark:text-white hover:bg-club-yellow-light dark:hover:bg-club-blue-light px-3 py-2 rounded-md text-base font-medium transition-colors duration-200 mt-2">
                   {{ __('My Account') }}
                </a>
            @endauth
        </div>
    </div>
</nav>