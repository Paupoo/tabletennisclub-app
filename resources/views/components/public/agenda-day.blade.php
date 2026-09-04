@props(['day', 'barFor'])

{{--
    Une journée de l'agenda. Le rail de date passe au-dessus des activités sous
    `md`, et à leur gauche au-delà.

    Une activité annulée reste listée, barrée : une case vide se lit comme un
    défaut d'affichage, alors qu'une ligne barrée portant son motif dissuade
    activement le déplacement — c'est la raison d'être du bloc.
--}}

<div data-agenda-day class="border-b border-gray-200 last:border-b-0 px-5 py-4 md:grid md:grid-cols-[132px_1fr] md:gap-6">

    <div class="mb-2 flex items-baseline gap-2 md:mb-0 md:block">
        <span class="text-xs font-bold uppercase tracking-wider text-gray-500">
            {{ $day->date->translatedFormat('l') }}
        </span>
        <span class="text-xl font-bold tabular-nums leading-none text-gray-900 md:block md:text-2xl">
            {{ $day->date->format('d') }}
        </span>
        <span class="text-xs text-gray-500">{{ $day->date->translatedFormat('F') }}</span>
    </div>

    <div>
        @foreach ($day->entries as $entry)
            <div class="flex items-baseline gap-3 border-t border-gray-100 py-2.5 first:border-t-0 first:pt-0">
                <span class="w-1 shrink-0 self-stretch rounded-sm {{ $barFor($entry) }}"></span>

                <span @class([
                    'w-24 shrink-0 whitespace-nowrap text-sm font-semibold tabular-nums',
                    'text-gray-400 line-through' => $entry->isCancelled(),
                    'text-gray-900' => ! $entry->isCancelled(),
                ])>
                    {{ $entry->startsAt->format('G\hi') }}@if ($entry->endsAt && ! $entry->spansMultipleDays()) – {{ $entry->endsAt->format('G\hi') }}@endif
                </span>

                <span class="min-w-0 flex-1">
                    <span @class([
                        'text-sm font-semibold',
                        'text-gray-400 line-through' => $entry->isCancelled(),
                        'text-gray-900' => ! $entry->isCancelled(),
                    ])>{{ $entry->title }}</span>

                    @if ($entry->isCancelled())
                        <span @class([
                            'ml-1.5 inline-block rounded px-1.5 py-0.5 align-[1px] text-xs font-bold uppercase tracking-wide',
                            'bg-amber-50 text-amber-700 border border-amber-300' => $entry->roomStaysOpen(),
                            'bg-red-50 text-red-700 border border-red-300' => ! $entry->roomStaysOpen(),
                        ])>
                            {{ $entry->roomStaysOpen() ? __('Room open for free play') : __('Room closed') }}
                        </span>
                    @endif

                    @if ($entry->spansMultipleDays())
                        <span class="mt-0.5 block text-xs text-gray-500">
                            {{ __('Until :date', ['date' => $entry->spanEndsOn->translatedFormat('l j F')]) }}
                            @if ($entry->endsAt)
                                · {{ $entry->startsAt->format('G\hi') }}–{{ $entry->endsAt->format('G\hi') }}
                            @endif
                            @if ($entry->location)
                                · {{ $entry->location }}
                            @endif
                        </span>

                        @foreach ($entry->spanExceptions as $exception)
                            <span class="mt-0.5 block text-xs font-medium text-red-700">
                                {{ __('Except :date', ['date' => $exception->startsAt->translatedFormat('l j F')]) }}@if ($exception->cancellationNote) — {{ $exception->cancellationNote }}@endif
                            </span>
                        @endforeach
                    @elseif ($entry->isCancelled() && $entry->cancellationNote)
                        <span @class([
                            'mt-0.5 block text-xs font-medium',
                            'text-amber-700' => $entry->roomStaysOpen(),
                            'text-red-700' => ! $entry->roomStaysOpen(),
                        ])>{{ $entry->cancellationNote }}</span>
                    @elseif ($entry->location)
                        <span class="mt-0.5 block text-xs text-gray-500">{{ $entry->location }}</span>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
</div>
