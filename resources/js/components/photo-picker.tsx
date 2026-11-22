import { Check, ImagePlus, Images, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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

/**
 * Upload picker with an optional reuse library: identical units share stock
 * photos, so landlords pick from already-uploaded pictures instead of
 * re-shooting every room. Uploads post through the native file input;
 * reused ids post as hidden inputs and the backend copies the files.
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
    const [previews, setPreviews] = useState<string[]>([]);
    const [reused, setReused] = useState<PhotoLibraryPhoto[]>([]);
    const [reuseUnitId, setReuseUnitId] = useState<string>('');
    const [clientError, setClientError] = useState<string | null>(null);

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
    const chosen = previews.length + reused.length;

    // Object URLs leak for the life of the page unless revoked.
    useEffect(() => {
        return () => {
            previews.forEach((url) => URL.revokeObjectURL(url));
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function syncPreviews(files: File[]) {
        setPreviews((old) => {
            old.forEach((url) => URL.revokeObjectURL(url));

            return files.map((file) => URL.createObjectURL(file));
        });
    }

    function handleChange(event: React.ChangeEvent<HTMLInputElement>) {
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
        const kept = valid.slice(0, room);

        if (valid.length > room) {
            setClientError(
                `Keep it to ${remaining} photos total — extras were dropped.`,
            );
        }

        // Write the surviving files back so the form posts exactly these.
        const transfer = new DataTransfer();
        kept.forEach((file) => transfer.items.add(file));

        if (inputRef.current) {
            inputRef.current.files = transfer.files;
        }

        syncPreviews(kept);
    }

    function removeUploadAt(index: number) {
        const input = inputRef.current;

        if (!input?.files) {
            return;
        }

        const transfer = new DataTransfer();
        Array.from(input.files).forEach((file, i) => {
            if (i !== index) {
                transfer.items.add(file);
            }
        });
        input.files = transfer.files;
        setClientError(null);
        syncPreviews(Array.from(transfer.files));
    }

    function toggleReuse(photo: PhotoLibraryPhoto) {
        setClientError(null);
        setReused((current) => {
            if (current.some((entry) => entry.id === photo.id)) {
                return current.filter((entry) => entry.id !== photo.id);
            }

            if (current.length + previews.length >= remaining) {
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
            {(previews.length > 0 || reused.length > 0) && (
                <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                    {previews.map((url, index) => (
                        <div
                            key={url}
                            className="group relative overflow-hidden rounded-lg border border-border-strong"
                        >
                            <img
                                src={url}
                                alt={`New photo ${index + 1}`}
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
                    {reused.map((photo) => (
                        <div
                            key={`reused-${photo.id}`}
                            className="group relative overflow-hidden rounded-lg border border-brand-primary/50"
                        >
                            <img
                                src={photo.url}
                                alt={photo.name}
                                loading="lazy"
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
                                            (entry) => entry.id !== photo.id,
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
