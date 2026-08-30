import { index } from '@/routes/platform/plans';
import PlanForm from './form';

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
