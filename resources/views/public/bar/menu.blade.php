{{--
    La carte du bar, telle qu'on la lit à table après avoir scanné le QR.

    Celui qui scanne veut un prix, tout de suite : la carte occupe le premier
    écran, les annonces attendent en dessous. Aucune typo ne rétrécit ici — sur
    un téléphone, dérouler coûte moins cher que plisser les yeux.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="light dark">
    <title>La carte du bar — {{ config('club.name') }}</title>
    @include('public.bar._tokens')
    <style>
        /*
           La page suit le thème de l'appareil, comme le reste du site : jetons
           clairs d'abord, puis les deux bascules (choix explicite et réglage
           système). En sombre, l'accent passe au jaune du club — le bleu ne
           tient pas la lecture sur fond sombre.
        */
        :root {
            --menu-page: var(--paper);
            --menu-accent: var(--blue);
            --menu-dots: #ccd2e2;
            --menu-out: #9aa1b3;
            --menu-ads: #f4f6fb;
            --menu-featured-bg: #fffaeb;
            --menu-featured-ink: #92400e;
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --menu-page: #141414;
                --ink: #efefef;
                --ink-2: #c4c4c4;
                --muted: #9a9a9a;
                --line: #414141;
                --menu-accent: var(--yellow);
                --menu-dots: #4a4a4a;
                --menu-out: #8a8a8a;
                --menu-ads: #1f1f1f;
                --menu-featured-bg: #241d0c;
                --menu-featured-ink: var(--yellow);
            }
        }

        :root[data-theme="dark"] {
            --menu-page: #141414;
            --ink: #efefef;
            --ink-2: #c4c4c4;
            --muted: #9a9a9a;
            --line: #414141;
            --menu-accent: var(--yellow);
            --menu-dots: #4a4a4a;
            --menu-out: #8a8a8a;
            --menu-ads: #1f1f1f;
            --menu-featured-bg: #241d0c;
            --menu-featured-ink: var(--yellow);
        }

        body { background: var(--menu-page); color: var(--ink); font-size: 16px; line-height: 1.5; }

        .page {
            max-width: 34rem;
            margin-inline: auto;
            padding-inline: 20px;
            padding-block: 22px 40px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .head { display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--line); padding-bottom: 16px; }
        .head img { width: 30px; height: 30px; }
        .head__club { font-size: 10px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; color: var(--muted); }
        .head__title { font-size: 21px; font-weight: 700; letter-spacing: -.02em; }

        .featured {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            border: 1px solid rgba(251, 191, 36, .6); border-left: 4px solid var(--yellow);
            background: var(--menu-featured-bg); border-radius: 6px; padding: 11px 13px;
        }
        .featured__label { display: block; font-family: var(--font-serif); font-style: italic; font-size: 14px; color: var(--menu-featured-ink); line-height: 1.2; }
        .featured__name { font-size: 17px; font-weight: 700; }
        .featured .price { font-size: 17px; }

        .cat { display: flex; flex-direction: column; }
        .cat__title {
            font-size: 11px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--menu-accent);
            padding-bottom: 7px; border-bottom: 1px solid var(--line); margin-bottom: 7px;
        }
        .row { display: flex; align-items: baseline; gap: 8px; padding-block: 6px; font-size: 15.5px; }
        .row__fill { flex: 1; border-bottom: 1px dotted var(--menu-dots); transform: translateY(-3px); }
        .row--out { color: var(--menu-out); }
        .tag {
            font-size: 9px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
            color: var(--menu-page); background: var(--menu-out); border-radius: 10px; padding: 1px 7px; white-space: nowrap;
        }

        .note { font-size: 13px; color: var(--muted); border-top: 1px solid var(--line); padding-top: 14px; margin: 0; }
        .note strong { color: var(--ink); font-weight: 600; }

        .ads { background: var(--menu-ads); border-radius: 8px; padding: 16px; display: flex; flex-direction: column; gap: 16px; }
        .ads__kicker { font-size: 10px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--menu-accent); }
        .ad { display: flex; gap: 11px; align-items: flex-start; }
        .ad__icon { font-size: 20px; line-height: 1.2; }
        .ad__title { font-size: 14.5px; font-weight: 700; line-height: 1.3; }
        .ad__meta { font-size: 12.5px; color: var(--muted); }
        .sponsors { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
        .sponsors img {
            width: 100%; aspect-ratio: 1; object-fit: contain; background: #fff;
            border: 1px solid var(--line); border-radius: 6px; padding: 8px; max-width: 100%;
        }

        .back { font-size: 13px; color: var(--menu-accent); text-align: center; }
        .back a { color: inherit; }
    </style>
</head>
<body>
    <div class="page">
        <header class="head">
            <img src="{{ asset('images/logo-club.svg') }}" alt="">
            <div>
                <div class="head__club">{{ config('club.name') }}</div>
                <div class="head__title">La carte du bar</div>
            </div>
        </header>

        @if ($featured)
            <div class="featured">
                <div>
                    <span class="featured__label">{{ \App\Services\PublicBarMenuService::FEATURED_CATEGORY }}</span>
                    <span class="featured__name">{{ $featured['name'] }}</span>
                </div>
                <span class="price">{{ $featured['price'] }}</span>
            </div>
        @endif

        @foreach ($categories as $category)
            <section class="cat">
                <h2 class="cat__title">{{ $category['name'] }}</h2>
                @foreach ($category['products'] as $product)
                    <div class="row {{ $product['out'] ? 'row--out' : '' }}">
                        <span>{{ $product['name'] }}</span>
                        <span class="row__fill"></span>
                        <span class="price">{{ $product['out'] ? 'Épuisé' : $product['price'] }}</span>
                    </div>
                @endforeach
            </section>
        @endforeach

        <p class="note">
            <strong>Espèces</strong> ou <strong>paiement par QR</strong>.
            Chaque consommation soutient le club et ses jeunes.
        </p>

        {{-- Les annonces viennent après la carte : celui qui scanne cherche un
             prix, pas une affiche. C'est aussi là qu'elles se lisent vraiment,
             plutôt que balayées du pouce en haut de page. --}}
        @if ($announcements || $news || $sponsors)
            <section class="ads">
                @if ($announcements)
                    <span class="ads__kicker">À venir au club</span>
                    @foreach ($announcements as $announcement)
                        <div class="ad">
                            <span class="ad__icon">{{ $announcement['icon'] ?: '🏓' }}</span>
                            <div>
                                <div class="ad__title">{{ $announcement['title'] }}</div>
                                <div class="ad__meta">
                                    {{ $announcement['when'] }}
                                    @if ($announcement['where']) · {{ $announcement['where'] }} @endif
                                    @if ($announcement['price']) · {{ $announcement['price'] }} @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif

                @foreach ($news as $post)
                    <div class="ad">
                        <span class="ad__icon">🏓</span>
                        <div>
                            <div class="ad__title">{{ $post['title'] }}</div>
                            <div class="ad__meta">{{ $post['category'] }}</div>
                        </div>
                    </div>
                @endforeach

                @if ($sponsors)
                    <div>
                        <span class="ads__kicker">Ils soutiennent le club</span>
                        <div class="sponsors">
                            @foreach ($sponsors as $sponsor)
                                <img src="{{ $sponsor['logo'] }}" alt="{{ $sponsor['name'] }}">
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <p class="back"><a href="{{ route('home') }}">{{ config('club.name') }}</a></p>
    </div>
</body>
</html>
