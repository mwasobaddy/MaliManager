import { Form, Head } from '@inertiajs/react';
import { BadgeCheck, Smartphone, Sparkles } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SetupSteps } from '@/components/setup-steps';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { becomeLandlord as storeLandlord } from '@/routes/guest';

type Plan = {
    name: string;
    slug: string;
    description: string;
    price: number;
    currency: string;
    price_label: string;
};

type Props = {
    plans: Plan[];
};

const currencies = ['KES', 'USD', 'UGX', 'TZS', 'RWF', 'NGN', 'GBP', 'EUR'];

/**
 * Landlord upgrade: the relocated half of the old signup branch. Everything
 * else about the user is already known, so this asks only for the organization
 * name, the currency, a plan, and whether vacant units should be listed
 * publicly.
 */
export default function BecomeLandlord({ plans }: Props) {
    const [currency, setCurrency] = useState('KES');
    const [listPublicly, setListPublicly] = useState(false);
    const [planSlug, setPlanSlug] = useState(plans[0]?.slug ?? '');

    return (
        <>
            <Head title="Become a landlord" />

            <div className="mx-auto max-w-2xl space-y-6 p-4 md:p-6">
                <SetupSteps current={1} />

                <Heading
                    level="h1"
                    title="Become a landlord"
                    description="Set up your organization. You can add properties right after."
                />

                <Form {...storeLandlord.form()} className="flex flex-col gap-6">
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base font-medium">
                                        Organization
                                    </CardTitle>
                                    <CardDescription>
                                        The business tenants will see.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="organization_name">
                                            Organization name
                                        </Label>
                                        <Input
                                            id="organization_name"
                                            name="organization_name"
                                            required
                                        />
                                        <InputError
                                            message={errors.organization_name}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="currency">
                                            Currency
                                        </Label>
                                        <input
                                            type="hidden"
                                            name="currency"
                                            value={currency}
                                        />
                                        <Select
                                            value={currency}
                                            onValueChange={setCurrency}
                                        >
                                            <SelectTrigger id="currency">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {currencies.map((code) => (
                                                    <SelectItem
                                                        key={code}
                                                        value={code}
                                                    >
                                                        {code}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.currency} />
                                    </div>

                                    <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
                                        <div>
                                            <p className="text-sm font-medium">
                                                List vacant units publicly
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                Guests can discover them on the
                                                home page. Individual units can
                                                still be held back.
                                            </p>
                                        </div>
                                        <Checkbox
                                            id="list_publicly"
                                            checked={listPublicly}
                                            onCheckedChange={(checked) =>
                                                setListPublicly(
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <input
                                            type="hidden"
                                            name="list_publicly"
                                            value={listPublicly ? '1' : '0'}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base font-medium">
                                        Plan
                                    </CardTitle>
                                    <CardDescription>
                                        You can change this later.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <input
                                        type="hidden"
                                        name="plan_slug"
                                        value={planSlug}
                                    />
                                    <div
                                        className="grid gap-3"
                                        role="radiogroup"
                                        aria-label="Plan"
                                    >
                                        {plans.map((plan) => (
                                            <button
                                                key={plan.slug}
                                                type="button"
                                                role="radio"
                                                aria-checked={
                                                    planSlug === plan.slug
                                                }
                                                onClick={() =>
                                                    setPlanSlug(plan.slug)
                                                }
                                                className={`rounded-lg border p-4 text-left transition-colors ${
                                                    planSlug === plan.slug
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-input hover:bg-muted'
                                                }`}
                                            >
                                                <div className="flex items-center justify-between">
                                                    <span className="font-medium">
                                                        {plan.name}
                                                    </span>
                                                    <span className="text-sm text-muted-foreground">
                                                        {plan.price_label}
                                                    </span>
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {plan.description}
                                                </p>
                                            </button>
                                        ))}
                                    </div>
                                    <InputError message={errors.plan_slug} />
                                </CardContent>
                            </Card>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing || planSlug === ''}
                            >
                                {processing && <Spinner />}
                                Create my organization
                            </Button>

                            <ul className="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm text-muted-foreground">
                                <li className="flex items-center gap-1.5">
                                    <Sparkles
                                        className="size-4 text-brand-accent"
                                        aria-hidden
                                    />
                                    Free up to 10 units
                                </li>
                                <li className="flex items-center gap-1.5">
                                    <Smartphone
                                        className="size-4 text-brand-accent"
                                        aria-hidden
                                    />
                                    Collect rent via M-Pesa
                                </li>
                                <li className="flex items-center gap-1.5">
                                    <BadgeCheck
                                        className="size-4 text-brand-accent"
                                        aria-hidden
                                    />
                                    No card required
                                </li>
                            </ul>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
