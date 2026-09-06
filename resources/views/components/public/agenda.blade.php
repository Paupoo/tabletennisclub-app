@props(['agenda'])

{{--
    La grille des activités du club : cinq semaines, une case par jour.

    Elle a d'abord été une liste datée de quatorze jours. Sur un rythme
    hebdomadaire, quatorze jours impriment deux fois la même semaine : la moitié
    du bloc était de la duplication littérale, pour 27 % de la hauteur de la
    page d'accueil. Cinq semaines en grille disent la même chose — et disent en
    plus que le club tourne toutes les semaines, ce qu'une liste ne peut pas.

    Trois familles de couleur seulement. Une couleur par type d'activité
    imposait une légende de six lignes et une case illisible sans elle ; la
    nuance (dirigé, supervisé, libre) est écrite dans la case, à côté de l'heure.
--}}

@php
    use App\Domains\Shared\Enums\AgendaFamily;

    $weeks = collect($agenda->days)->chunk(7);
    $weekdays = collect(range(0, 6))->map(
        fn (int $offset): string => \Carbon\Carbon::now()->startOfWeek()->addDays($offset)->translatedFormat('l')
    );

    /*
     * Les classes sont écrites en toutes lettres : Tailwind scanne les sources
     * à la recherche de chaînes littérales, une classe assemblée à l'exécution
     * ne serait jamais générée.
     */
    $pillClasses = function (\App\Data\PublicAgenda\AgendaEntry $entry): string {
        if ($entry->isCancelled()) {
            return $entry->roomStaysOpen()
                ? 'bg-amber-50 text-amber-800 ring-1 ring-amber-300'
                : 'bg-red-50 text-red-800 ring-1 ring-red-300';
        }

        return match ($entry->family) {
            AgendaFamily::TRAINING => 'bg-blue-50 text-blue-900',
            AgendaFamily::COMPETITION => 'bg-red-50/70 text-red-900',
            AgendaFamily::CLUB_LIFE => 'bg-gray-100 text-gray-700',
        };
    };

    $dotClasses = function (\App\Data\PublicAgenda\AgendaEntry $entry): string {
        if ($entry->isCancelled()) {
            return $entry->roomStaysOpen() ? 'bg-amber-500' : 'bg-red-600';
        }

        return match ($entry->family) {
            AgendaFamily::TRAINING => 'bg-club-blue',
            AgendaFamily::COMPETITION => 'bg-red-500',
            AgendaFamily::CLUB_LIFE => 'bg-gray-400',
        };
    };

    $upcoming = collect($agenda->days)
        ->reject(fn ($day): bool => $day->isPast || $day->entries === [])
        ->take(8);
@endphp

<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

    @if ($agenda->isEmpty())
        <p class="px-6 py-10 text-center text-sm text-gray-500">
            {{ __('The season calendar is not published yet.') }}
        </p>
    @else
        {{-- ── Grille : à partir de md ──────────────────────────────────── --}}
        <div class="hidden md:block">
            <div class="grid grid-cols-7 bg-gray-50">
                @foreach ($weekdays as $weekday)
                    <div class="border-b border-gray-200 py-2 text-center text-xs font-bold uppercase tracking-wider text-gray-500">
                        {{ $weekday }}
                    </div>
                @endforeach
            </div>

            @foreach ($weeks as $week)
                <div class="grid grid-cols-7">
                    @foreach ($week as $day)
                        <div @class([
                            'min-h-24 border-b border-r border-gray-100 px-1.5 pb-2 pt-1.5 last:border-r-0',
                            'bg-club-yellow/10' => $day->isToday,
                            'bg-gray-50/70' => $day->isPast,
                        ])>
                            <div @class([
                                'mb-1 text-xs font-bold tabular-nums',
                                'text-club-blue' => $day->isToday,
                                'text-gray-300' => $day->isPast,
                                'text-gray-900' => ! $day->isToday && ! $day->isPast,
                            ])>{{ $day->date->format('d') }}</div>

                            @foreach ($day->entries as $entry)
                                <div class="mb-1 rounded px-1.5 py-1 {{ $pillClasses($entry) }} {{ $day->isPast ? 'opacity-50' : '' }}">
                                    <div class="flex items-center gap-1.5">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dotClasses($entry) }}"></span>
                                        <span @class(['text-xs font-bold tabular-nums', 'line-through' => $entry->isCancelled()])>
                                            {{ $entry->startsAt->format('G\hi') }}
                                        </span>
                                    </div>
                                    <div class="truncate pl-3 text-xs">{{ $entry->title }}</div>
                                    @if ($entry->spansMultipleDays())
                                        <div class="pl-3 text-xs opacity-75">
                                            {{ __('until :date', ['date' => $entry->spanEndsOn->translatedFormat('j F')]) }}
                                        </div>
                                    @endif
                                    @if ($entry->isCancelled())
                                        <div class="pl-3 text-xs font-semibold">
                                            {{ $entry->roomStaysOpen() ? __('Room open for free play') : __('Room closed') }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- ── Liste : sous md, la grille à sept colonnes n'a plus la place ── --}}
        <div class="md:hidden">
            @foreach ($upcoming as $day)
                <div class="border-b border-gray-100 px-4 py-3 last:border-b-0">
                    <div class="mb-1.5 text-xs font-bold uppercase tracking-wider text-gray-500">
                        {{ $day->date->translatedFormat('l j F') }}
                    </div>
                    @foreach ($day->entries as $entry)
                        <div class="flex items-baseline gap-2.5 py-1">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 self-start rounded-full {{ $dotClasses($entry) }}"></span>
                            <span @class(['w-14 shrink-0 text-sm font-semibold tabular-nums', 'text-gray-400 line-through' => $entry->isCancelled()])>
                                {{ $entry->startsAt->format('G\hi') }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span @class(['text-sm', 'text-gray-400 line-through' => $entry->isCancelled(), 'text-gray-900' => ! $entry->isCancelled()])>
                                    {{ $entry->title }}
                                </span>
                                @if ($entry->spansMultipleDays())
                                    <span class="block text-xs text-gray-500">
                                        {{ __('until :date', ['date' => $entry->spanEndsOn->translatedFormat('j F')]) }}
                                    </span>
                                @endif
                                @if ($entry->isCancelled())
                                    <span @class(['block text-xs font-semibold', 'text-amber-700' => $entry->roomStaysOpen(), 'text-red-700' => ! $entry->roomStaysOpen()])>
                                        {{ $entry->roomStaysOpen() ? __('Room open for free play') : __('Room closed') }}@if ($entry->cancellationNote) · {{ $entry->cancellationNote }}@endif
                                    </span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap gap-x-5 gap-y-2 border-t border-gray-200 bg-gray-50 px-5 py-3 text-xs text-gray-600">
            <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-club-blue"></span>{{ __('Training') }}</span>
            <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-red-500"></span>{{ __('Competition') }}</span>
            <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-gray-400"></span>{{ __('Club life') }}</span>
        </div>
    @endif
</div>
