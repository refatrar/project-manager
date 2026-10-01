import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Pagination } from '@/components/patterns/pagination';
import Heading from '@/components/heading';
import ScopeDeleteModal from '@/components/setup/scope-delete-modal';
import ScopeFormModal from '@/components/setup/scope-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { index } from '@/routes/setup/scopes';
import type { Paginated, Scope, ScopeStatusOption } from '@/types';

type Props = {
    scopes: Paginated<Scope>;
    statusOptions: ScopeStatusOption[];
};

export default function ScopesIndex({ scopes, statusOptions }: Props) {
    const [editingScope, setEditingScope] = useState<Scope | null>(null);
    const [scopeToDelete, setScopeToDelete] = useState<Scope | null>(null);

    const refreshScopes = () => {
        router.reload({ only: ['scopes'] });
    };

    return (
        <>
            <Head title="Scopes" />

            <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 2xl:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Scopes"
                        description="Manage reusable scopes for projects and modules."
                    />

                    <ScopeFormModal
                        statusOptions={statusOptions}
                        onSaved={refreshScopes}
                    >
                        <Button type="button" data-test="scopes-create-button">
                            <Plus /> New scope
                        </Button>
                    </ScopeFormModal>
                </div>

                <div className="bg-card divide-y overflow-hidden rounded-lg border">
                    {scopes.data.map((scope) => (
                        <div
                            key={scope.id}
                            data-test="scope-row"
                            className="hover:bg-accent/40 flex items-center justify-between gap-4 px-4 py-3.5 transition-colors"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {scope.name}
                                    </span>
                                    <Badge
                                        variant={
                                            scope.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {scope.status === 'active'
                                            ? 'Active'
                                            : 'Inactive'}
                                    </Badge>
                                </div>
                                {scope.description ? (
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {scope.description}
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
                                                aria-label="Edit scope"
                                                data-test="scope-edit-button"
                                                onClick={() =>
                                                    setEditingScope(scope)
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Edit scope</p>
                                        </TooltipContent>
                                    </Tooltip>

                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="Delete scope"
                                                data-test="scope-delete-button"
                                                onClick={() =>
                                                    setScopeToDelete(scope)
                                                }
                                            >
                                                <Trash2 />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Delete scope</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                            </TooltipProvider>
                        </div>
                    ))}

                    {scopes.data.length === 0 ? (
                        <p className="text-muted-foreground px-4 py-10 text-center text-sm">
                            No scopes have been created yet.
                        </p>
                    ) : null}
                </div>

                <Pagination paginator={scopes} />
            </div>

            <ScopeFormModal
                open={editingScope !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingScope(null);
                    }
                }}
                scope={editingScope}
                statusOptions={statusOptions}
                onSaved={refreshScopes}
            />

            <ScopeDeleteModal
                scope={scopeToDelete}
                open={scopeToDelete !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setScopeToDelete(null);
                    }
                }}
                onDeleted={refreshScopes}
            />
        </>
    );
}

ScopesIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Scopes',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
