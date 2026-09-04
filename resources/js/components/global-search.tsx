import {
    Building,
    Building2,
    DoorOpen,
    FileText,
    Loader,
    Map,
    Search,
    Users,
    User,
    Wrench,
    X,
} from 'lucide-react';
import * as React from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { search } from '@/routes';

type SearchResult = {
    id: number | string;
    type: string;
    title: string;
    subtitle?: string | null;
    url: string;
};

type GroupedResults = Record<string, SearchResult[]>;

const TYPE_META: Record<
    string,
    { label: string; icon: React.ComponentType<{ className?: string }> }
> = {
    users: { label: 'Users', icon: User },
    organizations: { label: 'Organizations', icon: Building2 },
    properties: { label: 'Properties', icon: Building },
    land_parcels: { label: 'Land parcels', icon: Map },
    units: { label: 'Units', icon: DoorOpen },
    occupants: { label: 'Occupants', icon: Users },
    leases: { label: 'Leases', icon: FileText },
    maintenance: { label: 'Maintenance', icon: Wrench },
};

const HISTORY_KEY = 'malimanager.global-search.history';

function loadHistory(): string[] {
    try {
        return JSON.parse(localStorage.getItem(HISTORY_KEY) ?? '[]');
    } catch {
        return [];
    }
}

function pushHistory(term: string) {
    const trimmed = term.trim();

    if (trimmed.length < 2) {
        return;
    }

    const next = [trimmed, ...loadHistory().filter((t) => t !== trimmed)].slice(
        0,
        8,
    );
    localStorage.setItem(HISTORY_KEY, JSON.stringify(next));
}

export function GlobalSearch() {
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const [results, setResults] = React.useState<GroupedResults>({});
    const [history, setHistory] = React.useState<string[]>([]);
    const [active, setActive] = React.useState(0);
    const [loading, setLoading] = React.useState(false);

    const inputRef = React.useRef<HTMLInputElement>(null);
    const abortRef = React.useRef<AbortController | null>(null);

    const flattened = React.useMemo(
        () => Object.values(results).flat(),
        [results],
    );

    const resetState = () => {
        setHistory(loadHistory());
        setQuery('');
        setResults({});
        setActive(0);
    };

    React.useEffect(() => {
        if (open) {
            setTimeout(() => inputRef.current?.focus(), 50);
        }
    }, [open]);

    React.useEffect(() => {
        const handle = setTimeout(() => {
            if (query.trim().length < 2) {
                setResults({});

                return;
            }

            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;

            setLoading(true);

            fetch(search.url({ query: { q: query.trim() } }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: controller.signal,
            })
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error(
                            `Search failed with status ${response.status}`,
                        );
                    }

                    const data = (await response.json()) as {
                        results: GroupedResults;
                    };

                    setResults(data.results ?? {});
                    setActive(0);
                })
                .catch((error: unknown) => {
                    if ((error as Error)?.name === 'AbortError') {
                        return;
                    }

                    setResults({});
                })
                .finally(() => {
                    if (!controller.signal.aborted) {
                        setLoading(false);
                    }
                });
        }, 300);

        return () => {
            clearTimeout(handle);
            abortRef.current?.abort();
        };
    }, [query]);

    const go = React.useCallback(
        (url: string) => {
            pushHistory(query);
            setOpen(false);
            window.location.href = url;
        },
        [query],
    );

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActive((i) =>
                Math.min(i + 1, Math.max(flattened.length - 1, 0)),
            );
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((i) => Math.max(i - 1, 0));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const hit = flattened[active];

            if (hit) {
                go(hit.url);
            }
        }
    };

    const showHistory = query.trim().length < 2;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    resetState();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="group h-9 w-9 cursor-pointer"
                    aria-label="Search"
                >
                    <Search className="!size-5 opacity-80 group-hover:opacity-100" />
                </Button>
            </DialogTrigger>
            <DialogContent
                className="top-[20%] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-xl"
                showCloseButton={false}
            >
                <DialogTitle className="sr-only">Search</DialogTitle>
                <div className="flex items-center gap-2 border-b px-3">
                    <Search className="size-4 shrink-0 opacity-60" />
                    <Input
                        ref={inputRef}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder="Search users, properties, leases…"
                        className="h-12 border-0 px-0 shadow-none focus-visible:ring-0"
                    />
                    {loading && (
                        <Loader className="size-4 animate-spin opacity-60" />
                    )}
                    <DialogTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
                            aria-label="Close"
                        >
                            <X className="size-4" />
                        </Button>
                    </DialogTrigger>
                </div>

                <div className="max-h-80 overflow-y-auto p-2">
                    {showHistory && history.length > 0 && (
                        <div className="space-y-1">
                            <p className="px-2 py-1 text-xs font-medium text-muted-foreground">
                                Recent
                            </p>
                            {history.map((term) => (
                                <button
                                    key={term}
                                    type="button"
                                    onClick={() => setQuery(term)}
                                    className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent"
                                >
                                    <Search className="size-4 opacity-60" />
                                    {term}
                                </button>
                            ))}
                        </div>
                    )}

                    {!showHistory && flattened.length === 0 && !loading && (
                        <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                            No results found.
                        </p>
                    )}

                    {!showHistory &&
                        Object.entries(results).map(([type, items]) => {
                            const meta = TYPE_META[type] ?? {
                                label: type,
                                icon: Search,
                            };
                            const Icon = meta.icon;

                            return (
                                <div key={type} className="mb-2">
                                    <p className="px-2 py-1 text-xs font-medium text-muted-foreground">
                                        {meta.label}
                                    </p>
                                    {items.map((item) => {
                                        const index = flattened.indexOf(item);
                                        const isActive = index === active;

                                        return (
                                            <button
                                                key={`${type}-${item.id}`}
                                                type="button"
                                                onMouseEnter={() =>
                                                    setActive(index)
                                                }
                                                onClick={() => go(item.url)}
                                                className={cn(
                                                    'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm',
                                                    isActive
                                                        ? 'bg-accent'
                                                        : 'hover:bg-accent/60',
                                                )}
                                            >
                                                <Icon className="size-4 shrink-0 opacity-70" />
                                                <span className="flex-1 truncate">
                                                    {item.title}
                                                </span>
                                                {item.subtitle && (
                                                    <span className="truncate text-xs text-muted-foreground">
                                                        {item.subtitle}
                                                    </span>
                                                )}
                                            </button>
                                        );
                                    })}
                                </div>
                            );
                        })}
                </div>
            </DialogContent>
        </Dialog>
    );
}
