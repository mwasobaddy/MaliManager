import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect } from 'react';

type RichTextEditorProps = {
    name: string;
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
};

function ToolbarButton({
    active,
    onClick,
    label,
}: {
    active: boolean;
    onClick: () => void;
    label: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`rounded border px-2 py-1 text-xs font-medium transition-colors ${
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-input bg-background hover:bg-accent'
            }`}
        >
            {label}
        </button>
    );
}

/**
 * A minimal rich-text editor for lease agreements. Renders a hidden input
 * with the current HTML so it can drop into any classic form submission.
 * The server strips everything outside a formatting allowlist.
 */
export default function RichTextEditor({ name, value, onChange, placeholder }: RichTextEditorProps) {
    const editor = useEditor({
        extensions: [StarterKit],
        content: value || '',
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class:
                    'prose prose-sm min-h-[120px] max-w-none rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus:outline-none focus-visible:ring-1 focus-visible:ring-ring',
                'data-placeholder': placeholder ?? '',
            },
        },
        onUpdate: ({ editor: instance }) => onChange(instance.getHTML()),
    });

    useEffect(() => () => editor?.destroy(), [editor]);

    if (!editor) {
        return null;
    }

    const chain = editor.chain().focus();

    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap gap-1">
                <ToolbarButton
                    active={editor.isActive('bold')}
                    onClick={() => chain.toggleBold().run()}
                    label="B"
                />
                <ToolbarButton
                    active={editor.isActive('italic')}
                    onClick={() => chain.toggleItalic().run()}
                    label="I"
                />
                <ToolbarButton
                    active={editor.isActive('strike')}
                    onClick={() => chain.toggleStrike().run()}
                    label="S"
                />
                <ToolbarButton
                    active={editor.isActive('heading', { level: 2 })}
                    onClick={() => chain.toggleHeading({ level: 2 }).run()}
                    label="H2"
                />
                <ToolbarButton
                    active={editor.isActive('heading', { level: 3 })}
                    onClick={() => chain.toggleHeading({ level: 3 }).run()}
                    label="H3"
                />
                <ToolbarButton
                    active={editor.isActive('bulletList')}
                    onClick={() => chain.toggleBulletList().run()}
                    label="• List"
                />
                <ToolbarButton
                    active={editor.isActive('orderedList')}
                    onClick={() => chain.toggleOrderedList().run()}
                    label="1. List"
                />
                <ToolbarButton
                    active={editor.isActive('blockquote')}
                    onClick={() => chain.toggleBlockquote().run()}
                    label="Quote"
                />
            </div>

            <EditorContent editor={editor} />

            <input type="hidden" name={name} value={value} />
        </div>
    );
}
