{{--
    La feuille du tournoi, pour le mur de la salle.

    Page autonome, sans le gabarit de l'application : ce qui doit disparaître à
    l'impression n'est pas ici. Elle s'ouvre dans un onglet, appelle la boîte
    d'impression toute seule, et se relit à l'écran si on annule.

    Encre : le QR et les noms sont en noir. Une salle imprime en noir et blanc,
    et un QR gris clair ne se scanne pas.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tournament->name }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            background: #f4f4f5;
            color: #000;
            font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            font-size: 12px;
            line-height: 1.4;
        }

        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            padding: 14mm;
            background: #fff;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.15);
        }

        .masthead { display: flex; align-items: flex-start; gap: 18px; border-bottom: 2px solid #000; padding-bottom: 12px; }
        .masthead__text { flex: 1; min-width: 0; }
        .club { font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #52525b; }
        h1 { margin: 2px 0 6px; font-size: 26px; line-height: 1.1; font-weight: 800; }
        .when { font-size: 13px; font-weight: 600; }
        .where { font-size: 12px; color: #3f3f46; }

        /* Le bloc qui fait tout le travail : on le scanne en passant. */
        .qr { flex-shrink: 0; width: 34mm; text-align: center; }
        .qr img { display: block; width: 34mm; height: 34mm; }
        .qr__caption { margin-top: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
        .qr__url { margin-top: 2px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 7.5px; color: #52525b; word-break: break-all; }

        .pools { margin-top: 14px; column-count: 2; column-gap: 10mm; }
        .pool { break-inside: avoid; margin-bottom: 8mm; }
        .pool__name { margin: 0 0 4px; font-size: 15px; font-weight: 800; border-bottom: 1px solid #000; padding-bottom: 2px; }

        table { width: 100%; border-collapse: collapse; }
        .players td { padding: 3px 0; font-size: 12.5px; font-weight: 600; border-bottom: 1px dotted #d4d4d8; }
        .players td.seed { width: 16px; color: #71717a; font-weight: 700; }

        .matches { margin-top: 5px; }
        .matches caption { text-align: left; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #71717a; padding-bottom: 2px; }
        .matches td { padding: 2.5px 0; font-size: 11px; border-bottom: 1px dotted #e4e4e7; }
        .matches td.versus { color: #a1a1aa; padding: 0 4px; font-style: italic; }
        /* La case où l'arbitre écrit le score au stylo. */
        .matches td.score { width: 46px; border: 1px solid #a1a1aa; border-radius: 2px; height: 16px; }

        .empty { margin-top: 18px; padding: 14px; border: 1px dashed #a1a1aa; text-align: center; font-size: 12px; color: #52525b; }

        footer { margin-top: 10mm; border-top: 1px solid #d4d4d8; padding-top: 5px; font-size: 9px; color: #71717a; display: flex; justify-content: space-between; }

        @page { size: A4; margin: 10mm; }

        @media print {
            body { padding: 0; background: #fff; }
            .sheet { max-width: none; margin: 0; padding: 0; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="no-print" style="max-width:210mm;margin:0 auto 12px;text-align:right;">
        <button type="button" onclick="window.print()"
            style="font:inherit;font-weight:700;padding:8px 16px;border:0;border-radius:6px;background:#1e40af;color:#fff;cursor:pointer;">
            {{ __('Print') }}
        </button>
    </div>

    <div class="sheet">

        <header class="masthead">
            <div class="masthead__text">
                <p class="club">CTT Ottignies-Blocry</p>
                <h1>{{ $tournament->name }}</h1>
                <p class="when">
                    {{ $tournament->startsAt()?->translatedFormat('l j F Y') }}@if ($tournament->hasKnownStartTime()) · {{ $tournament->startsAt()->format('H\hi') }}@endif
                </p>
                @if ($tournament->rooms->isNotEmpty())
                    <p class="where">{{ $tournament->rooms->pluck('name')->join(' · ') }}</p>
                @endif
            </div>

            <div class="qr">
                <img src="{{ $qrDataUri }}" alt="{{ __('Follow the tournament live') }}">
                <p class="qr__caption">{{ __('Follow the tournament live') }}</p>
                <p class="qr__url">{{ $liveUrl }}</p>
            </div>
        </header>

        @if ($pools->isEmpty())
            <p class="empty">{{ __('No pools generated yet.') }}</p>
        @else
            <div class="pools">
                @foreach ($pools as $pool)
                    @php
                        $isDoubles = $pool->pairs->isNotEmpty();
                        $entries = $isDoubles
                            ? $pool->pairs->map(fn ($pair) => $pair->displayName())
                            : $pool->users->map(fn ($user) => $user->full_name);
                    @endphp

                    <section class="pool">
                        <h2 class="pool__name">{{ $pool->name }}</h2>

                        <table class="players">
                            @foreach ($entries as $i => $name)
                                <tr>
                                    <td class="seed">{{ $i + 1 }}</td>
                                    <td>{{ $name }}</td>
                                </tr>
                            @endforeach
                        </table>

                        @if ($pool->tournamentmatches->isNotEmpty())
                            <table class="matches">
                                <caption>{{ __('Matches') }}</caption>
                                @foreach ($pool->tournamentmatches as $match)
                                    @php
                                        $pairMatch = $match->pair1_id !== null;
                                        $side1 = $pairMatch ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—');
                                        $side2 = $pairMatch ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—');
                                    @endphp
                                    <tr>
                                        <td>{{ $side1 }}</td>
                                        <td class="versus">vs</td>
                                        <td>{{ $side2 }}</td>
                                        <td class="score"></td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif

        <footer>
            <span>{{ __('Follow the tournament live') }} — {{ $liveUrl }}</span>
            <span>{{ now()->translatedFormat('d/m/Y H:i') }}</span>
        </footer>

    </div>

    <script>
        // La boîte d'impression s'ouvre seule : le bouton n'existe que pour
        // ceux qui l'annulent puis changent d'avis.
        window.addEventListener('load', () => window.print());
    </script>

</body>
</html>
