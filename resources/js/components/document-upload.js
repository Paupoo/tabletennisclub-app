import { downscaleToBlob } from "../utils/downscale-image";

/**
 * Alpine side of <x-document-upload>: shrinks phone photos before the file
 * input inside it hands them to Livewire.
 *
 * A photo straight from a phone weighs 3 to 8 MB, and PHP refuses anything over
 * its upload limit before Laravel sees it ("the upload failed"). The listener
 * runs in the capture phase, so it sees the change before Livewire's own
 * wire:model handler: it swaps the heavy photos for downscaled JPEGs, then
 * fires the change again and lets Livewire upload as usual — progress bar,
 * events and multiple-file merge included. A PDF, or an image already light,
 * goes through untouched.
 */

const SHRINKABLE_TYPES = ["image/jpeg", "image/png", "image/webp"];

/** Under this size an image is sent as it is: a screenshot is not degraded for nothing. */
const SHRINK_ABOVE_BYTES = 1024 * 1024;

/**
 * @param {File} file
 * @returns {boolean}
 */
function needsShrinking(file) {
    return SHRINKABLE_TYPES.includes(file.type) && file.size > SHRINK_ABOVE_BYTES;
}

/**
 * @param {File} file
 * @param {number} maxEdge
 * @returns {Promise<File>} the downscaled JPEG, or the original when the browser cannot read it
 */
async function shrink(file, maxEdge) {
    try {
        const blob = await downscaleToBlob(file, { maxEdge });
        const name = file.name.replace(/\.[^.]+$/, "") + ".jpg";

        return new File([blob], name, { type: "image/jpeg", lastModified: file.lastModified });
    } catch {
        return file;
    }
}

export default ({ maxEdge = 2400 } = {}) => ({
    shrinking: false,

    /** @param {Event} event */
    async intercept(event) {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || input.type !== "file") {
            return;
        }

        // The change fired again below, with the shrunk files: Livewire's turn.
        if (input.dataset.shrunk === "1") {
            delete input.dataset.shrunk;

            return;
        }

        const files = Array.from(input.files ?? []);

        if (!files.some(needsShrinking)) {
            return;
        }

        // Livewire must not upload the originals.
        event.stopPropagation();

        this.shrinking = true;

        const shrunk = await Promise.all(files.map((file) => (needsShrinking(file) ? shrink(file, maxEdge) : file)));

        this.shrinking = false;

        const transfer = new DataTransfer();
        shrunk.forEach((file) => transfer.items.add(file));

        input.files = transfer.files;
        input.dataset.shrunk = "1";
        input.dispatchEvent(new Event("change", { bubbles: true }));
    },
});
