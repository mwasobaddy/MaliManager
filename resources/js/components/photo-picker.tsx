import { Check, GripVertical, ImagePlus, Images, X } from 'lucide-react';
import {
    Children,
    isValidElement,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type {
    PointerEvent as ReactPointerEvent,
    ReactNode,
    RefObject,
} from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_SIZE_BYTES = 8 * 1024 * 1024;

export type PhotoLibraryPhoto = {
    id: number;
    url: string;
    name: string;
};

export type PhotoLibraryUnit = {
    unit_id: number;
    unit_name: string;
    property_name: string;
    photos: PhotoLibraryPhoto[];
};

type UploadEntry = {
    file: File;
    url: string;
};

type PhotoPickerProps = {
    /** Field name for uploads. The input posts one file per entry. */
    name?: string;
    /** Field name for reused library photo ids. */
    reuseName?: string;
    max?: number;
    /** Photos already attached (edit sheets). Shrinks the remaining budget. */
    alreadyAttached?: number;
    /** Other units' photos, offered for reuse. */
    library?: PhotoLibraryUnit[];
    /** Own photos are pointless to reuse — hidden from the picker. */
    excludeUnitId?: number | null;
    /** Server-side message, e.g. `photos.0` failures. */
    error?: string;
    id?: string;
};

type StripName = 'uploads' | 'reused';

type DragState = {
    list: StripName;
    index: number;
};

/**
 * Upload picker with an optional reuse library: identical units share stock
 * photos, so landlords pick from already-uploaded pictures instead of
 * re-shooting every room. Uploads post through the native file input;
 * reused ids post as hidden inputs and the backend copies the files.
 *
 * Both preview strips reorder by drag (pointer events, so touch works) and
 * by arrow keys on the grip handle. The submitted order follows the
 * displayed order — and since an explicit cover falls back to the first
 * photo, dragging doubles as choosing the face of the unit.
 */
export function PhotoPicker({
    name = 'photos[]',
    reuseName = 'reuse_photo_ids[]',
    max = 12,
    alreadyAttached = 0,
    library = [],
    excludeUnitId = null,
    error,
    id = 'photo-picker',
}: PhotoPickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [uploads, setUploads] = useState<UploadEntry[]>([]);
    const [reused, setReused] = useState<PhotoLibraryPhoto[]>([]);
    const [reuseUnitId, setReuseUnitId] = useState<string>('');
    const [clientError, setClientError] = useState<string | null>(null);
    const [drag, setDrag] = useState<DragState | null>(null);
    const [dropIndex, setDropIndex] = useState<number | null>(null);

    // Every object URL ever minted, so removals revoke exactly what they
    // drop and unmount revokes the rest.
    const liveUrls = useRef<Set<string>>(new Set());
    const dragRef = useRef<DragState | null>(null);
    const tileRefs = useRef<Record<StripName, (HTMLDivElement | null)[]>>({
        uploads: [],
        reused: [],
    });

    const usableLibrary = useMemo(
        () =>
            library.filter(
                (entry) =>
                    entry.unit_id !== excludeUnitId && entry.photos.length > 0,
            ),
        [library, excludeUnitId],
    );
    const reuseUnit = usableLibrary.find(
        (entry) => String(entry.unit_id) === reuseUnitId,
    );
    const remaining = Math.max(0, max - alreadyAttached);
    const chosen = uploads.length + reused.length;

    useEffect(() => {
        const urls = liveUrls.current;

        return () => {
            urls.forEach((url) => URL.revokeObjectURL(url));
            urls.clear();
        };
    }, []);

    function writeUploads(next: UploadEntry[]) {
        const transfer = new DataTransfer();
        next.forEach((entry) => transfer.items.add(entry.file));

        if (inputRef.current) {
            inputRef.current.files = transfer.files;
        }

        setUploads(next);
    }

    function handleChange(event: React.ChangeEvent<HTMLInputElement>) {
        // The dialog replaces the input's files on every pick, so the
        // component's own list is the source of truth — otherwise adding
        // photos one at a time silently drops the earlier ones.
        const picked = Array.from(event.target.files ?? []);
        const valid = picked.filter(
            (file) =>
                ACCEPTED_TYPES.includes(file.type) &&
                file.size <= MAX_SIZE_BYTES,
        );

        if (valid.length !== picked.length) {
            setClientError(
                'Some files were skipped — JPG, PNG or WebP up to 8 MB each.',
            );
        } else {
            setClientError(null);
        }

        const room = Math.max(0, remaining - reused.length);
        const additions = valid
            .slice(0, Math.max(0, room - uploads.length))
            .map((file) => {
                const url = URL.createObjectURL(file);
                liveUrls.current.add(url);

                return { file, url };
            });

        if (uploads.length + valid.length > room) {
            setClientError(
                `Keep it to ${remaining} photos total — extras were dropped.`,
            );
        }

        // Write the surviving files back so the form posts exactly these.
        writeUploads([...uploads, ...additions]);
    }

    function removeUploadAt(index: number) {
        const gone = uploads[index];

        if (gone) {
            liveUrls.current.delete(gone.url);
            URL.revokeObjectURL(gone.url);
        }

        setClientError(null);
        writeUploads(uploads.filter((_, i) => i !== index));
    }

    function moveEntry(list: StripName, from: number, to: number) {
        // The list may have shrunk mid-drag (a remove tap lands
        // underneath the pointer); a stale index commits nothing.
        if (list === 'uploads') {
            const moved = uploads[from];

            if (moved === undefined) {
                return;
            }

            const next = uploads.filter((_, i) => i !== from);
            next.splice(Math.min(Math.max(to, 0), next.length), 0, moved);
            writeUploads(next);
        } else {
            const moved = reused[from];

            if (moved === undefined) {
                return;
            }

            const next = reused.filter((_, i) => i !== from);
            next.splice(Math.min(Math.max(to, 0), next.length), 0, moved);
            setReused(next);
        }
    }

    function endDrag(commit: boolean) {
        const current = dragRef.current;
        dragRef.current = null;

        if (commit && current && dropIndex !== null) {
            moveEntry(current.list, current.index, dropIndex);
        }

        setDrag(null);
        setDropIndex(null);
    }

    function handleDropMove(event: React.PointerEvent, list: StripName) {
        const current = dragRef.current;

        if (!current || current.list !== list) {
            return;
        }

        // Walk the strip minus the dragged tile; the first tile whose
        // centre sits past the pointer is where the gap goes.
        const tiles = tileRefs.current[list].filter(
            (_, i) => i !== current.index,
        );
        let next = tiles.length;

        for (let i = 0; i < tiles.length; i++) {
            const rect = tiles[i]?.getBoundingClientRect();

            if (!rect) {
                continue;
            }

            if (
                event.clientY < rect.top + rect.height / 2 ||
                (event.clientY < rect.bottom &&
                    event.clientX < rect.left + rect.width / 2)
            ) {
                next = i;
                break;
            }
        }

        setDropIndex(next);
    }

    function toggleReuse(photo: PhotoLibraryPhoto) {
        setClientError(null);
        setReused((current) => {
            if (current.some((entry) => entry.id === photo.id)) {
                return current.filter((entry) => entry.id !== photo.id);
            }

            if (current.length + uploads.length >= remaining) {
                setClientError(
                    `Keep it to ${remaining} photos total — remove one first.`,
                );

                return current;
            }

            return [...current, photo];
        });
    }

    return (
        <div className="grid gap-3">
            <input
                ref={inputRef}
                id={id}
                name={name}
                type="file"
                accept={ACCEPTED_TYPES.join(',')}
                multiple
                className="hidden"
                onChange={handleChange}
            />
            {reused.map((photo) => (
                <input
                    key={photo.id}
                    type="hidden"
                    name={reuseName}
                    value={photo.id}
                />
            ))}
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => inputRef.current?.click()}
                >
                    <ImagePlus className="size-4" />
                    {chosen === 0
                        ? 'Add photos'
                        : `Add more (${chosen}/${remaining})`}
                </Button>
                <span className="text-xs text-muted-foreground">
                    Up to {remaining} more • JPG, PNG, WebP, 8 MB each
                    {alreadyAttached > 0 &&
                        ` (${alreadyAttached} already attached)`}
                </span>
            </div>
            {(uploads.length > 0 || reused.length > 0) && (
                <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                    <ReorderStrip
                        list="uploads"
                        drag={drag}
                        dropIndex={dropIndex}
                        tileRefs={tileRefs}
                        onPointerDown={(index) => (event) => {
                            if (
                                event.pointerType === 'mouse' &&
                                event.button !== 0
                            ) {
                                return;
                            }

                            dragRef.current = {
                                list: 'uploads',
                                index,
                            };
                            setDrag({ list: 'uploads', index });
                            setDropIndex(index);
                            try {
                                event.currentTarget.setPointerCapture(
                                    event.pointerId,
                                );
                            } catch {
                                // Touch already captures implicitly and some
                                // pointers cannot be captured: moves still
                                // bubble through the strip either way.
                            }
                            event.preventDefault();
                        }}
                        onPointerMove={(event) =>
                            handleDropMove(event, 'uploads')
                        }
                        onPointerUp={() => endDrag(true)}
                        onPointerCancel={() => endDrag(false)}
                        onMoveKey={(index, delta) =>
                            moveEntry('uploads', index, index + delta)
                        }
                    >
                        {uploads.map((entry, index) => (
                            <div
                                key={entry.url}
                                className={cn(
                                    'group relative overflow-hidden rounded-lg border border-border-strong',
                                    drag?.list === 'uploads' &&
                                        drag.index === index &&
                                        'opacity-40',
                                )}
                            >
                                <img
                                    src={entry.url}
                                    alt={`New photo ${index + 1}`}
                                    draggable={false}
                                    className="aspect-square w-full object-cover"
                                />
                                <button
                                    type="button"
                                    aria-label={`Remove new photo ${index + 1}`}
                                    onClick={() => removeUploadAt(index)}
                                    className={cn(
                                        'absolute top-1 right-1 rounded-full bg-background/90 p-1',
                                        'text-muted-foreground hover:text-destructive',
                                    )}
                                >
                                    <X className="size-3.5" />
                                </button>
                            </div>
                        ))}
                    </ReorderStrip>
                    <ReorderStrip
                        list="reused"
                        drag={drag}
                        dropIndex={dropIndex}
                        tileRefs={tileRefs}
                        onPointerDown={(index) => (event) => {
                            if (
                                event.pointerType === 'mouse' &&
                                event.button !== 0
                            ) {
                                return;
                            }

                            dragRef.current = {
                                list: 'reused',
                                index,
                            };
                            setDrag({ list: 'reused', index });
                            setDropIndex(index);
                            try {
                                event.currentTarget.setPointerCapture(
                                    event.pointerId,
                                );
                            } catch {
                                // Touch already captures implicitly and some
                                // pointers cannot be captured: moves still
                                // bubble through the strip either way.
                            }
                            event.preventDefault();
                        }}
                        onPointerMove={(event) =>
                            handleDropMove(event, 'reused')
                        }
                        onPointerUp={() => endDrag(true)}
                        onPointerCancel={() => endDrag(false)}
                        onMoveKey={(index, delta) =>
                            moveEntry('reused', index, index + delta)
                        }
                    >
                        {reused.map((photo, index) => (
                            <div
                                key={`reused-${photo.id}`}
                                className={cn(
                                    'group relative overflow-hidden rounded-lg border border-brand-primary/50',
                                    drag?.list === 'reused' &&
                                        drag.index === index &&
                                        'opacity-40',
                                )}
                            >
                                <img
                                    src={photo.url}
                                    alt={photo.name}
                                    loading="lazy"
                                    draggable={false}
                                    className="aspect-square w-full object-cover"
                                />
                                <span className="absolute bottom-1 left-1 rounded bg-background/90 px-1 text-[10px] font-medium text-foreground">
                                    Reused
                                </span>
                                <button
                                    type="button"
                                    aria-label={`Remove reused photo ${photo.name}`}
                                    onClick={() =>
                                        setReused((current) =>
                                            current.filter(
                                                (entry) =>
                                                    entry.id !== photo.id,
                                            ),
                                        )
                                    }
                                    className={cn(
                                        'absolute top-1 right-1 rounded-full bg-background/90 p-1',
                                        'text-muted-foreground hover:text-destructive',
                                    )}
                                >
                                    <X className="size-3.5" />
                                </button>
                            </div>
                        ))}
                    </ReorderStrip>
                </div>
            )}
            {usableLibrary.length > 0 && (
                <div className="grid gap-2">
                    <Label
                        htmlFor={`${id}-reuse-unit`}
                        className="text-muted-foreground"
                    >
                        <span className="flex items-center gap-1.5">
                            <Images className="size-3.5" />
                            Or reuse photos from another unit
                        </span>
                    </Label>
                    <Select value={reuseUnitId} onValueChange={setReuseUnitId}>
                        <SelectTrigger id={`${id}-reuse-unit`}>
                            <SelectValue placeholder="Choose a unit…" />
                        </SelectTrigger>
                        <SelectContent>
                            {usableLibrary.map((entry) => (
                                <SelectItem
                                    key={entry.unit_id}
                                    value={String(entry.unit_id)}
                                >
                                    {entry.unit_name} · {entry.property_name} (
                                    {entry.photos.length})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {reuseUnit && (
                        <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                            {reuseUnit.photos.map((photo) => {
                                const picked = reused.some(
                                    (entry) => entry.id === photo.id,
                                );

                                return (
                                    <button
                                        key={photo.id}
                                        type="button"
                                        aria-label={`${picked ? 'Remove' : 'Reuse'} photo ${photo.name}`}
                                        aria-pressed={picked}
                                        onClick={() => toggleReuse(photo)}
                                        className={cn(
                                            'relative overflow-hidden rounded-lg border',
                                            picked
                                                ? 'border-brand-primary ring-2 ring-brand-primary/40'
                                                : 'border-border-strong',
                                        )}
                                    >
                                        <img
                                            src={photo.url}
                                            alt={photo.name}
                                            loading="lazy"
                                            draggable={false}
                                            className="aspect-square w-full object-cover"
                                        />
                                        {picked && (
                                            <span className="absolute top-1 right-1 rounded-full bg-brand-primary p-0.5 text-white">
                                                <Check className="size-3.5" />
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
            <InputError message={clientError ?? error} />
        </div>
    );
}

type ReorderStripProps = {
    list: StripName;
    drag: DragState | null;
    dropIndex: number | null;
    tileRefs: RefObject<Record<StripName, (HTMLDivElement | null)[]>>;
    onPointerDown: (
        index: number,
    ) => (event: ReactPointerEvent<HTMLButtonElement>) => void;
    onPointerMove: (event: ReactPointerEvent) => void;
    onPointerUp: () => void;
    onPointerCancel: () => void;
    onMoveKey: (index: number, delta: -1 | 1) => void;
    children: ReactNode[];
};

/**
 * Renders strip children with a dashed gap where the dragged tile would
 * land and a grip on every tile. Children order matches the data order;
 * the gap is positional only until drop commits.
 */
function ReorderStrip({
    list,
    drag,
    dropIndex,
    tileRefs,
    onPointerDown,
    onPointerMove,
    onPointerUp,
    onPointerCancel,
    onMoveKey,
    children,
}: ReorderStripProps) {
    const items = Children.toArray(children);
    const active = drag?.list === list ? drag : null;
    // Drop position in the order without the dragged tile; the commit
    // inserts there, so the indicator must speak the same coordinates.
    const removedLength = active ? items.length - 1 : items.length;
    const at = dropIndex ?? active?.index ?? null;
    const showTrailing =
        active !== null &&
        at !== null &&
        at >= removedLength &&
        at !== active.index;

    return (
        <>
            {items.map((child, dataIndex) => {
                const isDragged = active?.index === dataIndex;
                const removedPos =
                    active && dataIndex > active.index
                        ? dataIndex - 1
                        : dataIndex;
                const showBar =
                    active !== null &&
                    !isDragged &&
                    removedPos === dropIndex &&
                    dropIndex !== active.index;

                return (
                    <div
                        key={`${list}-tile-${dataIndex}`}
                        ref={(node) => {
                            tileRefs.current[list][dataIndex] = node;
                        }}
                        onPointerMove={onPointerMove}
                        onPointerUp={onPointerUp}
                        onPointerCancel={onPointerCancel}
                        className="relative"
                    >
                        {showBar && (
                            <span
                                aria-hidden
                                className="absolute top-1 bottom-1 -left-1.5 z-10 w-1 rounded-full bg-brand-primary"
                            />
                        )}
                        <div className={cn(isDragged && 'opacity-40')}>
                            {child}
                        </div>
                        <button
                            type="button"
                            aria-label={`Drag to reorder photo ${dataIndex + 1}`}
                            title="Drag to reorder"
                            onPointerDown={onPointerDown(dataIndex)}
                            onKeyDown={(event) => {
                                if (
                                    event.key !== 'ArrowLeft' &&
                                    event.key !== 'ArrowRight' &&
                                    event.key !== 'ArrowUp' &&
                                    event.key !== 'ArrowDown'
                                ) {
                                    return;
                                }

                                event.preventDefault();
                                onMoveKey(
                                    dataIndex,
                                    event.key === 'ArrowLeft' ||
                                        event.key === 'ArrowUp'
                                        ? -1
                                        : 1,
                                );
                            }}
                            className={cn(
                                'absolute top-1 left-1 rounded-full bg-background/90 p-1',
                                'cursor-grab [touch-action:none] text-muted-foreground hover:text-foreground active:cursor-grabbing',
                            )}
                        >
                            <GripVertical className="size-3.5" />
                        </button>
                    </div>
                );
            })}
            {showTrailing && (
                <div
                    key={`${list}-gap`}
                    aria-hidden
                    className="aspect-square rounded-lg border-2 border-dashed border-brand-primary/60"
                />
            )}
        </>
    );
}
