import { index } from '@/routes/platform/expenses';
import PlatformExpenseForm from './form';

type Expense = {
    id: number;
    category: string;
    amount: number;
    currency: string;
    spent_on: string;
    vendor_name: string | null;
    notes: string | null;
};

type Props = {
    expense: Expense;
};

export default function PlatformExpensesEdit({ expense }: Props) {
    return <PlatformExpenseForm expense={expense} />;
}

PlatformExpensesEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Platform expenses', href: index().url },
        { title: 'Edit expense', href: '' },
    ],
};
