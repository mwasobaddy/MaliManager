import {
    FileText,
    History,
    MoreHorizontal,
    Plus,
    Power,
    SquarePen,
} from 'lucide-react';
import { ActionIconButton } from '@/components/action-icon-button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

type OccupantActionsMenuProps = {
    /** Accessible name, e.g. "More actions for Brian". */
    label: string;
    showViewAgreement: boolean;
    showAmend: boolean;
    showAddCharge: boolean;
    showEndLease: boolean;
    onHistory: () => void;
    onViewAgreement: () => void;
    onAmend: () => void;
    onAddCharge: () => void;
    onEndLease: () => void;
};

/**
 * The one overflow menu for occupant actions, shared by the table row
 * and the view sheet so the same items, icons and order appear in both
 * places. Row and sheet keep their own inline quick actions — this is
 * the complete list for anywhere space runs out.
 */
export function OccupantActionsMenu({
    label,
    showViewAgreement,
    showAmend,
    showAddCharge,
    showEndLease,
    onHistory,
    onViewAgreement,
    onAmend,
    onAddCharge,
    onEndLease,
}: OccupantActionsMenuProps) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <ActionIconButton
                    icon={<MoreHorizontal />}
                    label={label}
                    color="gray"
                />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={onHistory}>
                    <History className="size-4" />
                    History
                </DropdownMenuItem>
                {showViewAgreement && (
                    <DropdownMenuItem onSelect={onViewAgreement}>
                        <FileText className="size-4" />
                        View agreement
                    </DropdownMenuItem>
                )}
                {showAmend && (
                    <DropdownMenuItem onSelect={onAmend}>
                        <SquarePen className="size-4" />
                        Amend rent
                    </DropdownMenuItem>
                )}
                {showAddCharge && (
                    <DropdownMenuItem onSelect={onAddCharge}>
                        <Plus className="size-4" />
                        Add charge
                    </DropdownMenuItem>
                )}
                {showEndLease && (
                    <DropdownMenuItem
                        className="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                        onSelect={onEndLease}
                    >
                        <Power className="size-4" />
                        End lease
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
