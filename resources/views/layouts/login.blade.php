<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Connexion - {{ config('app.name', 'CTT Ottignies-Blocry') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <!-- Background avec dégradé subtil -->
    <div class="min-h-screen bg-gradient-to-br from-gray-50 via-white to-blue-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
        
        <!-- Container principal centré -->
        <div class="flex flex-col items-center justify-center min-h-screen px-4 py-8">
            
            <!-- Logo Section avec animation subtile -->
            <div class="mb-8 flex flex-col items-center transform transition-transform duration-300 hover:scale-105">
                <a href="/" class="inline-block">
                    <x-logo class="w-24 h-24 fill-club-blue dark:fill-club-yellow transition-colors duration-300" />
                </a>
                <!-- Nom du club sous le logo -->
                <h1 class="mt-4 text-2xl font-bold text-center text-gray-800 dark:text-gray-100">
                    CTT Ottignies-Blocry
                </h1>
                <p class="mt-1 text-sm text-center text-gray-600 dark:text-gray-400">
                    @if(request()->routeIs('login') )
                        {{ __('Connection to your member space') }}
                    @elseif (request()->routeIs('register'))
                        {{ __('Create your account. (Please contact us to validate it)') }}
                    @endif
                </p>
            </div>

            <!-- Card de connexion avec design moderne -->
            <div class="w-full max-w-md">
                <div class="bg-white/80 backdrop-blur-sm dark:bg-gray-800/80 shadow-xl rounded-2xl border border-gray-200/50 dark:border-gray-700/50 overflow-hidden">
                    
                    <!-- Header avec accent de couleur -->
                    <div class="h-2 bg-gradient-to-r from-club-blue to-club-yellow"></div>
                    
                    <!-- Contenu du formulaire -->
                    <div class="px-8 py-8">
                        {{ $slot }}
                    </div>
                    
                    <!-- Footer avec liens utiles -->
                    <div class="px-8 py-4 bg-gray-50/50 dark:bg-gray-700/50 border-t border-gray-200/50 dark:border-gray-600/50">
                        <div class="flex flex-col sm:flex-row justify-between items-center text-xs text-gray-600 dark:text-gray-400 space-y-2 sm:space-y-0">
                            <a href="/" class="hover:text-club-blue dark:hover:text-club-yellow transition-colors duration-200">
                                ← Retour au site
                            </a>
                            {{-- <div class="flex space-x-4">
                                <a href="#" class="hover:text-club-blue dark:hover:text-club-yellow transition-colors duration-200">
                                    Aide
                                </a>
                                <a href="#contact" class="hover:text-club-blue dark:hover:text-club-yellow transition-colors duration-200">
                                    Contact
                                </a>
                            </div> --}}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info supplémentaire -->
            <div class="mt-8 text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Pas encore membre ? 
                    <a href="{{ route('register') }}" class="font-medium text-club-blue dark:text-club-yellow hover:underline transition-colors duration-200">
                        Rejoignez notre club !
                    </a>
                </p>
            </div>
        </div>

        <!-- Pattern décoratif subtil (optionnel) -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 rounded-full bg-club-blue/5 dark:bg-club-yellow/5"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 rounded-full bg-club-yellow/5 dark:bg-club-blue/5"></div>
        </div>
    </div>

    <style>
        /* Amélioration des focus states pour l'accessibilité */
        .focus-visible\:ring-club-blue:focus-visible {
            --tw-ring-color: theme('colors.club.blue');
        }
        
        /* Animation d'entrée subtile */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }
        
        /* Styles pour le backdrop blur sur les navigateurs plus anciens */
        @supports not (backdrop-filter: blur(8px)) {
            .backdrop-blur-sm {
                background-color: rgba(255, 255, 255, 0.95);
            }
            .dark .backdrop-blur-sm {
                background-color: rgba(31, 41, 55, 0.95);
            }
        }
    </style>
</body>
</html>