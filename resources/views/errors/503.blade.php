<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance en cours - CTT Ottignies-Blocry</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'club-blue': '#1e40af',
                        'club-blue-light': '#3b82f6',
                        'club-yellow': '#fbbf24',
                        'club-yellow-light': '#fcd34d'
                    }
                }
            }
        }
    </script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .animate-pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        .animate-bounce-slow {
            animation: bounce 3s infinite;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in {
            animation: fadeIn 1s ease-out;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-white to-blue-50 min-h-screen">
    <div class="min-h-screen flex items-center justify-center px-4" x-data="maintenanceApp()">
        <div class="max-w-2xl w-full text-center fade-in">
            <!-- Icône principale -->
            <div class="mb-8">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-club-blue rounded-full animate-pulse-slow">
                    <span class="text-4xl">🏓</span>
                </div>
            </div>

            <!-- Titre principal -->
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-6">
                Maintenance en cours
            </h1>

            <!-- Description -->
            <div class="space-y-4 mb-8 text-gray-600">
                <p class="text-lg md:text-xl leading-relaxed">
                    Le site du CTT Ottignies-Blocry est actuellement en maintenance pour améliorer votre expérience.
                </p>
                <p class="text-base">
                    Notre équipe travaille activement pour remettre le service en ligne dans les plus brefs délais.
                </p>
            </div>

            <!-- Compteur de temps estimé -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8 border border-gray-100">
                <div class="flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-club-blue mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-sm font-medium text-gray-700">Temps estimé</span>
                </div>
                
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-2xl font-bold text-club-blue" x-text="timeLeft.hours"></div>
                        <div class="text-xs text-gray-500">Heures</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-2xl font-bold text-club-blue" x-text="timeLeft.minutes"></div>
                        <div class="text-xs text-gray-500">Minutes</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-2xl font-bold text-club-blue" x-text="timeLeft.seconds"></div>
                        <div class="text-xs text-gray-500">Secondes</div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <div class="bg-gray-200 rounded-full h-2">
                        <div class="bg-gradient-to-r from-club-blue to-club-blue-light h-2 rounded-full transition-all duration-1000" :style="`width: ${progress}%`"></div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Progression estimée</p>
                </div>
            </div>

            <!-- Informations de contact -->
            <div class="bg-gradient-to-r from-club-blue to-club-blue-light rounded-xl p-6 text-white">
                <h3 class="text-lg font-semibold mb-3">Besoin d'aide urgente ?</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <a href="mailto:contact@cttottignies.be" class="hover:underline">{{ config('app.club_email') }}</a>
                    </div>
                    <div class="flex items-center justify-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <span>{{ config('app.club_phone_number') }}</span>
                    </div>
                </div>
            </div>

            <!-- Bouton de rechargement -->
            <div class="mt-8">
                <button 
                    @click="checkStatus()"
                    :disabled="isChecking"
                    class="inline-flex items-center px-6 py-3 bg-white border-2 border-club-blue text-club-blue rounded-lg font-semibold hover:bg-club-blue hover:text-white focus:outline-none focus:ring-2 focus:ring-club-blue focus:ring-offset-2 transition-all duration-200 disabled:opacity-50"
                    :class="{ 'animate-pulse': isChecking }"
                >
                    <svg class="w-4 h-4 mr-2" :class="{ 'animate-spin': isChecking }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span x-text="isChecking ? 'Vérification...' : 'Vérifier le statut'"></span>
                </button>
            </div>

            <!-- Footer -->
            <footer class="mt-12 text-sm text-gray-400">
                <p>Merci pour votre patience</p>
                <p class="mt-1">Dernière mise à jour : <span x-text="lastUpdated"></span></p>
            </footer>
        </div>
    </div>

    <script>
        function maintenanceApp() {
            return {
                timeLeft: {
                    hours: 2,
                    minutes: 30,
                    seconds: 0
                },
                progress: 25,
                isChecking: false,
                lastUpdated: new Date().toLocaleString('fr-FR'),
                
                init() {
                    this.startCountdown();
                    // Mise à jour de la progression toutes les minutes
                    setInterval(() => {
                        if (this.progress < 100) {
                            this.progress += Math.random() * 2;
                        }
                    }, 60000);
                },
                
                startCountdown() {
                    setInterval(() => {
                        if (this.timeLeft.seconds > 0) {
                            this.timeLeft.seconds--;
                        } else if (this.timeLeft.minutes > 0) {
                            this.timeLeft.minutes--;
                            this.timeLeft.seconds = 59;
                        } else if (this.timeLeft.hours > 0) {
                            this.timeLeft.hours--;
                            this.timeLeft.minutes = 59;
                            this.timeLeft.seconds = 59;
                        }
                        
                        // Formatage pour afficher toujours 2 chiffres
                        this.timeLeft.hours = String(this.timeLeft.hours).padStart(2, '0');
                        this.timeLeft.minutes = String(this.timeLeft.minutes).padStart(2, '0');
                        this.timeLeft.seconds = String(this.timeLeft.seconds).padStart(2, '0');
                    }, 1000);
                },
                
                async checkStatus() {
                    this.isChecking = true;
                    
                    try {
                        // Simulation d'une vérification d'API
                        await new Promise(resolve => setTimeout(resolve, 2000));
                        
                        // Ici vous pourriez faire un appel API réel pour vérifier le statut
                        // const response = await fetch('/api/status');
                        // if (response.ok) {
                        //     window.location.reload();
                        // }
                        
                        this.lastUpdated = new Date().toLocaleString('fr-FR');
                    } catch (error) {
                        console.error('Erreur lors de la vérification du statut:', error);
                    } finally {
                        this.isChecking = false;
                    }
                }
            }
        }
    </script>
</body>
</html>