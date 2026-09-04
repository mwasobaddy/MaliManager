import { mergeAttributes, Node } from '@tiptap/core';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        placeholder: {
            /**
             * Insert a {{token}} data placeholder at the current selection.
             * The token is rendered as a visible, styled chip in the editor and
             * serializes to `{{token}}` text so the server-side template merge
             * (LeaseAgreementTemplate) can substitute the live value.
             */
            insertPlaceholder: (token: string) => ReturnType;
        };
    }
}

/**
 * A data placeholder node used by the document/block builder. Authors pick a
 * token (e.g. occupant_name, rent_amount) from the companion picker; the node
 * renders as a highlighted `{{token}}` chip so it stands out from body text,
 * and is serialized back to plain `{{token}}` so the existing server-side
 * merge pipeline (LeaseAgreementTemplate + config/purifier.php allowlist)
 * keeps working unchanged.
 */
export const PlaceholderToken = Node.create({
    name: 'placeholder',

    group: 'inline',
    inline: true,
    atom: true,
    selectable: true,

    addAttributes() {
        return {
            token: {
                default: '',
                parseHTML: (element: HTMLElement) =>
                    element.getAttribute('data-token') ?? '',
                renderHTML: (attributes: { token?: string }) => ({
                    'data-token': attributes.token ?? '',
                }),
            },
        };
    },

    parseHTML() {
        return [
            {
                tag: 'span[data-token]',
            },
        ];
    },

    renderHTML({ node, HTMLAttributes }) {
        const token = (node.attrs.token as string) || '';

        // Render as HTML with attributes, then let the DOMContent handle styling
        // via editor.css. We keep the literal {{token}} so round-trips preserve it.
        return [
            'span',
            mergeAttributes(HTMLAttributes, {
                class: 'md-placeholder-token',
            }),
            `{{${token}}}`,
        ];
    },

    addCommands() {
        return {
            insertPlaceholder:
                (token: string) =>
                ({ commands }) =>
                    commands.insertContent({
                        type: this.name,
                        attrs: { token },
                    }),
        };
    },

    addKeyboardShortcuts() {
        return {
            // Treat as a single unit: deleting backwards removes the whole chip.
            Backspace: () => {
                const { selection } = this.editor.state;

                if (
                    selection.$from.parent.type === this.type &&
                    selection.empty
                ) {
                    return this.editor.commands.deleteSelection();
                }

                return false;
            },
        };
    },
});
