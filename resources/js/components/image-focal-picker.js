import { downscaleToBlob } from "../utils/downscale-image";

/**
 * Pick a featured image and say which part of it must stay in frame.
 *
 * The image is rendered in four different container ratios (a 2.83:1 hero on
 * desktop down to a near-square 1.24:1 one on a phone, plus 16:9 cards), so no
 * single crop can serve them all. The author therefore places a point rather
 * than a frame, and `object-cover` keeps that point visible everywhere.
 *
 * The file itself is downscaled here, before upload: the originals are phone
 * photos, and full-resolution ones both bloat the public pages and trip the
 * server body-size limits.
 */
export default function imageFocalPicker({
    model = "image",
    focalXProperty = "imageFocalX",
    focalYProperty = "imageFocalY",
    x = 50,
    y = 50,
    maxEdge = 1600,
} = {}) {
    return {
        model,
        focalXProperty,
        focalYProperty,
        maxEdge,
        x,
        y,
        dragging: false,
        processing: false,
        error: null,
        previewUrl: null,

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
                const blob = await downscaleToBlob(file, { maxEdge: this.maxEdge });

                this.previewUrl = URL.createObjectURL(blob);

                this.$wire.upload(
                    this.model,
                    new File([blob], "article.jpg", { type: "image/jpeg" }),
                    () => {
                        this.processing = false;
                    },
                    () => {
                        this.processing = false;
                        this.error = this.$refs.root.dataset.failedMessage;
                    },
                );
            } catch {
                this.processing = false;
                this.error = this.$refs.root.dataset.failedMessage;
            }
        },

        start(event) {
            this.dragging = true;
            // `pointerdown.prevent` stops the browser focusing the box on its
            // own, which would leave the arrow keys dead after a click.
            this.$refs.canvas.focus();
            this.$refs.canvas.setPointerCapture?.(event.pointerId);
            this.aim(event);
        },

        drag(event) {
            if (this.dragging) {
                this.aim(event);
            }
        },

        stop() {
            if (this.dragging) {
                this.dragging = false;
                this.commit();
            }
        },

        /** Place the point where the pointer is, in percentages of the image. */
        aim(event) {
            const box = this.$refs.canvas.getBoundingClientRect();

            this.move(
                ((event.clientX - box.left) / box.width) * 100,
                ((event.clientY - box.top) / box.height) * 100,
            );
        },

        nudge(dx, dy) {
            this.move(this.x + dx, this.y + dy);
            this.commit();
        },

        move(x, y) {
            this.x = Math.round(Math.min(100, Math.max(0, x)));
            this.y = Math.round(Math.min(100, Math.max(0, y)));
        },

        /**
         * Hand the point to Livewire once the gesture is over — pushing on every
         * pointer move would be one round-trip per pixel.
         */
        commit() {
            this.$wire.set(this.focalXProperty, this.x);
            this.$wire.set(this.focalYProperty, this.y);
        },

        get position() {
            return `${this.x}% ${this.y}%`;
        },
    };
}
