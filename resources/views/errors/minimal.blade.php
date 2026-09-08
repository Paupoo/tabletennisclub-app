{{--
    Le gabarit des huit pages d'erreur.

    Il embarquait un instantané figé de Tailwind v1, avec sa propre règle
    `prefers-color-scheme` — si bien qu'elles répondaient déjà au mode sombre,
    mais dans un gris (#1a202c) étranger à la palette, sans logo, sans lien de
    retour et sans la typographie du site. Un 404 est pourtant la page publique
    la plus probable après l'accueil.

    Le style reste en ligne et sans dépendance : une page d'erreur doit
    s'afficher quand le reste ne s'affiche plus. Pas de @vite — un manifeste
    manquant lèverait une exception à l'intérieur du gestionnaire d'exceptions.
    Pas de police distante non plus : `Instrument Sans` n'est ni auto-hébergée
    ni chargée depuis un CDN, elle retombe déjà sur la pile système.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') — {{ config('club.name') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-club.svg') }}">

        <style>
            :root {
                color-scheme: light;
                --ground: #f5f5f5;
                --card: #ffffff;
                --ink: #161616;
                --ink-soft: #555555;
                --rule: #e2e2e2;
                --accent: #1e40af;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    color-scheme: dark;
                    --ground: #161616;
                    --card: #222222;
                    --ink: #eeeeee;
                    --ink-soft: #9e9e9e;
                    --rule: #424242;
                    --accent: #fbbf24;
                }

                /*
                  Le logo est un SVG monochrome en bleu club : posé tel quel sur la
                  carte sombre il mesure 1,83:1. C'est le recolorage que prescrit le
                  design system pour les fonds sombres.
                */
                .mark { filter: brightness(0) invert(1); }
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: var(--ground);
                color: var(--ink);
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
                -webkit-font-smoothing: antialiased;
            }

            .card {
                background: var(--card);
                border: 1px solid var(--rule);
                border-radius: 12px;
                padding: 40px 36px;
                max-width: 30rem;
                width: 100%;
                text-align: center;
            }

            .mark { width: 56px; height: 56px; margin: 0 auto 20px; display: block; }

            .code {
                font-size: 13px;
                font-weight: 700;
                letter-spacing: .16em;
                color: var(--accent);
                margin: 0 0 10px;
            }

            .message {
                font-size: 22px;
                font-weight: 600;
                line-height: 1.3;
                margin: 0 0 24px;
                text-wrap: balance;
            }

            .home {
                display: inline-block;
                font-size: 15px;
                font-weight: 600;
                text-decoration: none;
                color: var(--accent);
                border: 1px solid var(--accent);
                border-radius: 8px;
                padding: 10px 22px;
            }

            .home:hover { text-decoration: underline; }
            .home:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }

            .club { margin: 22px 0 0; font-size: 13px; color: var(--ink-soft); }
        </style>
    </head>
    <body>
        <main class="card">
            <img class="mark" src="{{ asset('images/logo-club.svg') }}" alt="">
            <p class="code">{{ __('Error') }} @yield('code')</p>
            <h1 class="message">@yield('message')</h1>
            <a class="home" href="{{ url('/') }}">{{ __('Back to homepage') }}</a>
            <p class="club">{{ config('club.name') }}</p>
        </main>
    </body>
</html>
