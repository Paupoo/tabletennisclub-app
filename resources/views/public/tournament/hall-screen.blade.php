{{--
    L'écran de salle d'un tournoi, casté sur une télé.

    Lu de loin, debout, en quelques secondes : à gauche les tables (qui joue
    où), au centre ce qui suit, à droite une poule à la fois qui tourne toutes
    les 15 secondes. Les deux premières colonnes ne bougent jamais : « je joue
    où ? » doit toujours avoir sa réponse à l'écran.

    Mêmes jetons que l'écran du bar, même absence de framework : un téléviseur
    casté n'a ni clavier ni console. La page se recharge seule.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta http-equiv="refresh" content="60">
    <title>{{ $tournament->name }} — {{ config('club.name') }}</title>
    @include('public.bar._tokens')
    <style>
        html, body { height: 100%; }
        body { background: var(--night); color: var(--night-text); overflow: hidden; }

        .screen {
            container-type: size;
            height: 100vh;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            gap: 1.6cqw;
            padding: 2.2cqw 2.6cqw;
        }
        .head { display: flex; justify-content: space-between; align-items: baseline; gap: 2cqw; }
        .head h1 { margin: 0; font-size: 2.6cqw; font-weight: 700; }
        .head p { margin: 0; font-size: 1.6cqw; color: var(--night-muted); font-variant-numeric: tabular-nums; }

        .cols { display: grid; grid-template-columns: 1.25fr 1fr 1fr; gap: 1.6cqw; min-height: 0; }
        .panel {
            background: var(--night-panel);
            border: 1px solid var(--night-line);
            border-radius: 1cqw;
            padding: 1.4cqw;
            display: flex; flex-direction: column; gap: .8cqw;
            min-height: 0; overflow: hidden;
        }
        .cap { margin: 0; font-size: 1.3cqw; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--yellow); }

        .row { display: grid; grid-template-columns: 5cqw 1fr; gap: 1cqw; align-items: center; font-size: 1.7cqw; padding-bottom: .6cqw; border-bottom: 1px solid var(--night-line); }
        .num { background: var(--yellow); color: var(--night); font-weight: 700; border-radius: .6cqw; text-align: center; padding: .3cqw 0; font-variant-numeric: tabular-nums; }
        .free { color: #5fd3a3; }

        .next { display: grid; grid-template-columns: 1fr auto; gap: 1cqw; font-size: 1.6cqw; padding-bottom: .5cqw; border-bottom: 1px solid var(--night-line); }
        .next:first-of-type { color: var(--yellow); font-weight: 700; }
        .where { color: var(--night-muted); font-size: 1.3cqw; }
        .empty { color: var(--night-muted); font-size: 1.6cqw; }

        table { width: 100%; border-collapse: collapse; font-size: 1.5cqw; }
        td { padding: .4cqw .6cqw; border-bottom: 1px solid var(--night-line); }
        td.n { text-align: right; font-variant-numeric: tabular-nums; color: var(--night-muted); }
        .slide[hidden] { display: none; }
        .dots { margin-top: auto; display: flex; gap: .6cqw; justify-content: center; }
        .dots i { width: .8cqw; height: .8cqw; border-radius: 50%; background: var(--night-line); }
        .dots i.on { background: var(--yellow); }
    </style>
</head>
<body>
<main class="screen">
    <header class="head">
        <h1>{{ $tournament->name }}</h1>
        <p>{{ $facts }} · {{ now()->format('H\hi') }}</p>
    </header>

    <div class="cols">
        <section class="panel">
            <p class="cap">{{ __('On the tables') }}</p>
            @forelse ($tables as $table)
                <div class="row">
                    <span class="num">{{ $table['name'] }}</span>
                    <span @class(['free' => $table['match'] === null])>{{ $table['match'] ?? __('Free') }}</span>
                </div>
            @empty
                <p class="empty">{{ __('No table yet.') }}</p>
            @endforelse
        </section>

        <section class="panel">
            <p class="cap">{{ __('Up next') }}</p>
            @forelse ($queue as $next)
                <div class="next">
                    <span>{{ $next['match'] }}</span>
                    <span class="where">{{ $next['where'] }}</span>
                </div>
            @empty
                <p class="empty">{{ __('All matches are done or in progress.') }}</p>
            @endforelse
        </section>

        <section class="panel" data-rotating-panel>
            @forelse ($pools as $index => $pool)
                <div class="slide" @if ($index > 0) hidden @endif>
                    <p class="cap">{{ $pool['name'] }}</p>
                    <table>
                        @foreach ($pool['rows'] as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="n">{{ trans_choice(':count played|:count played', $row['played'], ['count' => $row['played']]) }}</td>
                                <td class="n">{{ trans_choice(':count won|:count won', $row['won'], ['count' => $row['won']]) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @empty
                <p class="empty">{{ __('No pools generated yet.') }}</p>
            @endforelse
            @if (count($pools) > 1)
                <div class="dots" aria-hidden="true">
                    @foreach ($pools as $index => $pool)
                        <i @class(['on' => $index === 0])></i>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</main>
<script>
    /*
     * Une poule toutes les 15 secondes, et la page se recharge toutes les 30
     * pour suivre les tables. La poule affichée survit au rechargement, sinon
     * l'écran repartirait toujours de la première. Le meta refresh (60 s) ne
     * sert que si ce script ne tourne pas.
     */
    (() => {
        const key = 'hall-screen-{{ $tournament->id }}';
        const slides = [...document.querySelectorAll('[data-rotating-panel] .slide')];
        const dots = [...document.querySelectorAll('[data-rotating-panel] .dots i')];
        const read = () => { try { return Number(sessionStorage.getItem(key)) || 0; } catch { return 0; } };
        const write = (value) => { try { sessionStorage.setItem(key, String(value)); } catch {} };
        let shown = slides.length ? read() % slides.length : 0;
        const show = () => slides.forEach((slide, index) => {
            slide.hidden = index !== shown;
            dots[index]?.classList.toggle('on', index === shown);
        });
        show();
        if (slides.length > 1) {
            setInterval(() => { shown = (shown + 1) % slides.length; write(shown); show(); }, 15000);
        }
        setTimeout(() => location.reload(), 30000);
    })();
</script>
</body>
</html>
