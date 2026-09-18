import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
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

                <div className="space-y-3">
                    {scopes.data.map((scope) => (
                        <div
                            key={scope.id}
                            data-test="scope-row"
                            className="flex items-center justify-between gap-4 rounded-lg border p-4"
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
                                                size="sm"
                                                data-test="scope-edit-button"
                                                onClick={() =>
                                                    setEditingScope(scope)
                                                }
                                            >
                                                <Pencil className="h-4 w-4" />
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
                                                size="sm"
                                                data-test="scope-delete-button"
                                                onClick={() =>
                                                    setScopeToDelete(scope)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
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
                        <p className="text-muted-foreground py-8 text-center">
                            No scopes have been created yet.
                        </p>
                    ) : null}
                </div>

                {scopes.last_page > 1 ? (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        {scopes.links.map((link, linkIndex) =>
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
