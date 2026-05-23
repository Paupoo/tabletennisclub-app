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
                @elseif($event['category'] === 'club-life')
                    Vie du club
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
            @if (!empty($event['time']) && $event['time'] !== '00:00')
                <div class="flex items-center text-sm text-gray-600">
                    <span class="mr-3 w-4">⏰</span>
                    <span>{{ $event['time'] }}</span>
                </div>
            @endif
            @if (!empty($event['location']))
                <div class="flex items-center text-sm text-gray-600">
                    <span class="mr-3 w-4">📍</span>
                    <span>{{ $event['location'] }}</span>
                </div>
            @endif
            @if (!empty($event['price']))
                <div class="flex items-center text-sm text-gray-600">
                    <span class="mr-3 w-4">🎟️</span>
                    <span>{{ $event['price'] }}</span>
                </div>
            @endif
        </div>
        
    </div>
</div>
