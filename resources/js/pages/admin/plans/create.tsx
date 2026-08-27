import PlanForm from './form';
import { index } from '@/routes/plans';

export default function PlansCreate() {
    return <PlanForm />;
}

PlansCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Plans', href: index().url },
        { title: 'New plan', href: '' },
    ],
};
