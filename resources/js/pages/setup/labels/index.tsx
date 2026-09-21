import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import LabelDeleteModal from '@/components/setup/label-delete-modal';
import LabelFormModal from '@/components/setup/label-form-modal';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index } from '@/routes/setup/labels';
import type { Label, Paginated } from '@/types';

type Props = {
    labels: Paginated<Label>;
};

export default function LabelsIndex({ labels }: Props) {
    const [editingLabel, setEditingLabel] = useState<Label | null>(null);
    const [labelToDelete, setLabelToDelete] = useState<Label | null>(null);

    const refreshLabels = () => {
        router.reload({ only: ['labels'] });
    };

    return (
        <>
            <Head title="Labels" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Labels"
                        description="Manage tags that can be applied to tasks across the team."
                    />

                    <LabelFormModal onSaved={refreshLabels}>
                        <Button type="button" data-test="labels-create-button">
                            <Plus /> New label
                        </Button>
                    </LabelFormModal>
                </div>

                <div className="space-y-3">
                    {labels.data.map((label) => (
                        <div
                            key={label.id}
                            data-test="label-row"
                            className="flex items-center justify-between gap-4 rounded-lg border p-4"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span
                                        className="h-3 w-3 shrink-0 rounded-full border"
                                        style={{
                                            backgroundColor:
                                                label.color ?? '#6b7280',
                                        }}
                                    />
                                    <span className="font-medium">
                                        {label.name}
                                    </span>
                                </div>
                                {label.description ? (
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {label.description}
                                    </p>
                                ) : null}
                            </div>

                            <TooltipProvider>
                                <div className="flex items-center gap-2">
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                data-test="label-edit-button"
                                                onClick={() =>
                                                    setEditingLabel(label)
                                                }
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Edit label</p>
                                        </TooltipContent>
                                    </Tooltip>

                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                data-test="label-delete-button"
                                                onClick={() =>
                                                    setLabelToDelete(label)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Delete label</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                            </TooltipProvider>
                        </div>
                    ))}

                    {labels.data.length === 0 ? (
                        <p className="text-muted-foreground py-8 text-center">
                            No labels have been created yet.
                        </p>
                    ) : null}
                </div>

                {labels.last_page > 1 ? (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        {labels.links.map((link, linkIndex) =>
                            link.url ? (
                                <Button
                                    key={`${link.label}-${linkIndex}`}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link href={link.url} preserveState>
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    key={`${link.label}-${linkIndex}`}
                                    variant="outline"
                                    size="sm"
                                    disabled
                                >
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Button>
                            ),
                        )}
                    </div>
                ) : null}
            </div>

            <LabelFormModal
                open={editingLabel !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingLabel(null);
                    }
                }}
                label={editingLabel}
                onSaved={refreshLabels}
            />

            <LabelDeleteModal
                label={labelToDelete}
                open={labelToDelete !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setLabelToDelete(null);
                    }
                }}
                onDeleted={refreshLabels}
            />
        </>
    );
}

LabelsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Labels',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
