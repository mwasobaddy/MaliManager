import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { store as maintenanceStore } from '@/routes/searcher/maintenance';

type RequestRow = {
    id: number;
    title: string;
    description: string;
    property_name: string | null;
    unit_name: string | null;
    status: string;
    priority: string;
    photo_count: number;
    created_at: string | null;
};

type ActiveLease = { id: number; label: string };

type Props = {
    requests: RequestRow[];
    activeLeases: ActiveLease[];
    priorities: string[];
};

export default function SearcherMaintenance({ requests, activeLeases, priorities }: Props) {
    const formRef = useRef<HTMLFormElement>(null);
    const [priority, setPriority] = useState('medium');
    const [leaseId, setLeaseId] = useState<string>(activeLeases[0] ? String(activeLeases[0].id) : '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
        const body = new FormData(event.currentTarget);
        body.set('lease_id', leaseId);
        body.set('priority', priority);

        setProcessing(true);
        setErrors({});

        fetch(maintenanceStore().url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body,
        })
            .then(async (response) => {
                if (response.ok) {
                    formRef.current?.reset();
                    setPriority('medium');
                    router.reload({ only: ['requests'] });
                } else if (response.status === 422) {
                    const data = await response.json();
                    setErrors(data.errors ?? {});
                }
            })
            .finally(() => setProcessing(false));
    };

    return (
        <>
            <Head title="Maintenance" />
            <div className="mx-auto flex h-full w-full max-w-3xl flex-1 flex-col gap-6 p-6">
                <Heading
                    title="Maintenance"
                    description="Report issues with the units you rent and follow their progress."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Report an issue</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form ref={formRef} onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="lease_id">Rental</Label>
                                <Select value={leaseId} onValueChange={setLeaseId}>
                                    <SelectTrigger><SelectValue placeholder="Pick your rental…" /></SelectTrigger>
                                    <SelectContent>
                                        {activeLeases.map((lease) => (
                                            <SelectItem key={lease.id} value={String(lease.id)}>
                                                {lease.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.lease_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="title">What is wrong?</Label>
                                <Input id="title" name="title" placeholder="e.g. Leaking kitchen tap" />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Describe the issue</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    rows={4}
                                    className="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                    placeholder="Tell us what needs to be fixed and where."
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2 sm:max-w-xs">
                                <Label>Priority</Label>
                                <Select value={priority} onValueChange={setPriority}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {priorities.map((p) => (
                                            <SelectItem key={p} value={p} className="capitalize">{p}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.priority} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="photos">Photos (up to 3)</Label>
                                <Input
                                    id="photos"
                                    name="photos[]"
                                    type="file"
                                    multiple
                                    accept=".jpg,.jpeg,.png,.webp"
                                />
                                <InputError message={errors['photos.0'] ?? errors.photos} />
                            </div>

                            <Button type="submit" disabled={processing || activeLeases.length === 0}>
                                Submit request
                            </Button>

                            {activeLeases.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    You need an active rental to raise maintenance requests.
                                </p>
                            )}
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Your requests</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {requests.length === 0 ? (
                            <p className="text-sm text-muted-foreground">You have not raised any requests yet.</p>
                        ) : (
                            requests.map((item) => (
                                <div key={item.id} className="rounded-lg border border-input p-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-medium">{item.title}</span>
                                        <Badge variant="outline" className="capitalize">{item.status.replace('_', ' ')}</Badge>
                                    </div>
                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{item.description}</p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {[item.property_name, item.unit_name].filter(Boolean).join(' · ')}
                                        {' · '}{item.created_at}
                                        {' · '}<span className="capitalize">{item.priority}</span> priority
                                    </p>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
