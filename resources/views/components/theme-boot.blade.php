{{--
    Décide du thème avant que la page se dessine.

    `resources/js/components/theme.js` fait le même calcul, mais il vit dans le
    bundle : il ne s'exécute qu'une fois les 371 Ko chargés et analysés. Mesuré
    sous bridage réseau, l'attribut `data-theme` arrive 131 ms après le premier
    rendu en wifi, 5,3 s en 4G lente et 10,6 s en 3G. Aujourd'hui cela ne se voit
    pas — la vitrine est blanche avant comme après — mais dès qu'elle basculera
    vraiment, ce délai deviendrait dix secondes de blanc suivies d'un
    basculement brutal, à chaque navigation.

    Ce script doit donc rester bloquant, en ligne, et placé avant la feuille de
    style. Il duplique volontairement la logique de `theme.js`, qui garde le rôle
    d'écouter les changements de réglage système en cours de session : les deux
    doivent appliquer la MÊME priorité — choix explicite, puis préférence du
    compte, puis réglage du système — sinon la page bascule deux fois.
--}}
<script>
    (function () {
        var root = document.documentElement;
        var theme = null;

        // Navigation privée et réglages qui bloquent le stockage : l'accès lui-même lève.
        try {
            theme = localStorage.getItem('theme');
        } catch (e) {
            theme = null;
        }

        if (!theme || theme === 'auto') {
            theme = root.dataset.dbTheme || null;
        }

        if (!theme || theme === 'auto') {
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        root.setAttribute('data-theme', theme);
    })();
</script>
