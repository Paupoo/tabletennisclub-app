{{--
    Les feuilles de match, une par poule, à découper.

    Le comité tend un rectangle de papier aux quatre joueurs d'une poule et
    n'entend plus parler d'eux : chaque carte porte donc tout ce qu'il faut pour
    jouer la poule seul -- la composition, l'ordre des rencontres, et de quoi
    noter.

    Une ligne par joueur et une colonne par set. C'est la disposition qui rend
    l'erreur difficile : on écrit ses points sur SA ligne, et il n'y a jamais
    deux nombres à départager dans la même case.
--}}
@extends('print.layout')

@section('title', __('Match sheets') . ' — ' . $tournament->name)

@push('styles')
<style>
    .sheet { padding-top: 6mm; }

    .lead { margin-bottom: 5mm; font-size: 11px; color: #52525b; }
    .lead strong { color: #000; }

    /* Le trait de découpe : chaque poule est un rectangle autonome. */
    .cut {
        break-inside: avoid;
        border: 1.5px dashed #71717a;
        border-radius: 3px;
        padding: 5mm;
        margin-bottom: 6mm;
        position: relative;
    }
    .cut__scissors { position: absolute; top: -8px; left: 8px; background: #fff; padding: 0 4px; font-size: 11px; color: #71717a; }

    .cut__head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; border-bottom: 1.5px solid #000; padding-bottom: 3px; }
    .cut__pool { font-size: 18px; font-weight: 800; margin: 0; }
    .cut__meta { font-size: 10px; color: #52525b; text-align: right; }

    .roster { margin: 4px 0 6px; font-size: 11.5px; }
    .roster span { font-weight: 600; }
    .roster span::before { content: attr(data-seed) ". "; color: #71717a; font-weight: 700; }
    .roster span + span::before { content: "  " attr(data-seed) ". "; }

    .match { width: 100%; border-collapse: collapse; margin-bottom: 3.5mm; }
    .match th { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #71717a; padding-bottom: 1px; }
    .match th.who { text-align: left; }
    .match td { border: 1px solid #52525b; height: 8mm; }
    .match td.who { border: 0; border-bottom: 1px solid #d4d4d8; padding-right: 6px; font-size: 12.5px; font-weight: 600; }
    .match td.set { width: 9mm; }
    /* La colonne qui tranche : c'est elle qu'on lit pour savoir qui a gagné. */
    .match td.won { width: 11mm; background: #f4f4f5; }

    .cut__foot { margin-top: 2mm; font-size: 9px; color: #71717a; font-style: italic; }
</style>
@endpush

@section('content')

    @php
        $setColumns = max(1, ($tournament->sets_to_win * 2) - 1);
    @endphp

    <header class="masthead">
        <img class="masthead__logo" src="{{ asset('images/logo-club.svg') }}" alt="">
        <div class="masthead__text">
            <p class="club">CTT Ottignies-Blocry</p>
            <h1>{{ $tournament->name }}</h1>
            <p class="when">
                {{ $tournament->startsAt()?->translatedFormat('l j F Y') }}@if ($tournament->hasKnownStartTime()) · {{ $tournament->startsAt()->format('H\hi') }}@endif
            </p>
        </div>
    </header>

    @if ($pools->isEmpty())
        <p class="empty">{{ __('No pools generated yet.') }}</p>
    @else
        <p class="lead">
            {{ __('Cut along the dashed lines and hand one sheet to each pool.') }}
            <strong>{{ trans_choice('{1} :count winning set|[2,*] :count winning sets', $tournament->sets_to_win, ['count' => $tournament->sets_to_win]) }}</strong>
        </p>

        @foreach ($pools as $pool)
            @php
                $isDoubles = $pool->pairs->isNotEmpty();
                $entries = $isDoubles
                    ? $pool->pairs->map(fn ($pair) => $pair->displayName())
                    : $pool->users->map(fn ($user) => $user->full_name);
            @endphp

            <section class="cut">
                <span class="cut__scissors">&#9986;</span>

                <div class="cut__head">
                    <h2 class="cut__pool">{{ $pool->name }}</h2>
                    <p class="cut__meta">
                        {{ $tournament->name }}<br>
                        {{ $tournament->startsAt()?->format('d/m/Y') }}
                    </p>
                </div>

                <p class="roster">
                    @foreach ($entries as $i => $name)
                        <span data-seed="{{ $i + 1 }}">{{ $name }}</span>
                    @endforeach
                </p>

                @forelse ($pool->tournamentmatches as $match)
                    @php
                        $pairMatch = $match->pair1_id !== null;
                        $side1 = $pairMatch ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—');
                        $side2 = $pairMatch ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—');
                    @endphp

                    <table class="match">
                        <thead>
                            <tr>
                                <th class="who">{{ __('Match :number', ['number' => $loop->iteration]) }}</th>
                                @for ($set = 1; $set <= $setColumns; $set++)
                                    <th>{{ $set }}</th>
                                @endfor
                                <th>{{ __('Sets') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ([$side1, $side2] as $name)
                                <tr>
                                    <td class="who">{{ $name }}</td>
                                    @for ($set = 1; $set <= $setColumns; $set++)
                                        <td class="set"></td>
                                    @endfor
                                    <td class="won"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @empty
                    <p class="empty">{{ __('No matches scheduled in this pool.') }}</p>
                @endforelse

                <p class="cut__foot">{{ __('Bring this sheet back to the desk once the pool is finished.') }}</p>
            </section>
        @endforeach
    @endif

@endsection
