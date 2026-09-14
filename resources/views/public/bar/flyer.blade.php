{{--
    Le flyer à poser sur les tables : deux A5 sur une A4 ordinaire.

    A5 paysage plutôt que portrait, pour que la feuille sorte d'une A4 portrait
    sans que personne n'ait à trouver le réglage « paysage » de l'imprimante du
    club un samedi soir. La carte se lit donc en deux colonnes : l'identité à
    gauche, le QR à droite, à la taille où on le scanne sans se pencher.

    Le logo, le nom du club, le QR, le lien en clair. Pas de prix : une carte
    imprimée périme au premier changement, et c'est précisément ce que le QR
    évite.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>La carte du bar — feuille à poser</title>
    @include('public.bar._tokens')
    <style>
        @page { size: A4 portrait; margin: 0; }

        body { background: #f4f4f5; color: var(--ink); }

        .sheet {
            width: 210mm; height: 297mm;
            margin: 12px auto;
            background: var(--paper);
            display: grid;
            grid-template-rows: 1fr 1fr;
            box-shadow: 0 1px 3px rgb(0 0 0 / .15);
        }

        /* Une A5 paysage : la moitié d'une A4 portrait, trait de coupe compris. */
        .card {
            container-type: size;
            border: 1px dashed #d4d4d8;
            padding: 6cqw 7cqw;
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 6cqw;
        }

        .card__left { display: flex; flex-direction: column; gap: 4cqw; min-width: 0; }
        .card__brand { display: flex; align-items: center; gap: 2cqw; }
        .card__brand img { width: 9cqw; height: 9cqw; flex: none; }
        .card__club { font-size: 2.5cqw; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--blue); }

        .card__title { font-size: 9.4cqw; font-weight: 700; letter-spacing: -.02em; line-height: 1.02; text-wrap: balance; }
        .card__serif { font-family: var(--font-serif); font-style: italic; font-size: 4.4cqw; color: var(--ink-2); }
        .card__foot { font-size: 2.7cqw; color: var(--muted); line-height: 1.45; margin: 0; text-wrap: balance; }

        .card__right { display: flex; flex-direction: column; align-items: center; gap: 2.4cqw; }
        .card__qr { width: 40cqw; height: 40cqw; display: block; }
        .card__url {
            font-size: 2.9cqw; font-weight: 700; color: var(--blue); text-align: center;
            border-top: 1px solid rgba(30, 64, 175, .3); padding-top: 1.6cqw; max-width: 38cqw;
        }

        .hint {
            max-width: 210mm; margin: 0 auto 16px; padding: 10px 14px;
            font-size: 13px; color: var(--ink-2); background: #fffaeb;
            border: 1px solid rgba(251, 191, 36, .5); border-radius: 6px;
        }

        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; }
            .hint { display: none; }
            .card { border-color: #e4e4e7; }
        }
    </style>
</head>
<body>
    <p class="hint">Imprimez sans marges, puis coupez en deux. Le QR mène à {{ $url }} — si l'adresse du site change, réimprimez cette page : le code suit.</p>

    <div class="sheet">
        @for ($i = 0; $i < 2; $i++)
            <div class="card">
                <div class="card__left">
                    <div class="card__brand">
                        <img src="{{ asset('images/logo-club.svg') }}" alt="">
                        <span class="card__club">{{ config('club.name') }}</span>
                    </div>

                    <div>
                        <div class="card__title">La carte du bar</div>
                        <div class="card__serif">À votre santé</div>
                    </div>

                    <p class="card__foot">Scannez le code pour voir ce qu'on sert et les prix.<br>Bon match&nbsp;!</p>
                </div>

                <div class="card__right">
                    <img class="card__qr" src="{{ $qr }}" alt="QR code vers la carte du bar">
                    <span class="card__url">{{ Str::after($url, '://') }}</span>
                </div>
            </div>
        @endfor
    </div>
</body>
</html>
