/**
 * Shrink an image file in the browser, before it is ever uploaded.
 *
 * Two callers rely on this: the avatar cropper, which needs a working-size
 * source it can pan and zoom smoothly on a phone, and the article featured
 * image, which is stored as-is and must not reach the server at phone-camera
 * resolution. Doing it here also sidesteps the PHP and reverse-proxy body
 * limits that used to make uploads fail silently on mobile.
 */

/**
 * Draw `file` onto a canvas no larger than `maxEdge` on its longest side.
 *
 * @param {File|Blob} file
 * @param {number} maxEdge
 * @returns {Promise<HTMLCanvasElement>}
 */
function drawScaled(file, maxEdge) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(file);

        image.onload = () => {
            URL.revokeObjectURL(objectUrl);

            const scale = Math.min(1, maxEdge / Math.max(image.width, image.height));
            const canvas = document.createElement("canvas");
            canvas.width = Math.round(image.width * scale);
            canvas.height = Math.round(image.height * scale);
            canvas.getContext("2d").drawImage(image, 0, 0, canvas.width, canvas.height);

            resolve(canvas);
        };

        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error("Could not read the image."));
        };

        image.src = objectUrl;
    });
}

/**
 * @param {File|Blob} file
 * @param {{maxEdge?: number, quality?: number}} options
 * @returns {Promise<string>} a JPEG data URL
 */
export async function downscaleToDataUrl(file, { maxEdge = 1400, quality = 0.92 } = {}) {
    const canvas = await drawScaled(file, maxEdge);

    return canvas.toDataURL("image/jpeg", quality);
}

/**
 * @param {File|Blob} file
 * @param {{maxEdge?: number, quality?: number}} options
 * @returns {Promise<Blob>} a JPEG blob, ready for $wire.upload()
 */
export async function downscaleToBlob(file, { maxEdge = 1600, quality = 0.85 } = {}) {
    const canvas = await drawScaled(file, maxEdge);

    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => (blob ? resolve(blob) : reject(new Error("Could not encode the image."))),
            "image/jpeg",
            quality,
        );
    });
}
