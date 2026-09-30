import { Editor } from "@tiptap/core";
import { Selection } from "@tiptap/pm/state";
import StarterKit from "@tiptap/starter-kit";
import Image from "@tiptap/extension-image";
import { TableKit } from "@tiptap/extension-table";
import { Markdown } from "@tiptap/markdown";
import { TemplateVariable } from "./template-variable";

/**
 * The Tiptap engine behind <x-markdown-editor>, in a chunk of its own.
 *
 * It is only ever reached through a dynamic import(), so Vite splits it out
 * and a page without an editor never downloads it.
 *
 * Markdown stays the stored format: the editor reads it, the author never
 * sees it, and getMarkdown() writes it back for Markdown::safe() to render.
 *
 * - Every heading level stays in the schema, although the toolbar only offers
 *   H2 and H3: an article written with `#` must survive being re-saved.
 * - Tables have no button, but their extension is loaded for the same reason.
 * - Underline is off: markdown cannot say it.
 * - `variables` (name → label) turns `{{name}}` placeholders into pills.
 */
export function createMarkdownEditor(
    element,
    { markdown, label, variables = null, editable = true, onChange, onTransaction, onImageFile, onBlur = () => {} },
) {
    const imageFrom = (items) =>
        [...(items ?? [])].find((file) => file.type?.startsWith("image/")) ?? null;

    return new Editor({
        element,
        extensions: [
            StarterKit.configure({
                underline: false,
                link: {
                    openOnClick: false,
                    autolink: true,
                    defaultProtocol: "https",
                },
            }),
            Image,
            TableKit.configure({ table: { resizable: false } }),
            ...(variables ? [TemplateVariable.configure({ labels: variables })] : []),
            Markdown,
        ],
        content: markdown,
        contentType: "markdown",
        editable,
        editorProps: {
            attributes: {
                class: "markdown-editor-surface",
                role: "textbox",
                "aria-multiline": "true",
                "aria-label": label,
            },
            handlePaste(view, event) {
                const file = imageFrom(event.clipboardData?.files);

                if (!file) {
                    return false;
                }

                onImageFile(file);

                return true;
            },
            handleDrop(view, event) {
                const file = imageFrom(event.dataTransfer?.files);

                if (!file) {
                    return false;
                }

                // Insert where the image was dropped, not where the caret was.
                const target = view.posAtCoords({ left: event.clientX, top: event.clientY });

                if (target) {
                    view.dispatch(view.state.tr.setSelection(Selection.near(view.state.doc.resolve(target.pos))));
                }

                event.preventDefault();
                onImageFile(file);

                return true;
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getMarkdown()),
        onTransaction: () => onTransaction(),
        onBlur: () => onBlur(),
    });
}
