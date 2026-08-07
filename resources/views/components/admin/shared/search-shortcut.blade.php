{{--
    Ctrl+K — ⌘K sur Mac — amène le curseur dans la recherche de la page.

    Dix-neuf écrans du back-office portent une recherche, toujours dans
    l'en-tête. Le raccourci évite l'aller-retour à la souris depuis la liste
    qu'on est en train de lire, et c'est celui que tout le monde essaie en
    premier : Slack, Linear et GitHub l'ont installé dans les doigts.

    Posé une seule fois dans le gabarit : aucun écran n'a à le déclarer, et un
    écran sans recherche rend la touche au navigateur au lieu de l'avaler.
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

        /**
         * Nomme le raccourci sur le champ lui-même : c'est là qu'un lecteur
         * d'écran va le chercher, et l'attribut ARIA prévu pour ça.
         */
        announce() {
            const field = this.field()
                ?? document.querySelector('[data-search-field]');

            field?.setAttribute('aria-keyshortcuts', 'Control+K');
        },

        press(event) {
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
                    const opened = this.field();
                    opened?.setAttribute('aria-keyshortcuts', 'Control+K');
                    opened?.focus();
                });

                return;
            }

            {{-- Le navigateur réserve Ctrl+K à sa barre d'adresse. --}}
            event.preventDefault();

            field.setAttribute('aria-keyshortcuts', 'Control+K');
            field.focus();
            field.select?.();
        },
    }"
    x-init="
        announce();
        document.addEventListener('livewire:navigated', () => announce());
        document.addEventListener('livewire:init', () => Livewire.hook('morph.updated', () => announce()));
    "
    @keydown.window="press($event)"
></div>
