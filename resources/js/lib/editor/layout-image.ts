import Image from '@tiptap/extension-image';

export type ImageLayout = 'inline' | 'float-left' | 'float-right' | 'block';

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        imageLayout: {
            setImageLayout: (layout: ImageLayout) => ReturnType;
        };
    }
}

/**
 * Extends the built-in Image node with a `data-layout` attribute that lets
 * authors place an image inline with the text, float it left/right (text wraps
 * around it), or render it as a full-width block. The value is persisted in
 * the HTML (configuration/purifier.php allowlist must keep `data-layout`) so a
 * layout survives a round-trip to the server and back.
 */
export const LayoutImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),
            layout: {
                default: 'block',
                parseHTML: (element: HTMLElement) =>
                    element.getAttribute('data-layout') ?? 'block',
                renderHTML: (attributes: { layout?: string }) => ({
                    'data-layout': attributes.layout ?? 'block',
                }),
            },
        };
    },

    addCommands() {
        return {
            ...this.parent?.(),
            setImageLayout:
                (layout: ImageLayout) =>
                ({ chain }) =>
                    chain().updateAttributes('image', { layout }).run(),
        };
    },
});
