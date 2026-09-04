@props(['agenda'])

{{--
    L'agenda daté de la page d'accueil.

    Il remplace les trois vues responsive qui rendaient toutes le même horaire
    type (`schedule-mini-overview`, `schedule-week-overview`,
    `schedule-calendar-view`) : elles affichaient « lundi 19h » toute l'année,
    sans jamais pouvoir dire qu'une séance saute, et laissaient tomber les packs
    multi-jours. Ici les jours sans rien ne sont pas rendus du tout — on passe
    du samedi au lundi.

    Le pli est mobile seulement : sous `lg`, seule la première semaine est
    ouverte ; à partir de `lg` tout est déplié et le bouton disparaît. Les deux
    semaines sont rendues côté serveur dans les deux cas, le pli ne fait que
    masquer — le contenu reste lisible par un moteur de recherche.
--}}

@php
    $foldFrom = now()->addDays(7)->startOfDay();
    $openDays = collect($agenda->days)->filter(fn ($day) => $day->date->lt($foldFrom))->values();
    $foldedDays = collect($agenda->days)->filter(fn ($day) => $day->date->gte($foldFrom))->values();

    $barFor = fn (\App\Data\PublicAgenda\AgendaEntry $entry): string => match (true) {
        $entry->isCancelled() => 'bg-gray-300',
        str_contains(mb_strtolower($entry->title), 'interclub') => 'bg-red-400',
        str_contains(mb_strtolower($entry->title), 'libre') => 'bg-gray-300',
        str_contains(mb_strtolower($entry->title), 'assembl') => 'bg-purple-400',
        str_contains(mb_strtolower($entry->title), 'supervis') => 'bg-amber-400',
        default => 'bg-club-blue',
    };
@endphp

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden" x-data="{ showRest: false }">

    <div class="bg-gradient-to-r from-club-blue to-club-blue-light text-white px-6 py-5">
        <h3 class="text-lg font-bold">{{ __('The next two weeks') }}</h3>
        <p class="text-sm opacity-80 mt-0.5">
            @if ($agenda->isExtended)
                {{ __('Nothing is scheduled in the coming fortnight — here is the next activity.') }}
            @elseif ($agenda->isEmpty())
                {{ __('No activity scheduled at the moment.') }}
            @else
                {{ __('Cancellations are shown here too — check before you travel.') }}
            @endif
        </p>
    </div>

    {{-- Les exceptions d'abord : sur mobile la deuxième semaine est repliée, et
         une annulation qui s'y trouve ne doit jamais dépendre d'un clic. --}}
    @foreach ($agenda->exceptions as $exception)
        <div @class([
            'flex items-start gap-3 px-5 py-3 border-b border-gray-200',
            'bg-amber-50' => $exception->roomStaysOpen(),
            'bg-red-50' => ! $exception->roomStaysOpen(),
        ])>
            <span @class([
                'mt-2 h-1.5 w-1.5 shrink-0 rounded-full',
                'bg-amber-500' => $exception->roomStaysOpen(),
                'bg-red-600' => ! $exception->roomStaysOpen(),
            ])></span>
            <p class="text-sm leading-relaxed">
                <strong @class([
                    'font-bold',
                    'text-amber-700' => $exception->roomStaysOpen(),
                    'text-red-700' => ! $exception->roomStaysOpen(),
                ])>
                    {{ $exception->startsAt->translatedFormat('l j F') }} —
                    {{ $exception->roomStaysOpen() ? __('Room open for free play') : __('Room closed') }}.
                </strong>
                @if ($exception->cancellationNote)
                    <span class="text-gray-600">{{ $exception->cancellationNote }}</span>
                @endif
            </p>
        </div>
    @endforeach

    @forelse ($openDays as $day)
        <x-public.agenda-day :day="$day" :bar-for="$barFor" />
    @empty
        @if ($agenda->isEmpty())
            <div class="px-6 py-8 text-center text-gray-500 text-sm">
                {{ __('The season calendar is not published yet.') }}
            </div>
        @endif
    @endforelse

    @if ($foldedDays->isNotEmpty())
        {{-- La classe est écrite en dur *et* pilotée par Alpine : sans elle, la
             deuxième semaine s'afficherait en clair sur mobile le temps
             qu'Alpine démarre. La syntaxe objet retire bien une classe déjà
             présente dans l'attribut, là où une chaîne ne ferait qu'ajouter. --}}
        <div data-agenda-folded class="max-lg:hidden" :class="{ 'max-lg:hidden': ! showRest }">
            @foreach ($foldedDays as $day)
                <x-public.agenda-day :day="$day" :bar-for="$barFor" />
            @endforeach
        </div>

        <button type="button"
            class="lg:hidden block w-full border-t border-gray-200 px-4 py-3 text-sm font-semibold text-club-blue hover:bg-gray-50 transition-colors cursor-pointer"
            :aria-expanded="showRest ? 'true' : 'false'"
            x-on:click="showRest = !showRest"
            x-text="showRest ? '{{ __('Hide the following days') }}' : '{{ __('See the following days') }}'">
            {{ __('See the following days') }}
        </button>
    @endif

    @if (! empty($agenda->rhythm) || $agenda->interclubRhythm !== null)
        <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 bg-gray-50 px-6 py-4 text-sm text-gray-600 border-t border-gray-200">
            <span class="font-semibold text-gray-900">{{ __('Our usual rhythm:') }}</span>
            @foreach ($agenda->rhythm as $rhythmDay)
                <span>
                    {{ \Carbon\Carbon::now()->startOfWeek()->addDays($rhythmDay->dayOfWeek - 1)->translatedFormat('l') }}
                    {{ str_replace(':', 'h', $rhythmDay->startsAt) }}–{{ str_replace(':', 'h', $rhythmDay->endsAt) }}
                </span>
            @endforeach

            @if ($agenda->interclubRhythm !== null)
                <span>
                    {{ __('interclubs at home on :day', ['day' => mb_strtolower($agenda->interclubRhythm->day)]) }}
                    {{ str_replace(':', 'h', $agenda->interclubRhythm->startsAt) }}
                </span>
            @endif
        </div>
    @endif
</div>
