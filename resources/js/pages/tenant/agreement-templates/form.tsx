import { Head, Link, useForm } from "@inertiajs/react";
import { Save } from 'lucide-react';
import { useState } from "react";
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import agreementTemplates from '@/routes/tenant/agreement-templates';

type Template = {
    id: number;
    name: string;
    body_html: string;
    document_name?: string | null;
    document_url?: string | null;
};

type Props = {
    scope: 'organization' | 'property';
    propertySlug?: string;
    template: Template | null;
};

export default function AgreementTemplateForm({ scope, propertySlug, template }: Props) {
    const form = useForm<{
        name: string;
        body_html: string;
        agreement_document?: File | null;
    }>({
        name: template?.name ?? '',
        body_html: template?.body_html ?? '<p></p>',
        agreement_document: null,
    });

    const [agreementText, setAgreementText] = useState(template?.body_html ?? '<p></p>');

    const submit = () => {
        form.setData('body_html', agreementText);

        if (scope === 'property') {
            if (template) {
                form.put(agreementTemplates.propertyUpdate({ property: propertySlug ?? '', template: template.id }).url);
            } else {
                form.post(agreementTemplates.propertyStore({ property: propertySlug ?? '' }).url);
            }

            return;
        }

        if (template) {
            form.put(agreementTemplates.update({ template: template.id }).url);
        } else {
            form.post(agreementTemplates.store().url);
        }
    };

    return (
        <>
            <Head title={template ? `Edit ${template.name}` : 'New agreement template'} />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="small"
                    title={template ? `Edit ${template.name}` : 'New agreement template'}
                    description="Write the agreement once. Placeholders auto-fill from each lease."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Template details</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Template name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="Standard residential lease"
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="body">Agreement body</Label>
                            <RichTextEditor
                                name="body_html_preview_only"
                                value={agreementText}
                                onChange={(html) => {
                                    setAgreementText(html);
                                    form.setData('body_html', html);
                                }}
                                placeholder="Write the template. Placeholders like {{occupant_name}} are replaced per lease."
                            />
                            <InputError message={form.errors.body_html} />
                            {template?.document_url && (
                                <div className="flex items-center gap-3 text-sm">
                                    <a
                                        href={template.document_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-primary underline"
                                    >
                                        {template.document_name ?? 'Uploaded template document'}
                                    </a>
                                    <label className="flex items-center gap-1 text-muted-foreground">
                                        <input
                                            type="checkbox"
                                            name="remove_agreement_document"
                                            value="1"
                                        />
                                        Remove
                                    </label>
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="agreement_document">
                                    {template?.document_url
                                        ? 'Replace document (PDF/DOCX, max 10MB)'
                                        : 'Attach a ready-made document (PDF/DOCX, max 10MB)'}
                                </Label>
                                <Input
                                    id="agreement_document"
                                    type="file"
                                    accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                    onChange={(e) =>
                                        form.setData(
                                            'agreement_document',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={form.errors.agreement_document} />
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Placeholders:{' '}
                                <code>{'{{occupant_name}}'}</code>,{' '}
                                <code>{'{{property_name}}'}</code>,{' '}
                                <code>{'{{unit_name}}'}</code>,{' '}
                                <code>{'{{rent_amount}}'}</code>,{' '}
                                <code>{'{{deposit_amount}}'}</code>,{' '}
                                <code>{'{{currency}}'}</code>,{' '}
                                <code>{'{{start_date}}'}</code>,{' '}
                                <code>{'{{end_date}}'}</code>,{' '}
                                <code>{'{{organization_name}}'}</code>
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex gap-2">
                    <Button asChild variant="outline">
                        <Link href={scope === 'organization'
                            ? agreementTemplates.orgIndex()
                            : agreementTemplates.propertyIndex({ property: propertySlug ?? '' })}>Back to templates</Link>
                    </Button>
                    <Button onClick={submit} disabled={form.processing}>
                        <Save className="size-4" />
                        {template ? 'Save changes' : 'Create template'}
                    </Button>
                </div>
            </div>
        </>
    );
}
