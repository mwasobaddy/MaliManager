import type { Editor } from '@tiptap/react';
import {
    Braces,
    Heading1,
    Heading2,
    Minus,
    Pilcrow,
    Table2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import RichTextEditor from '@/components/rich-text-editor';
import type {TemplateToken} from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type DocumentBuilderProps = {
    name: string;
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    availableTokens?: TemplateToken[];
};

type PaletteEntry = {
    label: string;
    description: string;
    icon: React.ReactNode;
    action: (editor: Editor) => void;
};

/**
 * A guided, block-first editor wrapper around the Tiptap engine. Instead of a
 * free-form toolbar, authors build documents from a curated set of "blocks"
 * (title, sub-heading, clause, section break, table, data fields), which maps
 * to clean, repeatable, on-brand output. The underlying RichTextEditor keeps
 * the full Tiptap engine (markdown-style formatting, image upload/crop, table
 * editing) for fine-grained control within a block, so nothing is lost.
 */
export default function DocumentBuilder({
    name,
    value,
    onChange,
    placeholder,
    availableTokens,
}: DocumentBuilderProps) {
    const [editor, setEditor] = useState<Editor | null>(null);

    const blocks: PaletteEntry[] = useMemo(
        () => [
            {
                label: 'Title',
                description: 'Document title (Heading 1)',
                icon: <Heading1 className="size-4" />,
                action: (e) =>
                    e.chain().focus().toggleHeading({ level: 1 }).run(),
            },
            {
                label: 'Sub-heading',
                description: 'Section heading (Heading 2)',
                icon: <Heading2 className="size-4" />,
                action: (e) =>
                    e.chain().focus().toggleHeading({ level: 2 }).run(),
            },
            {
                label: 'Clause',
                description: 'Body paragraph',
                icon: <Pilcrow className="size-4" />,
                action: (e) => e.chain().focus().setParagraph().run(),
            },
            {
                label: 'Section break',
                description: 'Horizontal divider',
                icon: <Minus className="size-4" />,
                action: (e) => e.chain().focus().setHorizontalRule().run(),
            },
            {
                label: 'Table',
                description: 'Insert a 2x2 table',
                icon: <Table2 className="size-4" />,
                action: (e) =>
                    e
                        .chain()
                        .focus()
                        .insertTable({ rows: 2, cols: 2 })
                        .run(),
            },
            {
                label: 'Data field',
                description: 'Insert a {{placeholder}}',
                icon: <Braces className="size-4" />,
                action: () => {
                    /* handled by the dedicated token picker below */
                },
            },
        ],
        [],
    );

    const dataFieldBlock = blocks.find((b) => b.label === 'Data field');
    const palette = blocks.filter((b) => b.label !== 'Data field');

    return (
        <div className="grid gap-2">
            <div className="flex flex-wrap items-center gap-1 rounded-md border border-input bg-background p-1.5">
                {palette.map((block) => (
                    <Button
                        key={block.label}
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="flex h-9 items-center gap-1.5 rounded-md px-2 text-xs font-medium"
                        onClick={() => editor && block.action(editor)}
                        title={block.description}
                    >
                        {block.icon}
                        {block.label}
                    </Button>
                ))}

                {dataFieldBlock && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="flex h-9 items-center gap-1.5 rounded-md border border-dashed border-brand-accent px-2 text-xs font-medium text-brand-accent"
                            >
                                {dataFieldBlock.icon}
                                {dataFieldBlock.label}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            align="start"
                            className="max-h-80 w-64 overflow-auto"
                        >
                            <DropdownMenuLabel>Insert data field</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {availableTokens && availableTokens.length > 0 ? (
                                availableTokens.map((entry) => (
                                    <DropdownMenuItem
                                        key={entry.token}
                                        onSelect={() => {
                                            editor
                                                ?.chain()
                                                .focus()
                                                .insertPlaceholder(entry.token)
                                                .run();
                                        }}
                                    >
                                        <div className="flex flex-col">
                                            <span className="font-mono text-xs">
                                                {`{{${entry.token}}}`}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {entry.description}
                                            </span>
                                        </div>
                                    </DropdownMenuItem>
                                ))
                            ) : (
                                <DropdownMenuItem disabled>
                                    No data fields available
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}
            </div>

            <RichTextEditor
                name={name}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                availableTokens={availableTokens}
                onEditorReady={setEditor}
            />
        </div>
    );
}

