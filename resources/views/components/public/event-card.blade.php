@props(['event'])

<div class="bg-base-100 rounded-lg border border-base-300 overflow-hidden hover:border-primary transition-colors flex flex-col">
    <div class="p-6 flex flex-col flex-1">
        <div class="flex items-center justify-between mb-4">
            @php
                $typeTone = match($event['type']) {
                    'TOURNAMENT' => 'primary',
                    'TRAINING'   => 'dark',
                    'INTERCLUB'  => 'success',
                    default      => 'secondary',
                };
            @endphp
            <x-badge :tone="$typeTone" solid class="uppercase">{{ $event['type_label'] }}</x-badge>
            <span class="text-2xl">{{ $event['icon'] }}</span>
        </div>

        <h3 class="text-xl font-bold mb-2 text-base-content">{{ $event['title'] }}</h3>
        <p class="text-muted mb-4 flex-1">{{ $event['description'] }}</p>

        <div class="space-y-2 mb-6">
            <div class="flex items-center text-sm text-muted">
                <span class="mr-3 w-4">📅</span>
                <span class="{{ ($event['is_past'] ?? false) ? 'line-through text-subtle' : '' }}">{{ $event['date'] }}</span>
            </div>
            @if (!empty($event['time']) && $event['time'] !== '00:00')
                <div class="flex items-center text-sm text-muted">
                    <span class="mr-3 w-4">⏰</span>
                    <span>{{ $event['time'] }}</span>
                </div>
            @endif
            @if (!empty($event['location']))
                <div class="flex items-center text-sm text-muted">
                    <span class="mr-3 w-4">📍</span>
                    <span>{{ $event['location'] }}</span>
                </div>
            @endif
            @if (!empty($event['price']))
                <div class="flex items-center text-sm text-muted">
                    <span class="mr-3 w-4">🎟️</span>
                    <span>{{ $event['price'] }}</span>
                </div>
            @endif
        </div>

        {{-- Action buttons --}}
        @if(!($event['is_past'] ?? false))
            @auth
                @if($event['is_registered'])
                    <a href="{{ route('admin.user.event-subscription', auth()->user()) }}"
                       class="inline-flex items-center justify-center gap-2 rounded-lg border-2 border-green-600 px-4 py-2 text-sm font-semibold text-green-700 transition-colors hover:bg-green-50">
                        <span>✓</span> {{ __('Registered') }}
                    </a>
                @else
                    <a href="{{ route('admin.user.event-subscription', auth()->user()) }}"
                       class="inline-flex items-center justify-center rounded-lg bg-club-blue px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-club-blue-light">
                        {{ __('Register') }}
                    </a>
                @endif
            @else
                <a href="{{ route('home') }}#contact"
                   class="inline-flex items-center justify-center rounded-lg border-2 border-primary px-4 py-2 text-sm font-semibold text-primary transition-colors hover:bg-club-blue hover:text-white">
                    {{ __('Contact us') }}
                </a>
            @endauth
        @endif
    </div>
</div>
