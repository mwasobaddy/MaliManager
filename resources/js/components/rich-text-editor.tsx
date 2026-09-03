import Color from '@tiptap/extension-color';
import FontFamily from '@tiptap/extension-font-family';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { Table } from '@tiptap/extension-table';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TableRow from '@tiptap/extension-table-row';
import TextAlign from '@tiptap/extension-text-align';
import {
    TextStyle,
    BackgroundColor,
    FontSize,
} from '@tiptap/extension-text-style';
import Underline from '@tiptap/extension-underline';
import { EditorContent, useEditor, useEditorState } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Bold,
    Crop,
    Eraser,
    Highlighter,
    ImagePlus,
    Italic,
    Link2,
    List,
    ListOrdered,
    Minus,
    Pilcrow,
    Redo2,
    RotateCcw,
    Strikethrough,
    Subscript as SubscriptIcon,
    Superscript as SuperscriptIcon,
    Table2,
    Underline as UnderlineIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn, sameOriginStorageUrl } from '@/lib/utils';

import '../../css/editor.css';
import ImageCropDialog from './image-crop-dialog';

type RichTextEditorProps = {
    name: string;
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
};

const FONT_FAMILIES: { label: string; value: string }[] = [
    { label: 'Default', value: '' },
    { label: 'Sans Serif', value: 'ui-sans-serif, system-ui, sans-serif' },
    { label: 'Serif', value: 'Georgia, "Times New Roman", serif' },
    { label: 'Monospace', value: 'ui-monospace, SFMono-Regular, monospace' },
    { label: 'Arial', value: 'Arial, Helvetica, sans-serif' },
    { label: 'Times New Roman', value: '"Times New Roman", Times, serif' },
    { label: 'Courier New', value: '"Courier New", Courier, monospace' },
];

const FONT_SIZES: { label: string; value: string }[] = [
    { label: 'Default', value: '' },
    { label: 'Small (12px)', value: '12px' },
    { label: 'Normal (16px)', value: '16px' },
    { label: 'Large (20px)', value: '20px' },
    { label: 'Large (24px)', value: '24px' },
    { label: 'Heading (32px)', value: '32px' },
];

const HEADING_LEVELS: { label: string; level: 1 | 2 | 3 | 4 | null }[] = [
    { label: 'Paragraph', level: null },
    { label: 'Heading 1', level: 1 },
    { label: 'Heading 2', level: 2 },
    { label: 'Heading 3', level: 3 },
    { label: 'Heading 4', level: 4 },
];

const TEXT_COLORS = [
    '#A85A36',
    '#0E7C7E',
    '#2B6CB0',
    '#C53030',
    '#276749',
    '#6B635A',
    '#000000',
];

const HIGHLIGHT_COLORS = [
    '#FDE68A',
    '#BBF7D0',
    '#BFDBFE',
    '#FECACA',
    '#F3C6E3',
    '#C7D2FE',
];

type EditorToolbarState = {
    fontFamily: string;
    fontSize: string;
    headingLabel: string;
    isBold: boolean;
    isItalic: boolean;
    isUnderline: boolean;
    isStrike: boolean;
    isSuperscript: boolean;
    isSubscript: boolean;
    canUndo: boolean;
    canRedo: boolean;
    textColor: string;
    backgroundColor: string;
    alignLeft: boolean;
    alignCenter: boolean;
    alignRight: boolean;
    alignJustify: boolean;
    isBulletList: boolean;
    isOrderedList: boolean;
    isBlockquote: boolean;
    isLink: boolean;
    isTable: boolean;
    isImage: boolean;
};

const DEFAULT_TOOLBAR_STATE: EditorToolbarState = {
    fontFamily: '',
    fontSize: '',
    headingLabel: 'Paragraph',
    isBold: false,
    isItalic: false,
    isUnderline: false,
    isStrike: false,
    isSuperscript: false,
    isSubscript: false,
    canUndo: false,
    canRedo: false,
    textColor: '',
    backgroundColor: '',
    alignLeft: false,
    alignCenter: false,
    alignRight: false,
    alignJustify: false,
    isBulletList: false,
    isOrderedList: false,
    isBlockquote: false,
    isLink: false,
    isTable: false,
    isImage: false,
};

function ToolbarButton({
    onClick,
    active,
    icon,
    label,
    disabled,
    title,
}: {
    onClick: () => void;
    active?: boolean;
    icon: React.ReactNode;
    label?: string;
    disabled?: boolean;
    title?: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className={cn(
                        'h-8 w-8 rounded-md',
                        active && 'bg-accent text-accent-foreground',
                    )}
                    disabled={disabled}
                    onClick={onClick}
                    aria-label={title ?? label ?? 'Formatting button'}
                >
                    {icon}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{title ?? label ?? ''}</TooltipContent>
        </Tooltip>
    );
}

