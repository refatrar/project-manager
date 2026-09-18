import type { PropsWithChildren } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import ScopeForm from '@/components/setup/scope-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Scope, ScopeStatusOption } from '@/types';

type Props = PropsWithChildren<{
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    scope?: Scope | null;
    statusOptions?: ScopeStatusOption[];
    onSaved?: (scope: Scope) => void;
}>;

export default function ScopeFormModal({
    children,
    open,
    onOpenChange,
    scope = null,
    statusOptions,
    onSaved,
}: Props) {
    const [uncontrolledOpen, setUncontrolledOpen] = useState(false);
    const isControlled = open !== undefined;
    const dialogOpen = isControlled ? open : uncontrolledOpen;

    const setDialogOpen = (nextOpen: boolean) => {
        if (!isControlled) {
            setUncontrolledOpen(nextOpen);
        }

        onOpenChange?.(nextOpen);
    };

    const isEditing = Boolean(scope);

    return (
        <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
            {children ? (
                <DialogTrigger asChild>{children}</DialogTrigger>
            ) : null}
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit scope' : 'Create scope'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Update the name, description, and status for this scope.'
                            : 'Add a scope that can be reused across projects and other forms.'}
                    </DialogDescription>
                </DialogHeader>

                <ScopeForm
                    key={`${String(dialogOpen)}-${scope?.id ?? 'create'}`}
                    scope={scope}
                    statusOptions={statusOptions}
                    onCancel={() => setDialogOpen(false)}
                    onSaved={(savedScope, message) => {
                        toast.success(message);
                        setDialogOpen(false);
                        onSaved?.(savedScope);
                    }}
                />
            </DialogContent>
        </Dialog>
    );
}
