import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Pagination } from '@/components/patterns/pagination';
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

            <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 2xl:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
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

                <div className="bg-card divide-y overflow-hidden rounded-lg border">
                    {labels.data.map((label) => (
                        <div
                            key={label.id}
                            data-test="label-row"
                            className="hover:bg-accent/40 flex items-center justify-between gap-4 px-4 py-3.5 transition-colors"
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
                                                size="icon-sm"
                                                aria-label="Edit label"
                                                data-test="label-edit-button"
                                                onClick={() =>
                                                    setEditingLabel(label)
                                                }
                                            >
                                                <Pencil />
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
                                                size="icon-sm"
                                                aria-label="Delete label"
                                                data-test="label-delete-button"
                                                onClick={() =>
                                                    setLabelToDelete(label)
                                                }
                                            >
                                                <Trash2 />
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
                        <p className="text-muted-foreground px-4 py-10 text-center text-sm">
                            No labels have been created yet.
                        </p>
                    ) : null}
                </div>

                <Pagination paginator={labels} />
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
