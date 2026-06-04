@props(['schedules' => []])

@php
    $daysOfWeek = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $activitiesByDay = collect($schedules)->groupBy('day');
@endphp

<div class="bg-white rounded-2xl shadow-sm border overflow-hidden" x-data="{ showDetails: false }">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-club-blue to-club-blue-light text-white px-6 py-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold">Planning hebdomadaire</h3>
                <p class="text-sm opacity-75 mt-0.5">
                    @php $count = collect($schedules)->count(); $days = $activitiesByDay->count(); @endphp
                    {{ $count }} activité{{ $count > 1 ? 's' : '' }} sur {{ $days }} jour{{ $days > 1 ? 's' : '' }}
                </p>
            </div>

            {{-- Segmented toggle --}}
            <div class="inline-flex bg-white/15 rounded-lg p-1 gap-0.5 shrink-0">
                <button @click="showDetails = false"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-150 cursor-pointer"
                        :class="showDetails ? 'text-white/60 hover:text-white' : 'bg-white/25 text-white shadow-sm'">
                    Aperçu
                </button>
                <button @click="showDetails = true"
                        class="px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-150 cursor-pointer"
                        :class="!showDetails ? 'text-white/60 hover:text-white' : 'bg-white/25 text-white shadow-sm'">
                    Détails
                </button>
            </div>
        </div>
    </div>

    {{-- Aperçu : grille 7 jours avec dots --}}
    <div x-show="!showDetails" x-transition>
        <div class="grid grid-cols-7 divide-x divide-gray-100 border-b border-gray-100">
            @foreach($daysOfWeek as $day)
                @php
                    $dayActivities = $activitiesByDay->get($day, collect());
                    $isToday = $day === now()->locale('fr')->dayName;
                @endphp
                <div class="min-h-28 p-3 {{ $isToday ? 'bg-club-yellow/5' : 'hover:bg-gray-50' }} transition-colors">
                    <div class="text-center mb-3">
                        <span class="text-xs font-semibold {{ $isToday ? 'text-club-blue' : 'text-gray-500' }}">
                            {{ mb_strtoupper(mb_substr($day, 0, 3)) }}
                        </span>
                        @if($isToday)
                            <div class="w-1 h-1 bg-club-yellow rounded-full mx-auto mt-1"></div>
                        @endif
                    </div>

                    <div class="space-y-1.5">
                        @forelse($dayActivities as $activity)
                            @php
                                [$dotColor, $pillBg, $pillText] = match ($activity['type'] ?? null) {
                                    'Directed'   => ['bg-blue-500',  'bg-blue-50',  'text-blue-800'],
                                    'Free'       => ['bg-gray-300',  'bg-gray-50',  'text-gray-500'],
                                    'Supervised' => ['bg-amber-400', 'bg-amber-50', 'text-amber-800'],
                                    'match'      => ['bg-red-400',   'bg-red-50',   'text-red-700'],
                                    default      => ['bg-gray-300',  'bg-gray-50',  'text-gray-500'],
                                };
                            @endphp
                            <div class="rounded px-1.5 py-1 {{ $pillBg }} {{ $pillText }}">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <div class="w-1.5 h-1.5 rounded-full {{ $dotColor }} shrink-0"></div>
                                    <span class="text-xs font-semibold">
                                        {{ explode(' – ', $activity['time'])[0] }}
                                    </span>
                                </div>
                                <div class="text-xs opacity-70 leading-tight truncate pl-2.5">
                                    {{ $activity['activity'] }}
                                </div>
                            </div>
                        @empty
                            <div class="text-gray-300 text-xs text-center py-3">—</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        <div class="px-6 py-3 flex items-center gap-4 text-xs text-gray-400 flex-wrap">
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-blue-500"></div>{{ __('Directed') }}</div>
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-amber-400"></div>{{ __('Supervised') }}</div>
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-gray-300"></div> Libre</div>
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-red-400"></div> Interclubs</div>
        </div>
    </div>

    {{-- Détails : grille 7 jours avec cartes complètes --}}
    <div x-show="showDetails" x-transition>
        <div class="grid grid-cols-7 divide-x divide-gray-100 border-b border-gray-100">
            @foreach($daysOfWeek as $day)
                @php
                    $dayActivities = $activitiesByDay->get($day, collect());
                    $isToday = $day === now()->locale('fr')->dayName;
                @endphp
                <div class="min-h-32 p-3 {{ $isToday ? 'bg-club-yellow/5' : '' }}">
                    <div class="text-center mb-3">
                        <span class="text-xs font-semibold {{ $isToday ? 'text-club-blue' : 'text-gray-600' }}">
                            {{ $day }}
                        </span>
                        @if($isToday)
                            <div class="w-1 h-1 bg-club-yellow rounded-full mx-auto mt-1"></div>
                        @endif
                    </div>
                    <div class="space-y-1.5">
                        @forelse($dayActivities as $activity)
                            @php
                                [$cellBorder, $cellBg, $cellText] = match ($activity['type'] ?? null) {
                                    'Directed'   => ['border-blue-400',  'bg-blue-50',   'text-blue-800'],
                                    'Free'       => ['border-gray-300',  'bg-gray-50',   'text-gray-600'],
                                    'Supervised' => ['border-amber-400', 'bg-amber-50',  'text-amber-800'],
                                    'match'      => ['border-red-400',   'bg-red-50',    'text-red-700'],
                                    default      => ['border-gray-200',  'bg-gray-50',   'text-gray-600'],
                                };
                                $levelClass = match ($activity['level'] ?? null) {
                                    'Tous niveaux'   => 'bg-blue-100 text-blue-700',
                                    'Débutant'       => 'bg-green-100 text-green-700',
                                    'Jeunes'         => 'bg-green-100 text-green-700',
                                    'Confirmé'       => 'bg-orange-100 text-orange-700',
                                    'Compétition'    => 'bg-red-100 text-red-700',
                                    'Jeunes espoirs' => 'bg-purple-100 text-purple-700',
                                    default          => null,
                                };
                            @endphp
                            <div class="{{ $cellBg }} {{ $cellText }} text-xs p-2 rounded border-l-2 {{ $cellBorder }}">
                                <div class="font-semibold leading-tight">{{ $activity['activity'] }}</div>
                                <div class="opacity-70 mt-0.5">{{ $activity['time'] }}</div>
                                @if(!empty($activity['coach']))
                                    <div class="opacity-60 mt-0.5 truncate">{{ $activity['coach'] }}</div>
                                @endif
                                @if($levelClass)
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs mt-1 {{ $levelClass }}">
                                        {{ $activity['level'] }}
                                    </span>
                                @endif
                                @if($activity['is_open_enrollment'] ?? false)
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs mt-1 bg-gray-100 text-gray-500">
                                        Entrée libre
                                    </span>
                                @endif
                            </div>
                        @empty
                            <div class="text-gray-300 text-xs text-center py-3">—</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
