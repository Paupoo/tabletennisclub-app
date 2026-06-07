@props(['event'])

<div class="bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-club-blue transition-colors flex flex-col">
    <div class="p-6 flex flex-col flex-1">
        <div class="flex items-center justify-between mb-4">
            @php
                $badgeClass = match($event['type']) {
                    'TOURNAMENT' => 'bg-club-blue text-white',
                    'TRAINING'   => 'bg-gray-800 text-white',
                    'INTERCLUB'  => 'bg-green-700 text-white',
                    default      => 'bg-club-yellow text-club-blue',
                };
            @endphp
            <span class="{{ $badgeClass }} text-xs font-medium px-3 py-1 rounded-full uppercase">
                {{ $event['type_label'] }}
            </span>
            <span class="text-2xl">{{ $event['icon'] }}</span>
        </div>

        <h3 class="text-xl font-bold mb-2 text-gray-900">{{ $event['title'] }}</h3>
        <p class="text-gray-600 mb-4 flex-1">{{ $event['description'] }}</p>

        <div class="space-y-2 mb-6">
            <div class="flex items-center text-sm text-gray-600">
                <span class="mr-3 w-4">📅</span>
                <span class="{{ ($event['is_past'] ?? false) ? 'line-through text-gray-400' : '' }}">{{ $event['date'] }}</span>
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

        {{-- Action buttons --}}
        @if(!($event['is_past'] ?? false))
            @auth
                @if($event['is_registered'])
                    <a href="{{ route('clubAdmin.registrations.index') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-lg border-2 border-green-600 px-4 py-2 text-sm font-semibold text-green-700 transition-colors hover:bg-green-50">
                        <span>✓</span> {{ __('Registered') }}
                    </a>
                @else
                    <a href="{{ route('clubAdmin.registrations.index') }}"
                       class="inline-flex items-center justify-center rounded-lg bg-club-blue px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-club-blue-light">
                        {{ __('Register') }}
                    </a>
                @endif
            @else
                <a href="{{ route('home') }}#contact"
                   class="inline-flex items-center justify-center rounded-lg border-2 border-club-blue px-4 py-2 text-sm font-semibold text-club-blue transition-colors hover:bg-club-blue hover:text-white">
                    {{ __('Contact us') }}
                </a>
            @endauth
        @endif
    </div>
</div>
