{{--
    Le gabarit des feuilles à imprimer.

    Page autonome, sans le gabarit de l'application : ce qui doit disparaître à
    l'impression n'est pas ici. Elle s'ouvre dans un onglet, appelle la boîte
    d'impression toute seule, et se relit à l'écran si on annule.

    Encre noire. Une salle imprime en noir et blanc, et une feuille pensée en
    couleur y devient une bouillie de gris.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
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
            padding: 12mm;
            background: #fff;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.15);
        }

        .masthead { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .masthead__logo { flex-shrink: 0; width: 18mm; height: 18mm; }
        .masthead__text { flex: 1; min-width: 0; }
        .club { font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #52525b; }
        h1 { margin: 1px 0 4px; font-size: 24px; line-height: 1.1; font-weight: 800; }
        .when { font-size: 13px; font-weight: 600; }
        .where { font-size: 11.5px; color: #3f3f46; }

        .empty { margin-top: 18px; padding: 14px; border: 1px dashed #a1a1aa; text-align: center; font-size: 12px; color: #52525b; }

        .toolbar { max-width: 210mm; margin: 0 auto 12px; text-align: right; }
        .toolbar button {
            font: inherit; font-weight: 700; padding: 8px 16px;
            border: 0; border-radius: 6px; background: #1e40af; color: #fff; cursor: pointer;
        }

        @page { size: A4; margin: 10mm; }

        @media print {
            body { padding: 0; background: #fff; }
            .sheet { max-width: none; margin: 0; padding: 0; box-shadow: none; }
            .no-print { display: none !important; }
        }
    </style>
    @stack('styles')
</head>
<body>

    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    </div>

    <div class="sheet">
        @yield('content')
    </div>

    <script>
        // La boîte d'impression s'ouvre seule : le bouton n'existe que pour
        // ceux qui l'annulent puis changent d'avis.
        window.addEventListener('load', () => window.print());
    </script>

</body>
</html>
