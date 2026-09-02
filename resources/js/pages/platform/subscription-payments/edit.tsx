import { index } from '@/routes/platform/subscription-payments';
import SubscriptionPaymentForm from './form';

type Option = {
    value: number;
    label: string;
};

type Payment = {
    id: number;
    organization_id: number | null;
    plan_id: number | null;
    amount: number;
    currency: string;
    received_on: string;
    period_start: string | null;
    period_end: string | null;
    method: string | null;
    reference: string | null;
    notes: string | null;
};

type Props = {
    payment: Payment;
    organizations?: Option[];
    plans?: Option[];
};

export default function SubscriptionPaymentsEdit({
    payment,
    organizations,
    plans,
}: Props) {
    return (
        <SubscriptionPaymentForm
            payment={payment}
            organizations={organizations}
            plans={plans}
        />
    );
}

SubscriptionPaymentsEdit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Subscription payments', href: index().url },
        { title: 'Edit payment', href: '' },
    ],
};
