@props(['event'])

<div x-show="selectedCategory === 'all' || selectedCategory === '{{ $event['category'] }}'" 
     x-transition
     class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-club-blue transition-colors">
    <div class="p-6">
        <div class="flex items-center justify-between mb-4">
            <span class="@if($event['category'] === 'tournament') bg-club-blue text-white @elseif($event['category'] === 'training') bg-gray-800 text-white @else bg-club-yellow text-club-blue @endif text-xs font-medium px-3 py-1 rounded-full uppercase">
                @if($event['category'] === 'tournament')
                    Tournoi
                @elseif($event['category'] === 'training')
                    Entraînement
                @else
                    Social
                @endif
            </span>
            <span class="text-2xl">{{ $event['icon'] }}</span>
        </div>
        <h3 class="text-xl font-bold mb-2 text-gray-900">{{ $event['title'] }}</h3>
        <p class="text-gray-600 mb-4">{{ $event['description'] }}</p>
        
        <div class="space-y-2 mb-6">
            <div class="flex items-center text-sm text-gray-600">
                <span class="mr-3 w-4">📅</span>
                <span>{{ $event['date'] }}</span>
            </div>
            <div class="flex items-center text-sm text-gray-600">
                <span class="mr-3 w-4">⏰</span>
                <span>{{ $event['time'] }}</span>
            </div>
            <div class="flex items-center text-sm text-gray-600">
                <span class="mr-3 w-4">📍</span>
                <span>{{ $event['location'] }}</span>
            </div>
            <div class="flex items-center text-sm text-gray-600">
                <span class="mr-3 w-4">{{ $event['category'] === 'tournament' ? '💰' : ($event['category'] === 'training' ? '👥' : '🍕') }}</span>
                <span>{{ $event['price'] }}</span>
            </div>
        </div>
        
        <button class="w-full @if($event['category'] === 'tournament') bg-club-blue hover:bg-club-blue-light @elseif($event['category'] === 'training') bg-gray-800 hover:bg-gray-700 @else bg-club-yellow hover:bg-club-yellow-light @endif @if($event['category'] === 'social') text-club-blue @else text-white @endif py-3 px-4 rounded-lg transition-colors font-medium">
            @if($event['category'] === 'tournament')
                S'inscrire Maintenant
            @elseif($event['category'] === 'training')
                Rejoindre la Session
            @else
                Confirmer Présence
            @endif
        </button>
    </div>
</div>