/**
 * A Word-like rich-text editor for lease agreements and templates. Supports
 * font family/size/color/highlight, classic text formatting, alignment,
 * lists, tables, links, horizontal rules, and image insert/drag/paste.
 * Pasted or inserted images are uploaded to the tenant's storage via
 * POST /editor-images and embedded by URL, so no base64 bloat is stored.
 *
 * Renders a hidden input with the current HTML so it can drop into any
 * classic form submission. The server sanitizes the HTML to a safe
 * allowlist on save (config/purifier.php "rich_text" profile).
 */
export default function RichTextEditor({
    name,
    value,
    onChange,
    placeholder,
}: RichTextEditorProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);
    const [cropTarget, setCropTarget] = useState<{
        src: string;
        alt: string;
    } | null>(null);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [1, 2, 3, 4] },
            }),
            Underline,
            TextStyle,
            Color,
            BackgroundColor,
            FontSize,
            FontFamily,
            TextAlign.configure({ types: ['heading', 'paragraph'] }),
            Image.configure({
                inline: false,
                allowBase64: false,
                resize: { enabled: true, minWidth: 50, minHeight: 50 },
            }),
            Link.configure({
                openOnClick: false,
                autolink: true,
                linkOnPaste: true,
                HTMLAttributes: {
                    rel: 'noopener noreferrer',
                    target: '_blank',
                },
            }),
            Superscript,
            Subscript,
            Table.configure({
                resizable: true,
                allowTableNodeSelection: true,
            }),
            TableRow,
            TableHeader,
            TableCell,
            Placeholder.configure({ placeholder: placeholder ?? '' }),
        ],
        content: value || '',
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'prose prose-sm max-w-none focus:outline-none',
            },
            handlePaste: uploadClipboardFiles,
            handleDrop: uploadDropFiles,
        },
        onUpdate: ({ editor: instance }) => onChange(instance.getHTML()),
    });

    const toolbar = useEditorState({
        editor,
        selector: ({ editor: e }) => {
            if (!e) {
                return DEFAULT_TOOLBAR_STATE;
            }

            const textStyle = e.getAttributes('textStyle') as Record<
                string,
                unknown
            >;
            const headingLevel = e.getAttributes('heading').level as
                number | undefined;

            return {
                fontFamily: (textStyle.fontFamily as string) ?? '',
                fontSize: (textStyle.fontSize as string) ?? '',
                headingLabel: headingLevel
                    ? `Heading ${headingLevel}`
                    : 'Paragraph',
                isBold: e.isActive('bold'),
                isItalic: e.isActive('italic'),
                isUnderline: e.isActive('underline'),
                isStrike: e.isActive('strike'),
                isSuperscript: e.isActive('superscript'),
                isSubscript: e.isActive('subscript'),
                canUndo: e.can().undo(),
                canRedo: e.can().redo(),
                textColor: (textStyle.color as string) ?? '',
                backgroundColor: (textStyle.backgroundColor as string) ?? '',
                alignLeft: e.isActive({ textAlign: 'left' }),
                alignCenter: e.isActive({ textAlign: 'center' }),
                alignRight: e.isActive({ textAlign: 'right' }),
                alignJustify: e.isActive({ textAlign: 'justify' }),
                isBulletList: e.isActive('bulletList'),
                isOrderedList: e.isActive('orderedList'),
                isBlockquote: e.isActive('blockquote'),
                isLink: e.isActive('link'),
                isTable: e.isActive('table'),
                isImage: e.isActive('image'),
            };
        },
    }) as EditorToolbarState;

    useEffect(() => {
        if (editor && value !== editor.getHTML()) {
            editor.commands.setContent(value || '');
        }
    }, [value, editor]);

    useEffect(() => () => editor?.destroy(), [editor]);

    function uploadClipboardFiles(_view: unknown, event: ClipboardEvent) {
        const files = Array.from(event.clipboardData?.files ?? []).filter((f) =>
            f.type.startsWith('image/'),
        );

        if (files.length === 0) {
            return false;
        }

        event.preventDefault();
        insertImages(files);

        return true;
    }

    function uploadDropFiles(
        _view: unknown,
        event: DragEvent,
        _slice: unknown,
        moved: boolean,
    ) {
        if (moved) {
            return false;
        }

        const files = Array.from(event.dataTransfer?.files ?? []).filter((f) =>
            f.type.startsWith('image/'),
        );

        if (files.length === 0) {
            return false;
        }

        event.preventDefault();
        insertImages(files);

        return true;
    }

    async function uploadImage(file: File): Promise<string> {
        const formData = new FormData();
        formData.append('image', file);

        const response = await fetch('/editor-images', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: formData,
        });

        if (!response.ok) {
            throw new Error('Image upload failed');
        }

        const data = await response.json();

        return data.url;
    }

    async function insertImages(files: File[]) {
        if (!editor || files.length === 0) {
            return;
        }

        setUploading(true);

        try {
            for (const file of files) {
                const url = await uploadImage(file);
                editor
                    .chain()
                    .focus()
                    .setImage({ src: url, alt: file.name })
                    .run();
            }
        } catch (error) {
            console.error('Failed to upload image', error);
        } finally {
            setUploading(false);
        }
    }

    function openFilePicker() {
        fileInputRef.current?.click();
    }

    function handleFileSelected(event: React.ChangeEvent<HTMLInputElement>) {
        const files = Array.from(event.target.files ?? []).filter((f) =>
            f.type.startsWith('image/'),
        );

        if (files.length > 0) {
            insertImages(files);
        }

        event.target.value = '';
    }

    function startCrop() {
        if (!editor) {
            return;
        }

        const attrs = editor.getAttributes('image') as {
            src?: string;
            alt?: string;
        };

        if (!attrs.src) {
            return;
        }

        // The inserted image lives on the central domain (/storage/...), which
        // is cross-origin from a tenant subdomain. Rewrite it to the current
        // host so the crop canvas can read the pixels without CORS.
        setCropTarget({ src: sameOriginStorageUrl(attrs.src), alt: attrs.alt ?? '' });
    }

    async function applyCroppedImage(file: File) {
        if (!editor) {
            return;
        }

        try {
            const url = await uploadImage(file);

            editor
                .chain()
                .focus()
                .updateAttributes('image', {
                    src: url,
                    alt: file.name,
                    width: null,
                    height: null,
                })
                .run();
        } catch (error) {
            console.error('Failed to upload cropped image', error);
        } finally {
            setCropTarget(null);
        }
    }

    function setLink() {
        if (!editor) {
            return;
        }

        const previous = editor.getAttributes('link');
        const prevUrl = (previous.href as string) ?? '';
        const url = window.prompt('Link URL', prevUrl);

        if (url === null) {
            return;
        }

        if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
    }

    const tableMenu = useMemo(() => {
        return {
            insertTable: () =>
                editor
                    ?.chain()
                    .focus()
                    .insertTable({ rows: 2, cols: 2, withHeaderRow: true })
                    .run(),
            addRow: () => editor?.chain().focus().addRowAfter().run(),
            addCol: () => editor?.chain().focus().addColumnAfter().run(),
            deleteRow: () => editor?.chain().focus().deleteRow().run(),
            deleteCol: () => editor?.chain().focus().deleteColumn().run(),
            merge: () => editor?.chain().focus().mergeCells().run(),
            split: () => editor?.chain().focus().splitCell().run(),
            deleteTable: () => editor?.chain().focus().deleteTable().run(),
        };
    }, [editor]);

    if (!editor) {
        return null;
    }

    const chain = editor.chain().focus();

    function keepFocus(event: { preventDefault: () => void }) {
        event.preventDefault();
        editor?.commands.focus();
    }

    function refocusAfterClose() {
        requestAnimationFrame(() => editor?.commands.focus());
    }

    return (
        <div className="md-editor grid gap-2">
            <div className="flex flex-wrap items-center gap-1 rounded-md border border-input bg-background p-1">
                {/* Undo / Redo */}
                <ToolbarButton
                    disabled={!toolbar.canUndo}
                    onClick={() => chain.undo().run()}
                    icon={<RotateCcw className="size-4" />}
                    title="Undo (Ctrl+Z)"
                />
                <ToolbarButton
                    disabled={!toolbar.canRedo}
                    onClick={() => chain.redo().run()}
                    icon={<Redo2 className="size-4" />}
                    title="Redo (Ctrl+Y)"
                />

                <div className="mx-1 h-6 w-px bg-border" />

                {/* Font family */}
                <Select
                    value={toolbar.fontFamily}
                    onValueChange={(v) =>
                        v
                            ? chain.setFontFamily(v).run()
                            : chain.unsetFontFamily().run()
                    }
                >
                    <SelectTrigger className="h-8 w-40 text-xs">
                        <SelectValue placeholder="Font" />
                    </SelectTrigger>
                    <SelectContent onCloseAutoFocus={keepFocus}>
                        {FONT_FAMILIES.map((font) => (
                            <SelectItem key={font.label} value={font.value}>
                                {font.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {/* Font size */}
                <Select
                    value={toolbar.fontSize}
                    onValueChange={(v) =>
                        v
                            ? chain.setFontSize(v).run()
                            : chain.unsetFontSize().run()
                    }
                >
                    <SelectTrigger className="h-8 w-28 text-xs">
                        <SelectValue placeholder="Size" />
                    </SelectTrigger>
                    <SelectContent onCloseAutoFocus={keepFocus}>
                        {FONT_SIZES.map((size) => (
                            <SelectItem key={size.label} value={size.value}>
                                {size.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {/* Heading level */}
                <Select
                    value={toolbar.headingLabel}
                    onValueChange={(label) => {
                        const match = HEADING_LEVELS.find(
                            (h) => h.label === label,
                        );

                        if (match?.level) {
                            chain.toggleHeading({ level: match.level }).run();
                        } else {
                            chain.setParagraph().run();
                        }
                    }}
                >
                    <SelectTrigger className="h-8 w-28 text-xs">
                        <SelectValue placeholder="Style" />
                    </SelectTrigger>
                    <SelectContent onCloseAutoFocus={keepFocus}>
                        {HEADING_LEVELS.map((h) => (
                            <SelectItem key={h.label} value={h.label}>
                                {h.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <div className="mx-1 h-6 w-px bg-border" />

                {/* Text formatting */}
                <ToolbarButton
                    active={toolbar.isBold}
                    onClick={() => chain.toggleBold().run()}
                    icon={<Bold className="size-4" />}
                    title="Bold (Ctrl+B)"
                />
                <ToolbarButton
                    active={toolbar.isItalic}
                    onClick={() => chain.toggleItalic().run()}
                    icon={<Italic className="size-4" />}
                    title="Italic (Ctrl+I)"
                />
                <ToolbarButton
                    active={toolbar.isUnderline}
                    onClick={() => chain.toggleUnderline().run()}
                    icon={<UnderlineIcon className="size-4" />}
                    title="Underline (Ctrl+U)"
                />
                <ToolbarButton
                    active={toolbar.isStrike}
                    onClick={() => chain.toggleStrike().run()}
                    icon={<Strikethrough className="size-4" />}
                    title="Strikethrough"
                />
                <ToolbarButton
                    active={toolbar.isSuperscript}
                    onClick={() => chain.toggleSuperscript().run()}
                    icon={<SuperscriptIcon className="size-4" />}
                    title="Superscript"
                />
                <ToolbarButton
                    active={toolbar.isSubscript}
                    onClick={() => chain.toggleSubscript().run()}
                    icon={<SubscriptIcon className="size-4" />}
                    title="Subscript"
                />
                <ToolbarButton
                    onClick={() => chain.unsetAllMarks().clearNodes().run()}
                    icon={<Eraser className="size-4" />}
                    title="Clear formatting"
                />

                {/* Text colour */}
                <ColorMenu
                    label="Text colour"
                    value={toolbar.textColor}
                    colors={TEXT_COLORS}
                    onSelect={(c) =>
                        c ? chain.setColor(c).run() : chain.unsetColor().run()
                    }
                    onRefocus={refocusAfterClose}
                />

                {/* Highlight */}
                <ColorMenu
                    label="Highlight"
                    value={toolbar.backgroundColor}
                    colors={HIGHLIGHT_COLORS}
                    onSelect={(c) =>
                        c
                            ? chain.setBackgroundColor(c).run()
                            : chain.unsetBackgroundColor().run()
                    }
                    onRefocus={refocusAfterClose}
                />

                <div className="mx-1 h-6 w-px bg-border" />

                {/* Alignment */}
                <ToolbarButton
                    active={toolbar.alignLeft}
                    onClick={() => chain.setTextAlign('left').run()}
                    icon={<AlignLeft className="size-4" />}
                    title="Align left"
                />
                <ToolbarButton
                    active={toolbar.alignCenter}
                    onClick={() => chain.setTextAlign('center').run()}
                    icon={<AlignCenter className="size-4" />}
                    title="Align center"
                />
                <ToolbarButton
                    active={toolbar.alignRight}
                    onClick={() => chain.setTextAlign('right').run()}
                    icon={<AlignRight className="size-4" />}
                    title="Align right"
                />
                <ToolbarButton
                    active={toolbar.alignJustify}
                    onClick={() => chain.setTextAlign('justify').run()}
                    icon={<AlignJustify className="size-4" />}
                    title="Justify"
                />

                <div className="mx-1 h-6 w-px bg-border" />

                {/* Lists + blocks */}
                <ToolbarButton
                    active={toolbar.isBulletList}
                    onClick={() => chain.toggleBulletList().run()}
                    icon={<List className="size-4" />}
                    title="Bulleted list"
                />
                <ToolbarButton
                    active={toolbar.isOrderedList}
                    onClick={() => chain.toggleOrderedList().run()}
                    icon={<ListOrdered className="size-4" />}
                    title="Numbered list"
                />
                <ToolbarButton
                    active={toolbar.isBlockquote}
                    onClick={() => chain.toggleBlockquote().run()}
                    icon={<Pilcrow className="size-4" />}
                    title="Block quote"
                />
                <ToolbarButton
                    onClick={() => chain.setHorizontalRule().run()}
                    icon={<Minus className="size-4" />}
                    title="Horizontal rule"
                />

                <div className="mx-1 h-6 w-px bg-border" />

                {/* Link */}
                <ToolbarButton
                    active={toolbar.isLink}
                    onClick={setLink}
                    icon={<Link2 className="size-4" />}
                    title="Insert / edit link"
                />

                {/* Image */}
                <ToolbarButton
                    onClick={openFilePicker}
                    disabled={uploading}
                    icon={<ImagePlus className="size-4" />}
                    title="Insert image"
                />
                <ToolbarButton
                    onClick={startCrop}
                    disabled={!toolbar.isImage}
                    icon={<Crop className="size-4" />}
                    title="Crop selected image"
                />
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    className="hidden"
                    onChange={handleFileSelected}
                />

                {/* Table */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 rounded-md"
                        >
                            <Table2 className="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Table</DropdownMenuLabel>
                        <DropdownMenuItem
                            onSelect={() => {
                                tableMenu.insertTable();
                                refocusAfterClose();
                            }}
                        >
                            Insert table
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => {
                                tableMenu.addRow();
                                refocusAfterClose();
                            }}
                            disabled={!toolbar.isTable}
                        >
                            Add row below
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => {
                                tableMenu.addCol();
                                refocusAfterClose();
                            }}
                            disabled={!toolbar.isTable}
                        >
                            Add column right
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => {
                                tableMenu.deleteRow();
                                refocusAfterClose();
                            }}
                            disabled={!toolbar.isTable}
                        >
                            Delete row
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => {
                                tableMenu.deleteCol();
                                refocusAfterClose();
                            }}
                            disabled={!toolbar.isTable}
                        >
                            Delete column
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => {
                                tableMenu.merge();
                                refocusAfterClose();
                            }}
                            disabled={!toolbar.isTable}
                        >
                            Merge cells
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() => {
                                tableMenu.split();
                                refocusAfterClose();
                            }}
                            disabled={!toolbar.isTable}
                        >
                            Split cell
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => {
                                tableMenu.deleteTable();
                                refocusAfterClose();
                            }}
                            disabled={!toolbar.isTable}
                        >
                            Delete table
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <EditorContent
                editor={editor}
                className="md-editor__content rounded-md border border-input bg-background px-4 py-3 shadow-sm focus-within:ring-1 focus-within:ring-ring"
            />

            <input type="hidden" name={name} value={value} />

            {cropTarget && (
                <ImageCropDialog
                    src={cropTarget.src}
                    alt={cropTarget.alt}
                    onCropApplied={applyCroppedImage}
                    onClose={() => setCropTarget(null)}
                />
            )}
        </div>
    );
}

function ColorMenu({
    label,
    value,
    colors,
    onSelect,
    onRefocus,
}: {
    label: string;
    value: string;
    colors: string[];
    onSelect: (color: string) => void;
    onRefocus?: () => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className={cn(
                        'h-8 w-8 rounded-md',
                        value && 'bg-accent text-accent-foreground',
                    )}
                    aria-label={label}
                >
                    <Highlighter
                        className="size-4"
                        style={{ color: value || undefined }}
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-auto p-2">
                <div className="mb-1 flex items-center justify-between gap-2">
                    <span className="text-xs font-medium text-muted-foreground">
                        {label}
                    </span>
                    <button
                        type="button"
                        className="text-xs text-muted-foreground hover:text-foreground"
                        onClick={() => {
                            onSelect('');
                            onRefocus?.();
                        }}
                    >
                        Clear
                    </button>
                </div>
                <div className="flex flex-wrap gap-1">
                    {colors.map((color) => (
                        <button
                            key={color}
                            type="button"
                            className="size-6 rounded border border-border"
                            style={{ backgroundColor: color }}
                            aria-label={color}
                            onClick={() => {
                                onSelect(color);
                                onRefocus?.();
                            }}
                        />
                    ))}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
