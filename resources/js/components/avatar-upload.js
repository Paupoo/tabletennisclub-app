import { downscaleToDataUrl } from "../utils/downscale-image";

/**
 * Mobile-first avatar cropper.
 *
 * The user picks any image — even a 10 MB phone photo — and everything is
 * handled client-side:
 *   1. the image is downscaled to a working size so the cropper stays smooth
 *      on mobile and the original is never uploaded;
 *   2. a full-screen modal offers a square crop with pinch-zoom / drag;
 *   3. only the final 512×512 JPEG (~80 KB) is uploaded to Livewire.
 *
 * This sidesteps every server body-size limit (PHP / reverse proxy) that used
 * to make uploads fail silently on phones, and keeps stored avatars tiny.
 */
export default function avatarCropper({
    model = "photo",
    output = 512,
    workingMax = 1400,
    quality = 0.85,
} = {}) {
    return {
        model,
        output,
        workingMax,
        quality,
        open: false,
        processing: false,
        error: null,
        cropper: null,

        pick() {
            if (!this.processing) {
                this.$refs.input.click();
            }
        },

        async selected(event) {
            const file = event.target.files?.[0];
            // Reset so re-picking the same file fires change again.
            event.target.value = "";

            if (!file) {
                return;
            }

            this.error = null;

            if (!file.type.startsWith("image/")) {
                this.error = this.$refs.root.dataset.invalidMessage;

                return;
            }

            this.processing = true;

            try {
                const source = await this.downscale(file);
                this.launch(source);
            } catch {
                this.error = this.$refs.root.dataset.failedMessage;
            } finally {
                this.processing = false;
            }
        },

        launch(source) {
            this.open = true;

            this.$nextTick(() => {
                const image = this.$refs.image;
                image.src = source;

                this.cropper?.destroy();
                this.cropper = new window.Cropper(image, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: "move",
                    autoCropArea: 1,
                    cropBoxMovable: false,
                    cropBoxResizable: false,
                    toggleDragModeOnDblclick: false,
                    background: false,
                    responsive: true,
                });
            });
        },

        zoom(step) {
            this.cropper?.zoom(step);
        },

        cancel() {
            this.close();
        },

        confirm() {
            if (!this.cropper || this.processing) {
                return;
            }

            this.processing = true;

            const canvas = this.cropper.getCroppedCanvas({
                width: this.output,
                height: this.output,
                imageSmoothingQuality: "high",
            });

            canvas.toBlob(
                (blob) => {
                    if (!blob) {
                        this.processing = false;
                        this.error = this.$refs.root.dataset.failedMessage;

                        return;
                    }

                    if (this.$refs.preview) {
                        this.$refs.preview.src = URL.createObjectURL(blob);
                    }

                    this.$wire.upload(
                        this.model,
                        new File([blob], "avatar.jpg", { type: "image/jpeg" }),
                        () => {
                            this.processing = false;
                            this.close();
                        },
                        () => {
                            this.processing = false;
                            this.error = this.$refs.root.dataset.failedMessage;
                        },
                    );
                },
                "image/jpeg",
                this.quality,
            );
        },

        close() {
            this.cropper?.destroy();
            this.cropper = null;
            this.open = false;
        },

        /** Downscale to `workingMax` on the longest edge and return a JPEG data URL. */
        downscale(file) {
            return downscaleToDataUrl(file, { maxEdge: this.workingMax, quality: 0.92 });
        },
    };
}
