@props([
    'fixture',
    'viewerId' => null,
])

{{--
    Une tuile de « Sélections de la semaine », toujours dans le même ordre :
    l'heure et le lieu, l'équipe, l'adversaire, puis les joueurs. La tuile entière
    mène à la page de la rencontre, ouverte à tout membre. Celle où joue le
    lecteur porte la bordure du club.
--}}
@php
    /** @var \App\Data\Interclub\WeeklyFixture $fixture */
    $isMine = $viewerId !== null && $fixture->involves($viewerId);
@endphp
<a href="{{ route('admin.interclubs.my-match', $fixture->id) }}" wire:navigate
    {{ $attributes->class([
        'flex flex-col overflow-hidden rounded-xl bg-base-100 transition-colors hover:border-primary',
        'border-2 border-primary' => $isMine,
        'border border-base-300' => ! $isMine,
    ]) }}
    data-weekly-tile="{{ $fixture->id }}" data-mine="{{ $isMine ? 'true' : 'false' }}">
    <div @class(['flex items-center justify-between gap-2 border-b border-base-300 px-4 py-2.5 text-sm font-semibold', 'bg-primary/10' => $isMine, 'bg-base-200' => ! $isMine])>
        <span class="tabular-nums">{{ $fixture->startsAt->format('H\hi') }}</span>
        @if ($fixture->isHome)
            <span class="badge badge-secondary badge-sm font-bold">{{ __('At home') }}</span>
        @else
            <span class="badge badge-ghost badge-sm">{{ $fixture->town !== null ? __('in :town', ['town' => $fixture->town]) : __('Away match') }}</span>
        @endif
    </div>

    <div class="px-4 py-3">
        <div class="text-primary text-lg font-bold leading-tight">{{ $fixture->teamName }}</div>
        <div class="text-sm">{{ __('against') }} <span class="font-semibold">{{ $fixture->opponentName }}</span></div>
    </div>

    <div class="flex-1 border-t border-base-300 px-4 py-3 text-sm">
        @if ($fixture->forfeit !== null)
            <span class="text-error font-semibold">{{ $fixture->forfeit->label() }}</span>
        @elseif (! $fixture->isPublished)
            <span class="text-base-content/60 italic">{{ __('Lineup not sent yet') }}</span>
        @else
            <ul class="space-y-1">
                @foreach ($fixture->players as $player)
                    <li @class(['flex items-baseline gap-2', 'bg-primary/5 -mx-1 rounded px-1' => $player['id'] === $viewerId]) data-weekly-player="{{ $player['id'] }}">
                        <span @class(['w-7 shrink-0 font-mono tabular-nums', 'text-base-content/40' => $player['force_list'] === null, 'text-base-content/70' => $player['force_list'] !== null])>{{ $player['force_list'] === null ? '—' : '#' . $player['force_list'] }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="font-bold">{{ $player['last_name'] }}</span>
                            <span @class(['font-bold' => $player['id'] === $viewerId])>{{ $player['first_name'] }}</span>
                            @if ($player['is_walkover'])
                                <span class="badge badge-warning badge-xs font-bold">WO</span>
                            @endif
                        </span>
                        <span class="text-base-content/60 shrink-0 font-mono text-xs">{{ $player['ranking'] }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($fixture->shortHandedWith !== null)
                <span class="badge badge-error badge-soft badge-sm mt-2">{{ __('Plays with :n', ['n' => $fixture->shortHandedWith]) }}</span>
            @endif
        @endif
    </div>
</a>
