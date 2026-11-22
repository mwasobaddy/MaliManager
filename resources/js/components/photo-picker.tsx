import { ImagePlus, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_SIZE_BYTES = 8 * 1024 * 1024;

type PhotoPickerProps = {
    /** Field name. The input posts one file per entry (`photos[]`). */
    name?: string;
    max?: number;
    /** Server-side message, e.g. `photos.0` failures. */
    error?: string;
    id?: string;
};

/**
 * Multi-photo picker for unit creation. The native file input stays in the
 * DOM (so Inertia's FormData collection picks the files up untouched) while
 * previews render from object URLs with per-photo remove.
 */
export function PhotoPicker({
    name = 'photos[]',
    max = 12,
    error,
    id = 'photo-picker',
}: PhotoPickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [previews, setPreviews] = useState<string[]>([]);
    const [clientError, setClientError] = useState<string | null>(null);

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

        const kept = valid.slice(0, max);

        if (valid.length > max) {
            setClientError(`Keep it to ${max} photos — extras were dropped.`);
        }

        // Write the surviving files back so the form posts exactly these.
        const transfer = new DataTransfer();
        kept.forEach((file) => transfer.items.add(file));

        if (inputRef.current) {
            inputRef.current.files = transfer.files;
        }

        syncPreviews(kept);
    }

    function removeAt(index: number) {
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

    return (
        <div className="grid gap-2">
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
            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => inputRef.current?.click()}
                >
                    <ImagePlus className="size-4" />
                    {previews.length === 0
                        ? 'Add photos'
                        : `Add more (${previews.length}/${max})`}
                </Button>
                <span className="text-xs text-muted-foreground">
                    Up to {max} • JPG, PNG, WebP, 8 MB each
                </span>
            </div>
            {previews.length > 0 && (
                <div className="grid grid-cols-4 gap-2 sm:grid-cols-6">
                    {previews.map((url, index) => (
                        <div
                            key={url}
                            className="group relative overflow-hidden rounded-lg border border-border-strong"
                        >
                            <img
                                src={url}
                                alt={`Photo ${index + 1}`}
                                className="aspect-square w-full object-cover"
                            />
                            <button
                                type="button"
                                aria-label={`Remove photo ${index + 1}`}
                                onClick={() => removeAt(index)}
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
            <InputError message={clientError ?? error} />
        </div>
    );
}
