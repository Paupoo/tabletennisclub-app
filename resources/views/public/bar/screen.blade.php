{{--
    L'écran casté derrière le bar.

    Lu à trois mètres, allumé toute la soirée : fond nuit, prix en blanc,
    accents jaunes. Un grand aplat blanc éblouit et écrase les logos des
    sponsors ; le sombre fait ressortir le jaune du club.

    La page se règle elle-même. Elle mesure ce qu'elle rend, descend d'un cran
    tant que la carte déborde, et s'arrête à un plancher de lisibilité — la
    transposition de DS-B à une lecture à trois mètres. Sous ce plancher, elle
    pagine plutôt que d'écrire trop petit : mieux vaut attendre son tour que
    plisser les yeux.

    Aucun framework : un téléviseur casté n'a ni clavier ni console.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>La carte du bar — {{ config('club.name') }}</title>
    @include('public.bar._tokens')
    <style>
        html, body { height: 100%; }
        body {
            background: var(--night);
            color: var(--night-text);
            overflow: hidden;
        }

        /* Tout est en cqw : la mise en page est la même image quelle que soit
           la définition que le Chromecast décide de rendre. */
        .screen {
            container-type: size;
            height: 100vh;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            gap: 2.2cqw;
            padding: 2.6cqw 3.2cqw;
            --row: 2cqw;
            --cat: 1.5cqw;
            --cols: 3;
        }

        .head { display: flex; align-items: center; justify-content: space-between; gap: 2.5cqw; }
        .brand { display: flex; align-items: center; gap: 1.3cqw; }
        .brand img { width: 4cqw; height: 4cqw; filter: brightness(0) invert(1); opacity: .95; }
        .brand__name { font-size: 1.35cqw; font-weight: 700; letter-spacing: .2em; text-transform: uppercase; color: var(--night-muted); }
        .head__title { font-size: 3.2cqw; font-weight: 700; letter-spacing: -.02em; }

        /* La vedette vit dans l'en-tête : elle y trouve de la place perdue, et
           ne prend rien à la carte — mesuré, c'était exactement la hauteur qui
           manquait pour tenir en un seul écran. */
        .featured {
            display: flex; align-items: center; gap: 1.6cqw; flex: none;
            border: 1px solid rgba(251, 191, 36, .45); border-left: .45cqw solid var(--yellow);
            background: linear-gradient(90deg, rgba(251, 191, 36, .16), rgba(251, 191, 36, .02));
            border-radius: .5cqw; padding: .9cqw 1.6cqw;
        }
        .featured__label { display: block; font-family: var(--font-serif); font-style: italic; font-size: 1.6cqw; color: var(--yellow); line-height: 1.15; }
        .featured__name { font-size: 2.1cqw; font-weight: 700; letter-spacing: -.02em; white-space: nowrap; }
        .featured .price { font-size: 2.1cqw; color: var(--yellow); }

        .main { display: grid; grid-template-columns: minmax(0, 2.4fr) minmax(0, 1fr); gap: 2.2cqw; min-height: 0; }
        .pages { display: grid; min-height: 0; }
        .page {
            grid-area: 1 / 1; min-height: 0; overflow: hidden;
            opacity: 0; visibility: hidden; transition: opacity .6s ease-out;
        }
        .page[data-active="true"] { opacity: 1; visibility: visible; }

        .cats { display: grid; grid-template-columns: repeat(var(--cols), minmax(0, 1fr)); gap: 1.8cqw 2.2cqw; align-content: start; }
        .cats__col { display: flex; flex-direction: column; gap: 1.8cqw; min-width: 0; }
        .cat__title {
            font-size: var(--cat); font-weight: 700; letter-spacing: .18em; text-transform: uppercase; color: var(--yellow);
            padding-bottom: .6cqw; margin-bottom: .8cqw; border-bottom: 1px solid var(--night-line);
        }
        .row { display: flex; align-items: baseline; gap: .55cqw; font-size: var(--row); line-height: 1.34; padding-block: .16cqw; }
        .row__name { font-weight: 500; white-space: nowrap; }
        .row__fill { flex: 1; min-width: .5cqw; border-bottom: 1px dotted rgba(255, 255, 255, .22); transform: translateY(-.3cqw); }
        .row .price { font-size: var(--row); }
        .row--out { color: var(--night-muted); }
        .row--out .row__fill { border-bottom-color: rgba(255, 255, 255, .1); }
        .tag {
            font-size: .95cqw; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
            color: var(--night); background: var(--night-muted); border-radius: 1cqw; padding: .1cqw .6cqw; white-space: nowrap;
        }

        /* Colonne publicitaire */
        .rail {
            background: var(--night-panel); border: 1px solid var(--night-line); border-radius: .8cqw;
            padding: 1.8cqw; display: grid; grid-template-rows: minmax(0, 1fr) auto; gap: 1.2cqw; min-height: 0;
        }
        .rail__slides { display: grid; min-height: 0; }
        .slide {
            grid-area: 1 / 1; display: flex; flex-direction: column; gap: 1.1cqw; min-height: 0;
            opacity: 0; visibility: hidden; transition: opacity .5s ease-out;
        }
        .slide[data-active="true"] { opacity: 1; visibility: visible; }
        .slide__kicker { font-size: 1.15cqw; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; color: var(--yellow); }
        .slide__icon { font-size: 3cqw; line-height: 1; }
        .slide__title { font-size: 1.95cqw; font-weight: 700; line-height: 1.2; letter-spacing: -.01em; }
        .slide__meta { font-size: 1.4cqw; color: var(--night-muted); display: flex; flex-direction: column; gap: .3cqw; }
        .slide__price {
            align-self: flex-start; font-size: 1.4cqw; font-weight: 700;
            color: var(--night); background: var(--yellow); border-radius: .4cqw; padding: .2cqw .8cqw;
        }
        .news { display: flex; flex-direction: column; gap: 1.3cqw; }
        .news__item { display: flex; flex-direction: column; gap: .35cqw; }
        .news__cat {
            font-size: 1.05cqw; font-weight: 700; letter-spacing: .1em; text-transform: uppercase;
            color: #bcd0ff; align-self: flex-start;
            border: 1px solid rgba(188, 208, 255, .35); border-radius: 1cqw; padding: .1cqw .6cqw;
        }
        .news__title { font-size: 1.55cqw; font-weight: 600; line-height: 1.25; }
        .sponsors { display: grid; grid-template-columns: 1fr 1fr; gap: 1.1cqw; }
        .sponsors img { width: 100%; aspect-ratio: 1; object-fit: contain; background: #fff; border-radius: .5cqw; padding: .7cqw; }
        .rail__dots { display: flex; gap: .6cqw; justify-content: center; }
        .dot { width: 1cqw; height: 1cqw; border-radius: 50%; padding: 0; border: 1px solid var(--night-muted); background: transparent; }
        .dot[aria-current="true"] { background: var(--yellow); border-color: var(--yellow); }

        .foot {
            display: flex; align-items: center; justify-content: space-between; gap: 2cqw;
            border-top: 1px solid var(--night-line); padding-top: 1.2cqw;
            font-size: 1.35cqw; color: var(--night-muted);
        }
        .foot strong { color: var(--night-text); font-weight: 600; }
        .foot__url { font-size: 1.3cqw; letter-spacing: .08em; text-transform: uppercase; color: var(--night-text); font-weight: 700; white-space: nowrap; }
        .pager { color: var(--yellow); font-weight: 700; letter-spacing: .1em; text-transform: uppercase; font-size: 1.2cqw; }

        @media (prefers-reduced-motion: reduce) {
            .page { transition: none; }
        }
    </style>
</head>
<body data-refresh="60">
    <div class="screen" id="screen">
        <header class="head">
            <div>
                <div class="brand">
                    <img src="{{ asset('images/logo-club.svg') }}" alt="">
                    <span class="brand__name">{{ config('club.name') }}</span>
                </div>
                <div class="head__title">La carte du bar</div>
            </div>

            @if ($featured)
                <div class="featured">
                    <div>
                        <span class="featured__label">{{ \App\Services\PublicBarMenuService::FEATURED_CATEGORY }}</span>
                        <span class="featured__name">{{ $featured['name'] }}</span>
                    </div>
                    <span class="price">{{ $featured['price'] }}</span>
                </div>
            @endif
        </header>

        <div class="main">
        {{-- La carte est rendue par le serveur : si le script meurt, l'écran
             affiche encore les prix. Le script ne fait que rééquilibrer les
             colonnes et régler la taille. --}}
        <main class="pages" id="pages">
            <div class="page" data-active="true">
                <div class="cats">
                    @foreach ($categories as $category)
                        <div class="cats__col" data-category="{{ $category['name'] }}">
                            <div>
                                <div class="cat__title">{{ $category['name'] }}</div>
                                @foreach ($category['products'] as $product)
                                    <div class="row {{ $product['out'] ? 'row--out' : '' }}">
                                        <span class="row__name">{{ $product['name'] }}</span>
                                        <span class="row__fill"></span>
                                        {{-- Le prix d'un produit qu'on ne peut pas servir n'apprend
                                             rien, et la pastille qui le doublait était, mesuré, ce
                                             qui empêchait la carte de tenir en un seul écran. --}}
                                        <span class="price">{{ $product['out'] ? 'Épuisé' : $product['price'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </main>

        {{-- La colonne reste en place : la carte ne bouge jamais, et le sponsor
             est vu en continu — c'est exactement ce qu'on lui vend. --}}
        <aside class="rail">
            <div class="rail__slides" id="slides">
                @foreach ($announcements as $announcement)
                    <div class="slide" data-slide>
                        <span class="slide__kicker">À venir au club</span>
                        <span class="slide__icon">{{ $announcement['icon'] ?: '🏓' }}</span>
                        <span class="slide__title">{{ $announcement['title'] }}</span>
                        <span class="slide__meta">
                            <span>{{ $announcement['when'] }}</span>
                            @if ($announcement['where'])
                                <span>{{ $announcement['where'] }}</span>
                            @endif
                        </span>
                        @if ($announcement['price'])
                            <span class="slide__price">{{ $announcement['price'] }}</span>
                        @endif
                    </div>
                @endforeach

                @if ($news)
                    <div class="slide" data-slide>
                        <span class="slide__kicker">Des nouvelles du club</span>
                        <div class="news">
                            @foreach ($news as $post)
                                <div class="news__item">
                                    <span class="news__cat">{{ $post['category'] }}</span>
                                    <span class="news__title">{{ $post['title'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($sponsors)
                    <div class="slide" data-slide>
                        <span class="slide__kicker">Ils soutiennent le club</span>
                        <div class="sponsors">
                            @foreach ($sponsors as $sponsor)
                                <img src="{{ $sponsor['logo'] }}" alt="{{ $sponsor['name'] }}">
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="rail__dots" id="dots"></div>
        </aside>
        </div>

        <footer class="foot">
            <span>
                <strong>Espèces</strong> ou <strong>paiement par QR</strong>
                · Chaque consommation soutient le club et ses jeunes. Merci&nbsp;!
            </span>
            <span class="pager" id="pager" hidden></span>
            <span class="foot__url">{{ Str::after(route('public.bar.menu'), '://') }}</span>
        </footer>
    </div>

    <script>
        /* Le catalogue se lit dans ce que le serveur a déjà rendu : une seule
           source dans la page, et les prix gardent leur mise en forme. */
        const CATALOGUE = Array.from(document.querySelectorAll('[data-category]')).map(col => ({
            name: col.dataset.category,
            products: Array.from(col.querySelectorAll('.row')),
        }));

        /*
         * Le plancher de lisibilité, en pourcentage de la largeur de l'écran :
         * 1,5 % font 29 px sur un 1080p, soit 13 mm de hauteur de lettre sur un
         * 43 pouces lu à trois mètres. `?min=` le relève devant la vraie télé,
         * le soir de l'installation, sans redéployer.
         */
        const params = new URLSearchParams(location.search);
        const floor = Math.min(3, Math.max(1, parseFloat(params.get('min')) || 1.5));
        const SCALES = [2.6, 2.5, 2.4, 2.3, 2.2, 2.1, 2.0, 1.9, 1.8, 1.7, 1.6, 1.5, 1.4, 1.3]
            .filter(s => s >= floor);

        const HEADER_COST = 1.4; // un titre de catégorie vaut 1,4 ligne de produit
        const screen = document.getElementById('screen');
        const pagesHost = document.getElementById('pages');
        const pager = document.getElementById('pager');

        function blockEl(block) {
            const box = document.createElement('div');
            const title = document.createElement('div');
            title.className = 'cat__title';
            title.textContent = block.name;
            box.appendChild(title);
            block.products.forEach(row => box.appendChild(row.cloneNode(true)));
            return box;
        }

        /* Remplit les colonnes sous une hauteur maximale, en lignes. Rend null
           si le nombre de colonnes n'y suffit pas. */
        function pack(cats, cols, max) {
            const out = [[]];
            const seen = new Set();
            let load = 0, block = null;

            for (const cat of cats) {
                block = null;
                for (const product of cat.products) {
                    if (load + (block ? 1 : HEADER_COST + 1) > max) {
                        if (out.length === cols) return null;
                        out.push([]); load = 0; block = null;
                    }
                    if (!block) {
                        block = { name: cat.name + (seen.has(cat) ? ' (suite)' : ''), products: [] };
                        seen.add(cat);
                        out[out.length - 1].push(block);
                        load += HEADER_COST;
                    }
                    block.products.push(product);
                    load += 1;
                }
            }
            while (out.length < cols) out.push([]);
            return out;
        }

        /* La plus petite hauteur de colonne qui tienne : un remplissage glouton
           laisse la dernière colonne absorber le reste, et c'est la plus haute
           qui impose la taille de toute la carte. */
        function columns(cats, cols) {
            const total = cats.reduce((s, c) => s + c.products.length + HEADER_COST, 0);
            for (let max = Math.ceil(total / cols * 10) / 10; max <= total; max += 0.1) {
                const attempt = pack(cats, cols, max);
                if (attempt) return attempt;
            }
            return pack(cats, cols, total);
        }

        function build(pagesOfCats, cols, scale) {
            screen.style.setProperty('--cols', cols);
            screen.style.setProperty('--row', scale + 'cqw');
            screen.style.setProperty('--cat', Math.max(1.1, scale * 0.76).toFixed(2) + 'cqw');
            pagesHost.replaceChildren(...pagesOfCats.map(cats => {
                const page = document.createElement('div');
                page.className = 'page';
                const grid = document.createElement('div');
                grid.className = 'cats';
                columns(cats, cols).forEach(blocks => {
                    const col = document.createElement('div');
                    col.className = 'cats__col';
                    blocks.forEach(b => col.appendChild(blockEl(b)));
                    grid.appendChild(col);
                });
                page.appendChild(grid);
                return page;
            }));
            pagesHost.firstElementChild.dataset.active = 'true';
        }

        /*
         * La hauteur ne suffit pas à décider : un nom de produit ne se coupe
         * pas, donc une carte courte laisse grandir la typo jusqu'à ce que
         * « Aquarius Citron » déborde sur la colonne voisine. Les deux sens
         * comptent.
         */
        function fits() {
            return Array.from(pagesHost.children).every(page => {
                if (page.scrollHeight > page.clientHeight + 1) return false;

                return Array.from(page.querySelectorAll('.row'))
                    .every(row => row.scrollWidth <= row.clientWidth + 1);
            });
        }

        /* Coupe entre catégories entières : une carte qui tourne au milieu des
           softs ne se lit pas. */
        function split(cats) {
            const total = cats.reduce((s, c) => s + c.products.length + HEADER_COST, 0);
            const pages = [[]];
            let load = 0;
            for (const cat of cats) {
                const weight = cat.products.length + HEADER_COST;
                if (load > 0 && load + weight > total / 2 + 0.5) { pages.push([]); load = 0; }
                pages[pages.length - 1].push(cat);
                load += weight;
            }
            return pages;
        }

        let timer = null;

        /*
         * Le nombre de colonnes se choisit, il ne se décrète pas.
         *
         * Trois colonnes sont indispensables à une carte fournie, mais elles
         * étranglent une carte courte : les colonnes deviennent trop étroites
         * pour « Croque-Monsieur », la typo bute sur la largeur alors que la
         * hauteur reste libre, et l'écran finit à moitié vide en petits
         * caractères. On essaie donc les deux et on garde la plus grosse typo.
         *
         * @return {{cols: number, scale: number}|null}
         */
        function best(pagesOfCats) {
            let winner = null;

            for (const cols of [2, 3]) {
                for (const scale of SCALES) {
                    build(pagesOfCats, cols, scale);
                    if (!fits()) continue;
                    if (!winner || scale > winner.scale) winner = { cols, scale };
                    break;
                }
            }

            return winner;
        }

        function layout() {
            if (!CATALOGUE.length) return;
            clearInterval(timer);

            const single = best([CATALOGUE]);
            if (single) {
                build([CATALOGUE], single.cols, single.scale);
                pager.hidden = true;
                return;
            }

            // Même au plancher, ça ne tient pas : on pagine plutôt que de
            // descendre sous ce qui se lit à trois mètres.
            const pagesOfCats = split(CATALOGUE);
            const paged = best(pagesOfCats) ?? { cols: 3, scale: SCALES[SCALES.length - 1] };
            build(pagesOfCats, paged.cols, paged.scale);

            const pages = Array.from(pagesHost.children);
            if (pages.length < 2) { pager.hidden = true; return; }

            pager.hidden = false;
            let current = 0;
            pager.textContent = 'Page 1 / ' + pages.length;
            if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
                timer = setInterval(() => {
                    current = (current + 1) % pages.length;
                    pages.forEach((el, i) => el.dataset.active = String(i === current));
                    pager.textContent = 'Page ' + (current + 1) + ' / ' + pages.length;
                }, 10000);
            }
        }

        /* La colonne tourne lentement : elle se lit d'un coup d'œil, entre deux
           gorgées, pas en la fixant. */
        const slides = Array.from(document.querySelectorAll('[data-slide]'));
        const dotsHost = document.getElementById('dots');

        if (slides.length) {
            const dots = slides.map(() => {
                const dot = document.createElement('span');
                dot.className = 'dot';
                dotsHost.appendChild(dot);
                return dot;
            });

            let current = 0;
            const showSlide = i => {
                current = i;
                slides.forEach((s, n) => s.dataset.active = String(n === i));
                dots.forEach((d, n) => d.setAttribute('aria-current', String(n === i)));
            };

            showSlide(0);
            dotsHost.hidden = slides.length < 2;

            if (slides.length > 1 && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
                setInterval(() => showSlide((current + 1) % slides.length), 10000);
            }
        }

        let resizeTimer = null;
        addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(layout, 200); });

        layout();
        // L'ajustement mesure du texte : sans les polices, il mesure le repli.
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(layout);
        // Et une dernière fois quand tout est arrivé — logos des sponsors compris,
        // qui poussent la colonne de droite tant qu'ils n'ont pas leur taille.
        addEventListener('load', layout);

        // Un écran casté n'a pas de clavier pour faire F5.
        setTimeout(() => location.reload(), Number(document.body.dataset.refresh) * 1000);
    </script>
</body>
</html>
