{{--
    Ctrl+K — ⌘K sur Mac — amène le curseur dans la recherche de la page.
    Échap l'efface.

    Dix-neuf écrans du back-office portent une recherche, toujours dans
    l'en-tête. Le raccourci évite l'aller-retour à la souris depuis la liste
    qu'on est en train de lire, et c'est celui que tout le monde essaie en
    premier : Slack, Linear et GitHub l'ont installé dans les doigts.

    Posé une seule fois dans le gabarit : aucun écran n'a à le déclarer, et un
    écran sans recherche rend la touche au navigateur au lieu de l'avaler.

    L'écoute est en phase de capture, donc avant les `@keydown.escape.window`
    des tiroirs et des menus : c'est ce qui permet à Échap d'effacer la
    recherche sans refermer le panneau qui la contient.
--}}
<div
    x-data="{
        /** Le champ qui pilote la recherche, s'il est visible en ce moment. */
        field() {
            return [...document.querySelectorAll('input')].find((input) => {
                const bound = [...input.attributes]
                    .some((attribute) => attribute.name.startsWith('wire:model') && attribute.value === 'search');

                return bound && input.getClientRects().length > 0;
            }) ?? null;
        },

        /** ⌘ sur un clavier Apple, Ctrl partout ailleurs. */
        isApple() {
            const platform = navigator.userAgentData?.platform ?? navigator.platform ?? '';

            return /mac|iphone|ipad|ipod/i.test(platform);
        },

        /**
         * Nomme le raccourci sur le champ — `aria-keyshortcuts` est l'attribut
         * prévu pour ça — puis pose le badge qui le rend visible.
         *
         * Rejoué après chaque navigation et chaque morph Livewire : sans ça
         * l'attribut disparaît au premier rafraîchissement de la liste.
         */
        decorate() {
            const field = this.field();

            if (! field) {
                return;
            }

            field.setAttribute('aria-keyshortcuts', 'Control+K');

            {{-- Mary rend le `suffix` en dernier enfant du <label class='input'>.
                 Hors de ce gabarit — le champ brut du panneau téléphone — il n'y
                 a pas de place pour un badge, et aucun clavier pour le lire. --}}
            const shell = field.closest('label.input');

            if (! shell || shell.querySelector('[data-search-hint]')) {
                return;
            }

            {{-- Le champ ne grandit pas (`flex: 0 1 auto`) : il occupe 202 px
                 d'une coque de 341, badge ou pas. Celui-ci se pose donc dans un
                 vide qui existait déjà, sans rien coûter à la saisie. --}}
            const hint = document.createElement('kbd');
            hint.setAttribute('data-search-hint', '');
            hint.setAttribute('aria-hidden', 'true');
            hint.className = 'kbd kbd-sm pointer-events-none opacity-60';
            hint.textContent = this.isApple() ? '⌘ K' : 'Ctrl K';

            shell.append(hint);
        },

        press(event) {
            if (event.key === 'Escape') {
                return this.clear(event);
            }

            if (event.key?.toLowerCase() !== 'k' || ! (event.ctrlKey || event.metaKey) || event.altKey) {
                return;
            }

            let field = this.field();

            {{-- Sur téléphone la recherche vit derrière une loupe : l'ouvrir d'abord. --}}
            if (! field) {
                const toggle = document.querySelector('[data-search-toggle]');

                if (! toggle || toggle.getClientRects().length === 0) {
                    return; {{-- Rien à atteindre : la touche reste au navigateur. --}}
                }

                event.preventDefault();
                toggle.click();

                this.$nextTick(() => {
                    this.decorate();
                    this.field()?.focus();
                });

                return;
            }

            {{-- Le navigateur réserve Ctrl+K à sa barre d'adresse. --}}
            event.preventDefault();

            this.decorate();
            field.focus();
            field.select?.();
        },

        /**
         * Échap efface la recherche et laisse le curseur en place : on efface
         * pour retaper, pas pour partir.
         *
         * Sur un champ déjà vide il n'y a rien à effacer — la touche repart, et
         * c'est le tiroir ou le menu qui se referme. Un Échap efface, le second
         * referme.
         */
        clear(event) {
            const field = event.target;

            if (field !== this.field() || field.value === '') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            field.value = '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }"
    x-init="
        decorate();
        document.addEventListener('livewire:navigated', () => decorate());
        document.addEventListener('livewire:init', () => Livewire.hook('morph.updated', () => decorate()));
    "
    @keydown.window.capture="press($event)"
></div>
