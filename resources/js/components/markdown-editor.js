import { downscaleToBlob } from "../utils/downscale-image";

/**
 * Alpine side of <x-markdown-editor>: toolbar state, links, images, and the
 * bridge to the Livewire property that holds the markdown.
 *
 * The editor instance lives in this closure, never in Alpine's reactive data:
 * wrapping a ProseMirror view in a Proxy breaks it. `revision` is bumped on
 * every transaction so the toolbar's active states recompute.
 *
 * The property is written client-side (`$wire.set(…, false)`), without a
 * request per keystroke; it reaches the server with the next action, save
 * included.
 */
export default function markdownEditor({
    model = "content",
    imageModel = null,
    imageAction = null,
    label = "",
    variables = null,
    maxEdge = 1600,
} = {}) {
    let editor = null;

    return {
        ready: false,
        revision: 0,
        panel: null,
        linkUrl: "",
        imageAlt: "",
        pendingImage: null,
        uploading: false,
        error: null,

        async init() {
            const { createMarkdownEditor } = await import("../editor/tiptap");

            let written = this.$wire.get(model) ?? "";

            editor = createMarkdownEditor(this.$refs.surface, {
                markdown: written,
                label,
                variables,
                onChange: (markdown) => {
                    written = markdown;
                    this.$wire.set(model, markdown, false);
                },
                onTransaction: () => this.revision++,
                onImageFile: (file) => this.askAlt(file),
            });

            // The surface is wire:ignore'd, so a value the server sets (a block
            // inserted, a past message reused, a form reset) has to be pushed in.
            this.$wire.$watch(model, (value) => {
                if ((value ?? "") !== written) {
                    written = value ?? "";
                    editor?.commands.setContent(written, { contentType: "markdown", emitUpdate: false });
                }
            });

            this.ready = true;
        },

        destroy() {
            editor?.destroy();
            editor = null;
        },

        isActive(name, attributes = {}) {
            this.revision;

            return editor?.isActive(name, attributes) ?? false;
        },

        can(command) {
            this.revision;

            return editor?.can()[command]() ?? false;
        },

        run(command, ...args) {
            editor?.chain().focus()[command](...args).run();
        },

        heading(level) {
            this.run("toggleHeading", { level });
        },

        insertVariable(name) {
            this.run("insertTemplateVariable", name);
        },

        // ── Links ─────────────────────────────────────────────────────────

        openLink() {
            this.linkUrl = editor?.getAttributes("link").href ?? "";
            this.panel = "link";
            this.$nextTick(() => this.$refs.linkInput?.focus());
        },

        applyLink() {
            const url = this.linkUrl.trim();
            const chain = editor.chain().focus().extendMarkRange("link");

            if (url === "") {
                chain.unsetLink().run();
            } else if (editor.state.selection.empty && !editor.isActive("link")) {
                chain.insertContent({ type: "text", text: url, marks: [{ type: "link", attrs: { href: url } }] }).run();
            } else {
                chain.setLink({ href: url }).run();
            }

            this.closePanel();
        },

        // ── Images ────────────────────────────────────────────────────────

        pickImage() {
            this.$refs.imageInput.click();
        },

        imageChosen(event) {
            const file = event.target.files?.[0];
            // Reset so re-picking the same file fires change again.
            event.target.value = "";

            if (file) {
                this.askAlt(file);
            }
        },

        askAlt(file) {
            if (!imageModel || !imageAction) {
                return;
            }

            this.error = null;

            if (!file.type.startsWith("image/")) {
                this.error = this.$root.dataset.invalidImage;

                return;
            }

            this.pendingImage = file;
            this.imageAlt = "";
            this.panel = "image";
            this.$nextTick(() => this.$refs.altInput?.focus());
        },

        async insertImage() {
            const file = this.pendingImage;
            const alt = this.imageAlt.trim();

            if (!file || alt === "") {
                return;
            }

            this.closePanel();
            this.uploading = true;

            try {
                const blob = await downscaleToBlob(file, { maxEdge });

                await new Promise((resolve, reject) => {
                    this.$wire.upload(imageModel, new File([blob], "image.jpg", { type: "image/jpeg" }), resolve, reject);
                });

                const src = await this.$wire.call(imageAction);

                if (!src) {
                    throw new Error("upload refused");
                }

                editor.chain().focus().setImage({ src, alt }).run();
            } catch {
                this.error = this.$root.dataset.failedImage;
            } finally {
                this.uploading = false;
            }
        },

        closePanel() {
            this.panel = null;
            this.pendingImage = null;
            editor?.commands.focus();
        },
    };
}
