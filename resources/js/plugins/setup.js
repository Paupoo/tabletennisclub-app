import 'cropperjs/dist/cropper.css';
import Cropper from 'cropperjs';
import Sortable from 'sortablejs';

export function setupPlugins() {
    window.Cropper = Cropper;

    window.initSortable = (el, component) => {
        new Sortable(el, {
            group: 'shared-teams',
            animation: 200,
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