import { index } from '@/routes/platform/expenses';
import PlatformExpenseForm from './form';

export default function PlatformExpensesCreate() {
    return <PlatformExpenseForm />;
}

PlatformExpensesCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Platform expenses', href: index().url },
        { title: 'New expense', href: '' },
    ],
};
