import { useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/setup/scopes';
import type { Scope, ScopeStatus, ScopeStatusOption } from '@/types';

export type ScopeFormData = {
    name: string;
    description: string;
    status: ScopeStatus;
};

export type ScopeSavedResponse = {
    scope: Scope;
    message: string;
};

type Props = {
    scope?: Scope | null;
    statusOptions?: ScopeStatusOption[];
    onSaved?: (scope: Scope, message: string) => void;
    onCancel?: () => void;
};

const defaultStatusOptions: ScopeStatusOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

export default function ScopeForm({
    scope = null,
    statusOptions = defaultStatusOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<ScopeFormData, ScopeSavedResponse>(
        () =>
            scope
                ? update.put([teamSlug ?? '', scope.id])
                : store(teamSlug ?? ''),
        {
            name: scope?.name ?? '',
            description: scope?.description ?? '',
            status: scope?.status ?? 'active',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.scope, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="scope-name">Name</Label>
                <Input
                    id="scope-name"
                    name="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="Web application"
                    required
                    data-test="scope-name"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="scope-description">Description</Label>
                <Textarea
                    id="scope-description"
                    name="description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    placeholder="Optional details about this scope"
                    data-test="scope-description"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="scope-status">Status</Label>
                <Select
                    name="status"
                    value={form.data.status}
                    onValueChange={(value) =>
                        form.setData('status', value as ScopeStatus)
                    }
                >
                    <SelectTrigger
                        id="scope-status"
                        className="w-full"
                        data-test="scope-status"
                    >
                        <SelectValue placeholder="Select a status" />
                    </SelectTrigger>
                    <SelectContent>
                        {statusOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.status} />
            </div>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {onCancel ? (
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={onCancel}
                    >
                        Cancel
                    </Button>
                ) : null}

                <Button
                    type="submit"
                    disabled={form.processing || !teamSlug}
                    data-test="scope-submit"
                >
                    {scope ? 'Save changes' : 'Create scope'}
                </Button>
            </div>
        </form>
    );
}
