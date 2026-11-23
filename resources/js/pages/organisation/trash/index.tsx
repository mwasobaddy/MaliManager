import { Head, Link, router } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ActionIconButton } from '@/components/action-icon-button';
import ConfirmDeleteOverlay from '@/components/confirm-delete-overlay';
import Heading from '@/components/heading';
import { Card } from '@/components/ui/card';
import { DataTable } from '@/components/data-table';
import { Empty } from '@/components/ui/empty';
import { Pagination } from '@/components/ui/pagination';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format-date';
import {
    empty as trashEmpty,
    force as trashForce,
    index as trashIndex,
    restore as trashRestore,
} from '@/routes/tenant/trash';
import type { ColumnDef } from '@tanstack/react-table';

type TrashRow = {
    id: number;
    display: string;
    deleted_at: string | null;
};

type Module = {
    key: string;
    label: string;
    count: number;
};

type Paginator = {
    data: TrashRow[];
    current_page: number;
    last_page: number;
    total: number;
};

type Props = {
    modules: Module[];
    activeModule: string | null;
    items: Paginator | null;
};

type Pending =
    | { type: 'restore'; ids: number[] }
    | { type: 'force'; ids: number[] }
    | { type: 'empty' };

/**
 * Organization trash: soft-deleted rows grouped by module, with restore
 * and permanent delete. Tier-scoped server-side — admins and owners see
 * the whole organization, everyone else sees only rows they created —
 * so an empty module list here means "nothing you may touch", not
 * "nothing was deleted".
 */
export default function TrashIndex({ modules, activeModule, items }: Props) {
    const [pending, setPending] = useState<Pending | null>(null);

    const active =
        modules.find((module) => module.key === activeModule) ?? null;
    const total = modules.reduce((sum, module) => sum + module.count, 0);

    const columns: ColumnDef<TrashRow>[] = [
        {
            accessorKey: 'display',
            header: 'Row',
            cell: ({ row }) => (
                <span className="font-medium">{row.original.display}</span>
            ),
        },
        {
            accessorKey: 'deleted_at',
            header: 'Deleted',
            cell: ({ row }) =>
                row.original.deleted_at
                    ? formatDateTime(row.original.deleted_at)
                    : '—',
        },
        {
            id: 'actions',
            header: 'Actions',
            cell: ({ row }) => (
                <div
                    className="flex justify-end gap-1"
                    onClick={(event) => event.stopPropagation()}
                >
                    <ActionIconButton
                        icon={<RotateCcw />}
                        label={`Restore ${row.original.display}`}
                        color="accent"
                        onClick={() =>
                            setPending({
                                type: 'restore',
                                ids: [row.original.id],
                            })
                        }
                    />
                    <ActionIconButton
                        icon={<Trash2 />}
                        label={`Delete ${row.original.display} forever`}
                        color="danger"
                        onClick={() =>
                            setPending({
                                type: 'force',
                                ids: [row.original.id],
                            })
                        }
                    />
                </div>
            ),
        },
    ];

    function changePage(nextPage: number) {
        router.get(
            trashIndex().url,
            { module: activeModule ?? undefined, page: nextPage },
            { preserveState: true, replace: true },
        );
    }

    const restoreCount = pending?.type === 'restore' ? pending.ids.length : 0;
    const forceCount = pending?.type === 'force' ? pending.ids.length : 0;

    const dialog =
        pending?.type === 'restore'
            ? {
                  method: 'post' as const,
                  action: trashRestore().url,
                  title:
                      restoreCount === 1
                          ? 'Restore this row?'
                          : `Restore ${restoreCount} rows?`,
                  description:
                      'The row returns to its list. Team access for staff logins needs a fresh invite.',
                  payload: { module: activeModule, ids: pending.ids },
                  submitLabel: 'Restore',
                  processingLabel: 'Restoring…',
                  danger: false,
              }
            : pending?.type === 'force'
              ? {
                    method: 'delete' as const,
                    action: trashForce().url,
                    title:
                        forceCount === 1
                            ? 'Delete this row forever?'
                            : `Delete ${forceCount} rows forever?`,
                    description:
                        'This cannot be undone. The row leaves the trash permanently.',
                    payload: { module: activeModule, ids: pending.ids },
                    submitLabel: 'Delete forever',
                    processingLabel: 'Deleting…',
                    danger: true,
                }
              : pending?.type === 'empty'
                ? {
                      method: 'delete' as const,
                      action: trashEmpty().url,
                      title: `Empty ${active?.label ?? 'trash'}?`,
                      description: `Every trashed ${(active?.label ?? 'row').toLowerCase()} in your scope is permanently deleted. This cannot be undone.`,
                      payload: { module: activeModule },
                      submitLabel: 'Empty trash',
                      processingLabel: 'Emptying…',
                      danger: true,
                  }
                : null;

    return (
        <>
            <Head title="Trash" />

            <div className="space-y-6">
                <Heading
                    title="Trash"
                    description="Soft-deleted rows, grouped by module. Restore what was removed by mistake, or delete it forever."
                />

                {modules.length === 0 || total === 0 ? (
                    <Card className="p-6">
                        <Empty
                            icon={<Trash2 className="size-6" />}
                            title="Trash is empty"
                            description="Deleted rows land here first, so nothing disappears by accident."
                        />
                    </Card>
                ) : (
                    <>
                        <div className="flex flex-wrap gap-2">
                            {modules.map((module) => (
                                <Link
                                    key={module.key}
                                    href={
                                        trashIndex({
                                            query: { module: module.key },
                                        }).url
                                    }
                                    className={
                                        module.key === activeModule
                                            ? 'rounded-full bg-primary px-3 py-1 text-sm font-medium text-primary-foreground'
                                            : 'rounded-full border px-3 py-1 text-sm text-muted-foreground hover:bg-muted'
                                    }
                                >
                                    {module.label} · {module.count}
                                </Link>
                            ))}
                        </div>

                        <Card>
                            <div className="flex items-center justify-between gap-2 border-b px-4 py-3">
                                <p className="text-sm text-muted-foreground">
                                    {items?.total ?? 0} trashed{' '}
                                    {active?.label.toLowerCase() ?? 'rows'}
                                </p>
                                {(items?.total ?? 0) > 0 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        className="text-status-danger hover:bg-status-danger-bg"
                                        onClick={() =>
                                            setPending({ type: 'empty' })
                                        }
                                    >
                                        Empty trash
                                    </Button>
                                )}
                            </div>
                            <DataTable
                                columns={columns}
                                data={items?.data ?? []}
                                getRowId={(row) => String(row.id)}
                                empty={`No trashed ${active?.label.toLowerCase() ?? 'rows'}.`}
                            />
                        </Card>

                        {items && items.last_page > 1 && (
                            <Pagination
                                currentPage={items.current_page}
                                lastPage={items.last_page}
                                total={items.total}
                                label={active?.label.toLowerCase() ?? 'rows'}
                                onChange={changePage}
                            />
                        )}
                    </>
                )}

                {dialog && activeModule && (
                    <ConfirmDeleteOverlay
                        open
                        onOpenChange={(open) => {
                            if (!open) {
                                setPending(null);
                            }
                        }}
                        method={dialog.method}
                        action={dialog.action}
                        payload={dialog.payload}
                        title={dialog.title}
                        description={dialog.description}
                        submitLabel={dialog.submitLabel}
                        processingLabel={dialog.processingLabel}
                        danger={dialog.danger}
                        closeOnSuccess
                    />
                )}
            </div>
        </>
    );
}

TrashIndex.layout = {
    breadcrumbs: [{ title: 'Trash' }],
};
