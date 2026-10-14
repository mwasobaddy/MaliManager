import { Form, usePage } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useState } from 'react';
import { OverlayFooter } from '@/components/overlay-footer';
import { ResponsiveOverlay } from '@/components/responsive-overlay';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/** Shape of the per-row report a controller flashes as `importErrors`. */
type ImportError = { row: number; message: string };

type ImportSheetProps = {
    open: boolean;
    onClose: () => void;
    /** Wayfinder route helper for the import endpoint. */
    route: () => { url: string };
    title: string;
    description: string;
    /** Columns the importer cannot proceed without. */
    required: string[];
    optional?: string[];
    /** `canImport` from the page's permission list. */
    canImport: boolean;
    buttonLabel?: string;
    /** Distinguishes the file input id when more than one sheet is mounted. */
    idPrefix: string;
};

/**
 * The CSV upload sheet shared by every platform table that can import.
 *
 * `importErrors` is read off the page object, not `props`, because that is where
 * `Inertia::flash()` puts it. A partial failure deliberately leaves the sheet
 * open so the per-row report stays readable — closing on `onSuccess` the way a
 * clean run does would throw the report away exactly when it is needed.
 */
export function CsvImportSheet({
    open,
    onClose,
    route,
    title,
    description,
    required,
    optional = [],
    canImport,
    buttonLabel = 'Import',
    idPrefix,
}: ImportSheetProps) {
    const [errors, setErrors] = useState<ImportError[]>([]);
    const page = usePage();
    const validationErrors = (page.props.errors ?? {}) as Record<string, string>;

    if (!canImport) {
        return null;
    }

    return (
        <ResponsiveOverlay
            open={open}
            onOpenChange={(next) => {
                if (!next) {
                    setErrors([]);
                    onClose();
                }
            }}
            title={title}
            description={description}
            footer={
                <OverlayFooter
                    onCancel={onClose}
                    cancelLabel="Close"
                />
            }
        >
            <Form
                method="post"
                action={route().url}
                encType="multipart/form-data"
                onSuccess={(response) => {
                    // Flash rides on the page object itself, not `props`.
                    const reported = (
                        response as unknown as {
                            flash?: { importErrors?: ImportError[] };
                        }
                    ).flash?.importErrors ?? [];

                    setErrors(reported);

                    if (reported.length === 0) {
                        onClose();
                    }
                }}
                className="space-y-3 py-2"
            >
                <div className="border-border-strong bg-background space-y-1 rounded-lg border p-3 text-xs">
                    <div className="font-medium">Required columns</div>
                    <div className="text-muted-foreground mt-1">
                        {required.join(', ')}
                    </div>
                    {optional.length > 0 && (
                        <>
                            <div className="font-medium mt-3">Optional</div>
                            <div className="text-muted-foreground">
                                {optional.join(', ')}
                            </div>
                        </>
                    )}
                </div>

                <Input
                    id={`${idPrefix}-import-file`}
                    name="file"
                    type="file"
                    accept=".csv,text/csv,text/plain"
                    aria-label="CSV file"
                />

                {validationErrors.file && (
                    <p className="text-destructive text-xs">
                        {validationErrors.file}
                    </p>
                )}

                {errors.length > 0 && (
                    <div
                        role="status"
                        className="border-destructive/40 bg-destructive/5 space-y-1 rounded-lg border p-3"
                    >
                        <div className="text-destructive text-xs font-medium">
                            {errors.length}{' '}
                            {errors.length === 1 ? 'row was' : 'rows were'}{' '}
                            skipped
                        </div>
                        <ul className="text-muted-foreground space-y-1 text-xs">
                            {errors.map((problem, index) => (
                                <li key={`${problem.row}-${index}`}>
                                    {problem.message}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <Button type="submit" className="w-full">
                    <Upload className="mr-2 size-4" />
                    {buttonLabel}
                </Button>
            </Form>
        </ResponsiveOverlay>
    );
}

/**
 * Trigger a CSV download as a real navigation.
 *
 * Not `router.visit()` and not `fetch()`: the endpoint answers with a file
 * attachment, so an Inertia visit has nothing to render and would surface its
 * error dialog, and a fetch-built blob drops the session cookie. A hidden form
 * GET is the only one of the three that keeps the cookie and lets the browser
 * save the file.
 *
 * `scope=page` keeps the export on the page the user is looking at;
 * anything else exports the whole filtered set.
 */
export function downloadCsv(
    routeUrl: string,
    params: Record<string, string | number>,
): void {
    const url = new URL(routeUrl, window.location.origin);

    for (const [key, value] of Object.entries(params)) {
        if (value !== '') {
            url.searchParams.set(key, String(value));
        }
    }

    const form = document.createElement('form');

    form.method = 'GET';
    form.action = url.pathname + url.search;
    form.hidden = true;
    document.body.appendChild(form);
    form.submit();
    form.remove();
}