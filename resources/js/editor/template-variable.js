import { Node } from "@tiptap/core";

/**
 * A template placeholder such as `{{first_name}}`, shown as a pill.
 *
 * It is an atom: inserted, selected and deleted in one piece, so the author
 * cannot half-break the syntax. The markdown keeps the placeholder exactly as
 * EmailTemplateRenderer expects it.
 *
 * `labels` maps a placeholder name to the words the pill shows; a name it does
 * not know still gets a pill, so a typo stays visible before sending.
 */
export const TemplateVariable = Node.create({
    name: "templateVariable",
    group: "inline",
    inline: true,
    atom: true,
    selectable: true,

    addOptions() {
        return { labels: {} };
    },

    addAttributes() {
        return {
            name: {
                default: null,
                parseHTML: (element) => element.getAttribute("data-variable"),
            },
        };
    },

    parseHTML() {
        return [{ tag: "span[data-variable]" }];
    },

    renderHTML({ node }) {
        return [
            "span",
            {
                "data-variable": node.attrs.name,
                class: "badge badge-soft badge-primary badge-sm mx-0.5 align-baseline not-prose",
            },
            this.options.labels[node.attrs.name] ?? node.attrs.name,
        ];
    },

    markdownTokenizer: {
        name: "templateVariable",
        level: "inline",
        start: (src) => src.indexOf("{{"),
        tokenize(src) {
            const match = /^\{\{\s*(\w+)\s*\}\}/.exec(src);

            if (!match) {
                return undefined;
            }

            return { type: "templateVariable", raw: match[0], name: match[1] };
        },
    },

    parseMarkdown: (token, helpers) => helpers.createNode("templateVariable", { name: token.name }),

    renderMarkdown: (node) => `{{${node.attrs.name}}}`,

    addCommands() {
        return {
            insertTemplateVariable:
                (name) =>
                ({ commands }) =>
                    commands.insertContent({ type: this.name, attrs: { name } }),
        };
    },
});
