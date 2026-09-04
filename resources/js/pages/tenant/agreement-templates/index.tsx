import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import agreementTemplates from '@/routes/tenant/agreement-templates';

type Template = {
    id: number;
    name: string;
    body_html: string;
    created_at: string;
};

type Props = {
    scope: 'organization' | 'property';
    propertySlug?: string;
    templates: Template[];
};

export default function AgreementTemplatesIndex({
    scope,
    propertySlug,
    templates,
}: Props) {
    const back =
        scope === 'organization'
            ? agreementTemplates.orgIndex()
            : agreementTemplates.propertyIndex({
                  property: propertySlug ?? '',
              });
    const createHref =
        scope === 'organization'
            ? agreementTemplates.create().url
            : agreementTemplates.propertyCreate({
                  property: propertySlug ?? '',
              }).url;

    return (
        <>
            <Head title="Agreement templates" />
            <div className="mb-2">
                <Link
                    href={back.url}
                    className="text-sm text-muted-foreground underline"
                >
                    ← Back
                </Link>
            </div>
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        title="Agreement templates"
                        description="Reusable lease agreements with {{placeholders}} that auto-fill from lease data."
                    />
                    <Button asChild>
                        <Link href={createHref}>
                            <Plus className="size-4" />
                            New template
                        </Link>
                    </Button>
                </div>

                {templates.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No templates yet. Create one to reuse it across
                        occupants.
                    </p>
                ) : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-xs tracking-wide text-muted-foreground uppercase">
                                <th className="px-3 py-2">Name</th>
                                <th className="px-3 py-2">Preview</th>
                                <th className="px-3 py-2 text-right">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {templates.map((template) => (
                                <tr
                                    key={template.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="px-3 py-2 font-medium">
                                        {template.name}
                                    </td>
                                    <td className="max-w-md truncate px-3 py-2 text-muted-foreground">
                                        {template.body_html
                                            .replace(/<[^>]+>/g, ' ')
                                            .slice(0, 120)}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                            >
                                                <Link
                                                    href={
                                                        scope === 'organization'
                                                            ? agreementTemplates.edit(
                                                                  {
                                                                      template:
                                                                          template.id,
                                                                  },
                                                              )
                                                            : agreementTemplates.propertyEdit(
                                                                  {
                                                                      property:
                                                                          propertySlug ??
                                                                          '',
                                                                      template:
                                                                          template.id,
                                                                  },
                                                              )
                                                    }
                                                >
                                                    <Pencil className="size-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="sm"
                                                className="text-red-600"
                                            >
                                                <Link
                                                    href={
                                                        scope === 'organization'
                                                            ? agreementTemplates.destroy(
                                                                  {
                                                                      template:
                                                                          template.id,
                                                                  },
                                                              )
                                                            : agreementTemplates.propertyDestroy(
                                                                  {
                                                                      property:
                                                                          propertySlug ??
                                                                          '',
                                                                      template:
                                                                          template.id,
                                                                  },
                                                              )
                                                    }
                                                    method="delete"
                                                >
                                                    <Trash2 className="size-4" />
                                                </Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </>
    );
}
