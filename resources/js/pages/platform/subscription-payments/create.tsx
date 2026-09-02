import { index } from '@/routes/platform/subscription-payments';
import SubscriptionPaymentForm from './form';

type Option = {
    value: number;
    label: string;
};

type Props = {
    organizations?: Option[];
    plans?: Option[];
};

export default function SubscriptionPaymentsCreate({
    organizations,
    plans,
}: Props) {
    return (
        <SubscriptionPaymentForm organizations={organizations} plans={plans} />
    );
}

SubscriptionPaymentsCreate.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Subscription payments', href: index().url },
        { title: 'New payment', href: '' },
    ],
};
