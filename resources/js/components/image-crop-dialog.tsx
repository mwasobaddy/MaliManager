import { useState } from 'react';
import Cropper from 'react-easy-crop';
import type { Area, Point } from 'react-easy-crop';
import 'react-easy-crop/react-easy-crop.css';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const ASPECT_PRESETS: { label: string; value: number | null }[] = [
    { label: 'Free', value: null },
    { label: '1:1', value: 1 },
    { label: '4:3', value: 4 / 3 },
    { label: '16:9', value: 16 / 9 },
];

function loadImage(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.crossOrigin = 'anonymous';
        image.onload = () => resolve(image);
        image.onerror = () =>
            reject(new Error('Failed to load image for cropping'));
        image.src = src;
    });
}

async function cropImageToBlob(src: string, area: Area): Promise<Blob> {
    const image = await loadImage(src);

    const canvas = document.createElement('canvas');
    canvas.width = area.width;
    canvas.height = area.height;

    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('Canvas is not supported');
    }

    ctx.drawImage(
        image,
        area.x,
        area.y,
        area.width,
        area.height,
        0,
        0,
        area.width,
        area.height,
    );

    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error('Failed to export cropped image'));
                }
            },
            'image/png',
            1,
        );
    });
}

type ImageCropDialogProps = {
    src: string;
    alt?: string;
    onCropApplied: (file: File) => void;
    onClose: () => void;
};

/**
 * Modal crop tool for images inside the rich-text editor. Uses
 * react-easy-crop to select a region, exports the crop via a canvas, and
 * hands the resulting File back to the caller for re-upload to /editor-images.
 *
 * Mounts fresh each time a crop begins (the parent conditionally renders it),
 * so initial crop/zoom state is always clean.
 */
export default function ImageCropDialog({
    src,
    alt,
    onCropApplied,
    onClose,
}: ImageCropDialogProps) {
    const [open, setOpen] = useState(true);
    const [crop, setCrop] = useState<Point>({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [area, setArea] = useState<Area | null>(null);
    const [aspect, setAspect] = useState<number | null>(null);
    const [applying, setApplying] = useState(false);

    function handleCropApplied(file: File) {
        setOpen(false);
        onClose();
        onCropApplied(file);
    }

    async function handleApply() {
        if (!area) {
            return;
        }

        setApplying(true);

        try {
            const blob = await cropImageToBlob(src, area);
            const file = new File([blob], alt || 'cropped-image.png', {
                type: 'image/png',
            });

            handleCropApplied(file);
        } catch (error) {
            console.error('Failed to crop image', error);
        } finally {
            setApplying(false);
        }
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (!next) {
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Crop image</DialogTitle>
                    <DialogDescription>
                        Drag to position the crop area and use the slider to
                        zoom.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-wrap gap-1">
                    {ASPECT_PRESETS.map((preset) => (
                        <Button
                            key={preset.label}
                            type="button"
                            variant="ghost"
                            size="sm"
                            className={
                                aspect === preset.value
                                    ? 'bg-accent text-accent-foreground'
                                    : undefined
                            }
                            onClick={() => setAspect(preset.value)}
                        >
                            {preset.label}
                        </Button>
                    ))}
                </div>

                <div className="relative h-72 w-full overflow-hidden rounded-md border border-input">
                    <Cropper
                        image={src}
                        crop={crop}
                        zoom={zoom}
                        aspect={aspect ?? 1}
                        showGrid
                        onCropChange={setCrop}
                        onZoomChange={setZoom}
                        onCropComplete={(_area, areaPixels) =>
                            setArea(areaPixels)
                        }
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            setOpen(false);
                            onClose();
                        }}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={!area || applying}
                        onClick={handleApply}
                    >
                        {applying ? 'Applying…' : 'Apply crop'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
