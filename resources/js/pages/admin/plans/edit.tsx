import PlanForm from './form';
import { index } from '@/routes/plans';

type Plan = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    currency: string;
    price: number;
    properties_limit: number | null;
    units_limit: number | null;
    has_dedicated_db: boolean;
    has_custom_domain: boolean;
    has_email_notifications: boolean;
    has_sms_notifications: boolean;
    features: string[] | null;
    is_active: boolean;
    sort_order: number;
};

type Props = {
    plan: Plan;
};

export default function PlansEdit({ plan }: Props) {
    return <PlanForm plan={plan} />;
}

PlansEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Plans', href: index().url },
        { title: 'Edit plan', href: '' },
    ],
};
