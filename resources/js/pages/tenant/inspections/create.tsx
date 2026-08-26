import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    index as inspectionsIndex,
    store as storeInspection,
} from '@/routes/tenant/inspections';

type Unit = { id: number; name: string };

type Props = {
    units: Unit[];
};

export default function InspectionCreate({ units }: Props) {
    const propertySlug = window.location.pathname.split('/')[1];
    const form = useForm<{
        title: string;
        unit_id: string;
        inspection_date: string;
        notes: string;
        photos: File[] | null;
    }>({
        title: '',
        unit_id: '',
        inspection_date: new Date().toISOString().slice(0, 10),
        notes: '',
        photos: null,
    });

    const submit = () => {
        form.post(storeInspection({ property: propertySlug }).url);
    };

    return (
        <>
            <Head title="New inspection" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="small"
                    title="New inspection"
                    description="Upload walkthrough photos — AI generates the condition report."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                value={form.data.title}
                                onChange={(e) => form.setData('title', e.target.value)}
                                placeholder="Move-out walkthrough"
                            />
                            <InputError message={form.errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Unit</Label>
                            <Select value={form.data.unit_id} onValueChange={(v) => form.setData('unit_id', v)}>
                                <SelectTrigger><SelectValue placeholder="Pick a unit…" /></SelectTrigger>
                                <SelectContent>
                                    {units.map((unit) => (
                                        <SelectItem key={unit.id} value={String(unit.id)}>
                                            {unit.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.unit_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="inspection_date">Date</Label>
                            <Input
                                id="inspection_date"
                                type="date"
                                value={form.data.inspection_date}
                                onChange={(e) => form.setData('inspection_date', e.target.value)}
                            />
                            <InputError message={form.errors.inspection_date} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="notes">Notes (helps the AI report)</Label>
                            <textarea
                                id="notes"
                                rows={3}
                                className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                placeholder="Anything you noticed during the walkthrough."
                                value={form.data.notes}
                                onChange={(e) => form.setData('notes', e.target.value)}
                            />
                            <InputError message={form.errors.notes} />
                        </div>

                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="photos">Photos (up to 10)</Label>
                            <Input
                                id="photos"
                                type="file"
                                multiple
                                accept=".jpg,.jpeg,.png,.webp"
                                onChange={(e) =>
                                    form.setData('photos', e.target.files ? Array.from(e.target.files) : null)
                                }
                            />
                            <InputError message={form.errors.photos} />
                        </div>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href={inspectionsIndex({ property: propertySlug })}>Back to inspections</Link>
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        <Save className="size-4" />
                        Create inspection
                    </Button>
                </div>
            </div>
        </>
    );
}
