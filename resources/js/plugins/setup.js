import 'cropperjs/dist/cropper.css';
import Cropper from 'cropperjs';
import Sortable from 'sortablejs';

export function setupPlugins() {
    window.Cropper = Cropper;

    window.initSortable = (el, component) => {
        new Sortable(el, {
            group: 'shared-teams',
            animation: 200,
            // Sans poignée, tout glissement vertical au doigt déplaçait un joueur
            // au lieu de faire défiler la page : les poules étaient inatteignables
            // sur téléphone. La poignée existait déjà dans le balisage, elle
            // n'était simplement pas déclarée ici.
            handle: '[data-drag-handle]',
            // Au doigt seulement : une pression courte reste un défilement, un
            // appui maintenu démarre le déplacement. La souris n'attend pas.
            delayOnTouchOnly: true,
            delay: 150,
            touchStartThreshold: 5,
            onEnd: () => {
                let structure = [];
                document.querySelectorAll('[data-team-id]').forEach(zone => {
                    structure.push({
                        teamId: zone.dataset.teamId,
                        memberIds: Array.from(zone.children).map(m => m.dataset.id)
                    });
                });
                component.updateStructure(structure);
            }
        });
    }
}