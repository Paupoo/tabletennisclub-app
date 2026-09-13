{{--
    La feuille à poser sur les tables : quatre A6 sur une A4 ordinaire.

    Le logo, le nom du club, le QR, le lien en clair. Pas de prix : une carte
    imprimée périme au premier changement, et c'est précisément ce que le QR
    évite. La page appelle la boîte d'impression toute seule, et se relit à
    l'écran si on annule.
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
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            box-shadow: 0 1px 3px rgb(0 0 0 / .15);
        }

        .card {
            container-type: size;
            border: 1px dashed #d4d4d8;
            padding: 7cqw 6cqw;
            display: grid;
            grid-template-rows: auto 1fr auto;
            text-align: center;
        }

        .card__top { display: flex; flex-direction: column; align-items: center; gap: 2cqw; }
        .card__top img { width: 13cqw; height: 13cqw; }
        .card__club { font-size: 3.2cqw; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--blue); }

        .card__mid { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3.4cqw; }
        .card__title { font-size: 9cqw; font-weight: 700; letter-spacing: -.02em; line-height: 1.05; }
        .card__serif { font-family: var(--font-serif); font-style: italic; font-size: 5cqw; color: var(--ink-2); }
        .card__qr { width: 44cqw; height: 44cqw; display: block; }
        .card__url {
            font-size: 3.7cqw; font-weight: 700; color: var(--blue);
            border-bottom: 1px solid rgba(30, 64, 175, .3); padding-bottom: .6cqw; word-break: break-all;
        }
        .card__foot { font-size: 3.2cqw; color: var(--muted); line-height: 1.4; margin: 0; }

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
    <p class="hint">Imprimez sans marges, puis coupez en quatre. Le QR mène à {{ $url }} — si l'adresse du site change, réimprimez cette page : le code suit.</p>

    <div class="sheet">
        @for ($i = 0; $i < 4; $i++)
            <div class="card">
                <div class="card__top">
                    <img src="{{ asset('images/logo-club.svg') }}" alt="">
                    <span class="card__club">{{ config('club.name') }}</span>
                </div>

                <div class="card__mid">
                    <div>
                        <div class="card__title">La carte<br>du bar</div>
                        <div class="card__serif">en ligne, toujours à jour</div>
                    </div>
                    <img class="card__qr" src="{{ $qr }}" alt="QR code vers la carte du bar">
                    <span class="card__url">{{ Str::after($url, '://') }}</span>
                </div>

                <p class="card__foot">Scannez pour voir ce qu'on sert<br>et les prix. Bon match&nbsp;! 🏓</p>
            </div>
        @endfor
    </div>
</body>
</html>
