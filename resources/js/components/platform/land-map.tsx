import L from 'leaflet';
import { useEffect, useRef } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import 'leaflet/dist/leaflet.css';

type Parcel = {
    id: number;
    name: string;
    status: string;
    city: string | null;
    acreage: string | null;
    latitude: number | null;
    longitude: number | null;
};

const STATUS_COLORS: Record<string, string> = {
    active: '#0e7c7e',
    inactive: '#8f8576',
};

/**
 * Parcels on OpenStreetMap tiles, one circle per titled parcel. Parcels
 * without coordinates cannot be plotted and list below the map instead
 * of silently vanishing.
 */
export function LandMap({ parcels }: { parcels: Parcel[] }) {
    const ref = useRef<HTMLDivElement>(null);
    const plotted = parcels.filter(
        (parcel) => parcel.latitude !== null && parcel.longitude !== null,
    );
    const unplotted = parcels.filter(
        (parcel) => parcel.latitude === null || parcel.longitude === null,
    );

    const key = plotted.map((parcel) => parcel.id).join(',');

    useEffect(() => {
        if (ref.current === null || plotted.length === 0) {
            return;
        }

        const map = L.map(ref.current).setView(
            [plotted[0].latitude as number, plotted[0].longitude as number],
            7,
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        const markers = plotted.map((parcel) =>
            L.circleMarker(
                [parcel.latitude as number, parcel.longitude as number],
                {
                    radius: 9,
                    color: STATUS_COLORS[parcel.status] ?? '#8f8576',
                    fillColor: STATUS_COLORS[parcel.status] ?? '#8f8576',
                    fillOpacity: 0.7,
                    weight: 2,
                },
            )
                .bindPopup(
                    `<strong>${parcel.name}</strong><br>${parcel.status} · ${parcel.acreage ?? '?'} acres`,
                )
                .addTo(map),
        );

        if (markers.length > 1) {
            map.fitBounds(L.featureGroup(markers).getBounds().pad(0.3));
        }

        return () => {
            map.remove();
        };
    }, [key]);

    if (parcels.length === 0) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base font-medium">
                        Land parcels
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-muted-foreground">
                        No parcels registered yet.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base font-medium">
                    Land parcels
                </CardTitle>
                <CardDescription>
                    {plotted.length} of {parcels.length} plotted · teal is
                    active, grey is inactive.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-3">
                {plotted.length > 0 ? (
                    <div
                        ref={ref}
                        className="z-0 h-64 w-full overflow-hidden rounded-lg border"
                        role="img"
                        aria-label={`Map of ${plotted.length} land parcels`}
                    />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        None of the parcels have coordinates yet — add latitude
                        and longitude to plot them.
                    </p>
                )}
                {unplotted.length > 0 && (
                    <ul className="divide-y divide-border text-sm">
                        {unplotted.map((parcel) => (
                            <li
                                key={parcel.id}
                                className="flex items-baseline justify-between gap-2 py-1.5"
                            >
                                <span className="font-medium">
                                    {parcel.name}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {parcel.city ?? 'no location'} · needs
                                    coordinates
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
