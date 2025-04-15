<!-- Vue Blade avec intégration Livewire et Alpine.js -->
<x-app-layout>
    <!-- Container principal avec animation d'apparition -->
    <div class="max-w-7xl mx-auto p-6">
      <h1 class="text-2xl font-bold text-white mb-6">État des tables</h1>
      
      <!-- Filtres et recherche -->
      <div class="flex flex-wrap gap-4 mb-6">
        <button class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">Toutes</button>
        <button class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Disponibles</button>
        <button class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Occupées</button>
        <div class="ml-auto">
          <input type="text" placeholder="Rechercher..." class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-none">
        </div>
      </div>
      
      <!-- Grille des tables avec espacement et responsive améliorés -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        
        <!-- Table 1 - Disponible -->
        <div class="group relative rounded-xl border border-green-400 bg-gradient-to-br from-green-50 to-green-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
          <div class="absolute top-3 right-3">
            <span class="flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
          </div>
          
          <div class="flex items-center space-x-4">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-green-500 text-white font-bold text-xl shadow-inner">
              1
            </div>
            <div class="flex flex-col">
              <span class="text-green-700 font-semibold text-lg">Disponible</span>
              <span class="text-green-600 text-sm">Libre depuis 32 min</span>
            </div>
          </div>
          
          <div class="mt-4 flex justify-end">
            <button class="px-3 py-1 bg-green-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Réserver
            </button>
          </div>
        </div>
        
        <!-- Table 2 - Occupée -->
        <div class="group relative rounded-xl border border-red-400 bg-gradient-to-br from-red-50 to-red-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
          <div class="absolute top-3 right-3">
            <span class="flex h-3 w-3">
              <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
            </span>
          </div>
          
          <div class="flex items-center space-x-4">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-500 text-white font-bold text-xl shadow-inner">
              2
            </div>
            <div class="flex flex-col">
              <span class="text-red-700 font-semibold text-lg">Occupée</span>
              <span class="text-red-600 text-sm">Depuis 23 min</span>
            </div>
          </div>
          
          <div class="mt-4 bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center">
                <svg class="w-4 h-4 text-gray-600 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span class="text-gray-800 font-medium text-sm">Michel Dupont</span>
              </div>
              <span class="text-gray-500 text-xs">VS</span>
              <div class="flex items-center">
                <span class="text-gray-800 font-medium text-sm">Marie Lambert</span>
                <svg class="w-4 h-4 text-gray-600 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
              </div>
            </div>
            <div class="flex justify-center items-center mt-2">
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-red-500 h-2 rounded-full" style="width: 45%"></div>
              </div>
            </div>
          </div>
          
          <div class="mt-4 flex justify-end">
            <button class="px-3 py-1 bg-blue-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Détails
            </button>
          </div>
        </div>
        
        <!-- Table 3 - Disponible -->
        <div class="group relative rounded-xl border border-green-400 bg-gradient-to-br from-green-50 to-green-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
          <div class="absolute top-3 right-3">
            <span class="flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
          </div>
          
          <div class="flex items-center space-x-4">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-green-500 text-white font-bold text-xl shadow-inner">
              3
            </div>
            <div class="flex flex-col">
              <span class="text-green-700 font-semibold text-lg">Disponible</span>
              <span class="text-green-600 text-sm">Libre depuis 14 min</span>
            </div>
          </div>
          
          <div class="mt-4 flex justify-end">
            <button class="px-3 py-1 bg-green-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Réserver
            </button>
          </div>
        </div>
        
        <!-- Table 4 - Occupée -->
        <div class="group relative rounded-xl border border-red-400 bg-gradient-to-br from-red-50 to-red-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
          <div class="absolute top-3 right-3">
            <span class="flex h-3 w-3">
              <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
            </span>
          </div>
          
          <div class="flex items-center space-x-4">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-500 text-white font-bold text-xl shadow-inner">
              4
            </div>
            <div class="flex flex-col">
              <span class="text-red-700 font-semibold text-lg">Occupée</span>
              <span class="text-red-600 text-sm">Depuis 15 min</span>
            </div>
          </div>
          
          <div class="mt-4 bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center">
                <svg class="w-4 h-4 text-gray-600 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span class="text-gray-800 font-medium text-sm">Jean Martin</span>
              </div>
              <span class="text-gray-500 text-xs">VS</span>
              <div class="flex items-center">
                <span class="text-gray-800 font-medium text-sm">Sophie Petit</span>
                <svg class="w-4 h-4 text-gray-600 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
              </div>
            </div>
            <div class="flex justify-center items-center mt-2">
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-red-500 h-2 rounded-full" style="width: 35%"></div>
              </div>
            </div>
          </div>
          
          <div class="mt-4 flex justify-end">
            <button class="px-3 py-1 bg-blue-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Détails
            </button>
          </div>
        </div>
        
        <!-- Table 5 - Disponible -->
        <div class="group relative rounded-xl border border-green-400 bg-gradient-to-br from-green-50 to-green-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
          <div class="absolute top-3 right-3">
            <span class="flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
          </div>
          
          <div class="flex items-center space-x-4">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-green-500 text-white font-bold text-xl shadow-inner">
              5
            </div>
            <div class="flex flex-col">
              <span class="text-green-700 font-semibold text-lg">Disponible</span>
              <span class="text-green-600 text-sm">Libre depuis 8 min</span>
            </div>
          </div>
          
          <div class="mt-4 flex justify-end">
            <button class="px-3 py-1 bg-green-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Réserver
            </button>
          </div>
        </div>
        
        <!-- Table 6 - Disponible -->
        <div class="group relative rounded-xl border border-green-400 bg-gradient-to-br from-green-50 to-green-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
          <div class="absolute top-3 right-3">
            <span class="flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
          </div>
          
          <div class="flex items-center space-x-4">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-green-500 text-white font-bold text-xl shadow-inner">
              6
            </div>
            <div class="flex flex-col">
              <span class="text-green-700 font-semibold text-lg">Disponible</span>
              <span class="text-green-600 text-sm">Libre depuis 45 min</span>
            </div>
          </div>
          
          <div class="mt-4 flex justify-end">
            <button class="px-3 py-1 bg-green-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Réserver
            </button>
          </div>
        </div>
        
        <!-- Table 7 - Occupée -->
        <div class="group relative rounded-xl border border-red-400 bg-gradient-to-br from-red-50 to-red-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
          <div class="absolute top-3 right-3">
            <span class="flex h-3 w-3">
              <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
            </span>
          </div>
          
          <div class="flex items-center space-x-4">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-500 text-white font-bold text-xl shadow-inner">
              7
            </div>
            <div class="flex flex-col">
              <span class="text-red-700 font-semibold text-lg">Occupée</span>
              <span class="text-red-600 text-sm">Depuis 7 min</span>
            </div>
          </div>
          
          <div class="mt-4 bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center">
                <svg class="w-4 h-4 text-gray-600 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span class="text-gray-800 font-medium text-sm">Thomas Robert</span>
              </div>
              <span class="text-gray-500 text-xs">VS</span>
              <div class="flex items-center">
                <span class="text-gray-800 font-medium text-sm">Emma Dubois</span>
                <svg class="w-4 h-4 text-gray-600 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
              </div>
            </div>
            <div class="flex justify-center items-center mt-2">
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-red-500 h-2 rounded-full" style="width: 15%"></div>
              </div>
            </div>
          </div>
          
          <div class="mt-4 flex justify-end">
            <button class="px-3 py-1 bg-blue-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Détails
            </button>
          </div>
        </div>
        
        <!-- Table 8 - Disponible -->
        <div class="group relative rounded-xl border border-green-400 bg-gradient-to-br from-green-50 to-green-100 p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
          <div class="absolute top-3 right-3">
            <span class="flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
          </div>
          
          <div class="flex items-center space-x-4">
            <div class="flex items-center justify-center w-12 h-12 rounded-full bg-green-500 text-white font-bold text-xl shadow-inner">
              8
            </div>
            <div class="flex flex-col">
              <span class="text-green-700 font-semibold text-lg">Disponible</span>
              <span class="text-green-600 text-sm">Libre depuis 22 min</span>
            </div>
          </div>
          
          <div class="mt-4 flex justify-end">
            <button class="px-3 py-1 bg-green-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Réserver
            </button>
          </div>
        </div>
        
      </div>
    </div>
    
    <style>
      @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }
      
      .grid > div {
        animation: fadeIn 0.6s ease-out forwards;
      }
      
      .grid > div:nth-child(1) { animation-delay: 0.05s; }
      .grid > div:nth-child(2) { animation-delay: 0.1s; }
      .grid > div:nth-child(3) { animation-delay: 0.15s; }
      .grid > div:nth-child(4) { animation-delay: 0.2s; }
      .grid > div:nth-child(5) { animation-delay: 0.25s; }
      .grid > div:nth-child(6) { animation-delay: 0.3s; }
      .grid > div:nth-child(7) { animation-delay: 0.35s; }
      .grid > div:nth-child(8) { animation-delay: 0.4s; }
    </style>
    </x-app-layout>