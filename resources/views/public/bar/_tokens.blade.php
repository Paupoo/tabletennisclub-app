{{--
    Les jetons des trois surfaces de la carte.

    Pages autonomes, sans Tailwind ni Alpine : un téléviseur casté n'a ni
    clavier ni console, et la feuille part à l'imprimante. Moins il y a de
    chaîne de compilation entre le catalogue et le mur, moins il y a de
    manières de se retrouver avec un écran blanc un samedi soir.

    Les deux couleurs du club, sur fond nuit pour l'écran et sur blanc pour ce
    qui se lit dans la main.
--}}
<style>
    :root {
        --blue: #1e40af;
        --blue-light: #3b82f6;
        --yellow: #fbbf24;

        --ink: #141824;
        --ink-2: #414a5e;
        --muted: #6f7789;
        --line: #e6e9f2;
        --paper: #ffffff;

        --night: #0a1124;
        --night-panel: #111b39;
        --night-line: rgba(255, 255, 255, .11);
        --night-text: #f5f7fc;
        --night-muted: #9aa5c3;

        --font-sans: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        --font-serif: "Instrument Serif", Georgia, "Times New Roman", serif;
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: var(--font-sans);
        -webkit-font-smoothing: antialiased;
    }

    /* Le prix ne se coupe jamais avant le symbole : mesuré, une ligne cassée
       fait perdre deux crans de taille à toute la carte affichée à l'écran. */
    .price { font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
</style>
