import { Form, Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { resend, verify } from '@/routes/login/otp';

const CODE_LENGTH = 6;
const RESEND_COOLDOWN_SECONDS = 90;
const COOLDOWN_KEY = 'malimanager.otp-cooldown';

type Props = {
    identifier: string;
    channel: 'email' | 'sms';
    masked: string;
    /** Kept for backwards compatibility; mirrors identifier for email logins. */
    email?: string | null;
    status?: string;
};

/**
 * Reads the persisted resend deadline for this contact. Returns 0 when
 * there is none, it belongs to a different login, it already passed, or
 * storage is unavailable — all of which simply mean "no cooldown".
 */
function readCooldown(identifier: string): number {
    try {
        const raw = localStorage.getItem(COOLDOWN_KEY);

        if (!raw) {
            return 0;
        }

        const parsed = JSON.parse(raw) as {
            identifier?: unknown;
            availableAt?: unknown;
        };

        if (
            parsed.identifier !== identifier ||
            typeof parsed.availableAt !== 'number'
        ) {
            return 0;
        }

        return parsed.availableAt;
    } catch {
        return 0;
    }
}

function writeCooldown(identifier: string, availableAt: number): void {
    try {
        localStorage.setItem(
            COOLDOWN_KEY,
            JSON.stringify({ identifier, availableAt }),
        );
    } catch {
        // Private browsing etc: the timer just won't survive a refresh.
    }
}

function clearCooldown(): void {
    try {
        localStorage.removeItem(COOLDOWN_KEY);
    } catch {
        // Nothing to clean up.
    }
}

function formatRemaining(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

/**
 * Shows just enough to confirm where the code went. The server masks the
 * contact; this falls back to the raw value when it is absent (e.g. a
 * stale cached page) so the screen never goes blank.
 */
function maskContact(masked: string | undefined, identifier: string): string {
    return masked && masked.length > 0 ? masked : identifier;
}

export default function Otp({ identifier, channel, masked, status }: Props) {
    const [digits, setDigits] = useState<string[]>(() =>
        Array<string>(CODE_LENGTH).fill(''),
    );
    const [cooldownUntil, setCooldownUntil] = useState<number>(() =>
        readCooldown(identifier),
    );
    const [now, setNow] = useState<number>(() => Date.now());
    const boxes = useRef<Array<HTMLInputElement | null>>([]);

    // The arrival on this page always follows a fresh send from the login
    // form, so a visit with no (valid) stored deadline starts one. A stored
    // future deadline — e.g. after a hard refresh — is resumed untouched.
    useEffect(() => {
        if (cooldownUntil > Date.now()) {
            return;
        }

        const fresh = Date.now() + RESEND_COOLDOWN_SECONDS * 1000;
        writeCooldown(identifier, fresh);
        setCooldownUntil(fresh);
    }, [identifier, cooldownUntil]);

    useEffect(() => {
        if (cooldownUntil <= Date.now()) {
            return;
        }

        const id = window.setInterval(() => setNow(Date.now()), 500);

        return () => window.clearInterval(id);
    }, [cooldownUntil]);

    const remainingSeconds = Math.max(
        0,
        Math.ceil((cooldownUntil - now) / 1000),
    );

    const focusBox = (index: number): void => {
        const box = boxes.current[index];

        if (box) {
            box.focus();
            box.select();
        }
    };

    const setDigit = (index: number, value: string): void => {
        setDigits((previous) => {
            if (previous[index] === value) {
                return previous;
            }

            const next = [...previous];
            next[index] = value;

            return next;
        });
    };

    const handleChange = (index: number, raw: string): void => {
        const cleaned = raw.replace(/\D/g, '');

        if (cleaned === '') {
            setDigit(index, '');

            return;
        }

        // A full paste (or autofill) landing in one box still fills from here.
        const chars = cleaned.split('');

        if (chars.length > 1) {
            setDigits((previous) => {
                const next = [...previous];

                for (
                    let offset = 0;
                    offset < chars.length && index + offset < CODE_LENGTH;
                    offset++
                ) {
                    next[index + offset] = chars[offset];
                }

                return next;
            });
            focusBox(Math.min(index + chars.length, CODE_LENGTH - 1));

            return;
        }

        setDigit(index, chars[chars.length - 1]);
        focusBox(Math.min(index + 1, CODE_LENGTH - 1));
    };

    const handleKeyDown = (
        index: number,
        event: React.KeyboardEvent<HTMLInputElement>,
    ): void => {
        if (event.key === 'Backspace') {
            event.preventDefault();

            if (digits[index] !== '') {
                setDigit(index, '');
            } else if (index > 0) {
                setDigit(index - 1, '');
                focusBox(index - 1);
            }

            return;
        }

        if (event.key === 'Delete') {
            event.preventDefault();
            setDigit(index, '');

            return;
        }

        if (event.key === 'ArrowLeft' && index > 0) {
            event.preventDefault();
            focusBox(index - 1);

            return;
        }

        if (event.key === 'ArrowRight' && index < CODE_LENGTH - 1) {
            event.preventDefault();
            focusBox(index + 1);
        }
    };

    const handlePaste = (
        event: React.ClipboardEvent<HTMLInputElement>,
    ): void => {
        const text = event.clipboardData.getData('text').replace(/\D/g, '');

        if (text === '') {
            return;
        }

        event.preventDefault();

        const chars = text.split('').slice(0, CODE_LENGTH);
        setDigits((previous) => {
            const next = [...previous];

            chars.forEach((char, offset) => {
                next[offset] = char;
            });

            return next;
        });
        focusBox(Math.min(chars.length, CODE_LENGTH - 1));
    };

    const restartCooldown = (): void => {
        const availableAt = Date.now() + RESEND_COOLDOWN_SECONDS * 1000;
        writeCooldown(identifier, availableAt);
        setCooldownUntil(availableAt);
        setNow(Date.now());
    };

    const code = digits.join('');
    const complete = code.length === CODE_LENGTH;
    const viaSms = channel === 'sms';

    return (
        <>
            <Head title="Enter your code" />

            <div className="space-y-6">
                <div className="space-y-1">
                    <h2 className="text-lg font-medium">
                        {viaSms ? 'Check your texts' : 'Check your email'}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        We sent a one-time code to{' '}
                        <strong>{maskContact(masked, identifier)}</strong>.
                    </p>
                </div>

                <Form
                    {...verify.form()}
                    onSuccess={clearCooldown}
                    className="flex flex-col gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <fieldset className="grid gap-2">
                                <legend className="text-sm leading-none font-medium">
                                    One-time code
                                </legend>
                                <div
                                    className="flex justify-center gap-2"
                                    onPaste={handlePaste}
                                >
                                    {digits.map((digit, index) => (
                                        <Input
                                            key={index}
                                            ref={(element) => {
                                                boxes.current[index] = element;
                                            }}
                                            value={digit}
                                            onChange={(event) =>
                                                handleChange(
                                                    index,
                                                    event.target.value,
                                                )
                                            }
                                            onKeyDown={(event) =>
                                                handleKeyDown(index, event)
                                            }
                                            onFocus={(event) =>
                                                event.target.select()
                                            }
                                            inputMode="numeric"
                                            autoComplete={
                                                index === 0
                                                    ? 'one-time-code'
                                                    : 'off'
                                            }
                                            aria-label={`Digit ${index + 1} of ${CODE_LENGTH}`}
                                            maxLength={CODE_LENGTH}
                                            disabled={processing}
                                            data-test={`code-digit-${index + 1}`}
                                            className="h-12 w-12 text-center text-lg tabular-nums"
                                        />
                                    ))}
                                </div>
                                {/* The six boxes are the UI; this carries the joined value. */}
                                <input
                                    type="hidden"
                                    name="code"
                                    value={code}
                                    readOnly
                                />
                                {code.length > 0 && !complete ? (
                                    <p
                                        className="text-sm text-destructive"
                                        data-test="code-incomplete"
                                    >
                                        Code must be {CODE_LENGTH} digits.
                                    </p>
                                ) : (
                                    <InputError message={errors.code} />
                                )}
                            </fieldset>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing || !complete}
                                data-test="verify-button"
                            >
                                {processing && <Spinner />}
                                Verify
                            </Button>
                        </>
                    )}
                </Form>

                {status && (
                    <div className="text-center text-sm font-medium text-status-success">
                        {status}
                    </div>
                )}

                <div className="flex items-center justify-between text-sm">
                    <Form
                        {...resend.form()}
                        onSuccess={restartCooldown}
                        className="inline"
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="link"
                                className="h-auto p-0"
                                disabled={processing || remainingSeconds > 0}
                                data-test="resend-button"
                            >
                                {processing
                                    ? 'Sending…'
                                    : remainingSeconds > 0
                                      ? `Resend code in ${formatRemaining(remainingSeconds)}`
                                      : 'Resend code'}
                            </Button>
                        )}
                    </Form>
                    <Link
                        href={login()}
                        className="text-muted-foreground hover:text-foreground"
                    >
                        Use a different login
                    </Link>
                </div>
            </div>
        </>
    );
}

Otp.layout = {
    title: 'Enter your code',
    description: 'Verify your login to continue',
};
