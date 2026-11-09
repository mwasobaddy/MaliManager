import { Form, Head, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    SetupSidePanel,
    SetupStepsTopCard,
} from '@/components/setup-side-panel';
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
import type { SetupProgress } from '@/components/setup-steps';
import { becomeLandlord as storeLandlord, home } from '@/routes/guest';

/**
 * The first funnel step has no previous section, so its Back button
 * returns to wherever the user came from, falling back to home when
 * there is no history (fresh tab, direct link).
 */
function goToPreviousUrl() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit(home.url());
    }
}

type Props = {
    setupProgress: SetupProgress;
};

const currencies = ['KES', 'USD', 'UGX', 'TZS', 'RWF', 'NGN', 'GBP', 'EUR'];

/**
 * Landlord upgrade: the relocated half of the old signup branch. Everything
 * else about the user is already known, so this asks only for the organization
 * name, the currency, and whether vacant units should be listed publicly.
 * The organization starts on the free plan; plan choice moved to the Plan &
 * trial funnel step.
 */
export default function BecomeLandlord({ setupProgress }: Props) {
    const [currency, setCurrency] = useState('KES');
    const [listPublicly, setListPublicly] = useState(false);

    return (
        <>
            <Head title="Become a landlord" />

            <div className="mx-auto grid w-full max-w-7xl flex-1 grid-cols-7 gap-8 px-4 py-8">
                <SetupSidePanel
                    intro={[
                        "Let's get your rental business set up",
                        'Free up to 10 units · No card required',
                    ]}
                    current={1}
                    progress={setupProgress}
                />

                <div className="col-span-7 space-y-6 lg:col-span-4">
                    <SetupStepsTopCard current={1} progress={setupProgress} />

                    <div className="flex items-start justify-between gap-4">
                        <Heading
                            level="h1"
                            title="Become a landlord"
                            description="Set up your organization. You can add properties right after."
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={goToPreviousUrl}
                        >
                            <ArrowLeft className="size-4" />
                            Back
                        </Button>
                    </div>

                    <Form
                        {...storeLandlord.form()}
                        className="flex flex-col gap-6"
                    >
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
                                                message={
                                                    errors.organization_name
                                                }
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
                                            <InputError
                                                message={errors.currency}
                                            />
                                        </div>

                                        <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
                                            <Label
                                                htmlFor="list_publicly"
                                                className="flex-1 cursor-pointer"
                                            >
                                                <span className="block text-sm font-medium">
                                                    List vacant units publicly
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    Guests can discover them on
                                                    the home page. Individual
                                                    units can still be held
                                                    back.
                                                </span>
                                            </Label>
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

                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing && <Spinner />}
                                    Create my organization
                                </Button>
                            </>
                        )}
                    </Form>
                </div>
            </div>
        </>
    );
}
