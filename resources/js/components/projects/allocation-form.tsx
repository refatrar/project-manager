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
import { store, update } from '@/routes/projects/resource-allocations';
import type {
    AllocationStatus,
    AllocationStatusOption,
    ProjectMember,
    ResourceAllocation,
} from '@/types';

export type AllocationFormData = {
    user_id: string;
    status: AllocationStatus;
    starts_on: string;
    ends_on: string;
    hours_per_day: string;
    allocation_percentage: string;
    notes: string;
};

export type AllocationSavedResponse = {
    allocation: ResourceAllocation;
    message: string;
};

type Props = {
    projectId: number;
    allocation?: ResourceAllocation | null;
    members: ProjectMember[];
    statusOptions: AllocationStatusOption[];
    onSaved?: (allocation: ResourceAllocation, message: string) => void;
    onCancel?: () => void;
};

export default function AllocationForm({
    projectId,
    allocation = null,
    members,
    statusOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;

    const form = useHttp<AllocationFormData, AllocationSavedResponse>(
        () =>
            allocation
                ? update.put([teamSlug ?? '', projectId, allocation.id])
                : store([teamSlug ?? '', projectId]),
        {
            user_id: allocation ? String(allocation.user.id) : '',
            status: allocation?.status ?? 'planned',
            starts_on: allocation?.starts_on ?? '',
            ends_on: allocation?.ends_on ?? '',
            hours_per_day: allocation ? String(allocation.hours_per_day) : '8',
            allocation_percentage: allocation?.allocation_percentage
                ? String(allocation.allocation_percentage)
                : '',
            notes: allocation?.notes ?? '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.allocation, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="allocation-user">Member</Label>
                <Select
                    value={form.data.user_id}
                    onValueChange={(value) => form.setData('user_id', value)}
                >
                    <SelectTrigger id="allocation-user" data-test="allocation-user">
                        <SelectValue placeholder="Select a member" />
                    </SelectTrigger>
                    <SelectContent>
                        {members.map((member) => (
                            <SelectItem key={member.user.id} value={String(member.user.id)}>
                                {member.user.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.user_id} />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="allocation-starts-on">Starts on</Label>
                    <Input
                        id="allocation-starts-on"
                        type="date"
                        value={form.data.starts_on}
                        onChange={(event) => form.setData('starts_on', event.target.value)}
                        data-test="allocation-starts-on"
                    />
                    <InputError message={form.errors.starts_on} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="allocation-ends-on">Ends on</Label>
                    <Input
                        id="allocation-ends-on"
                        type="date"
                        value={form.data.ends_on}
                        onChange={(event) => form.setData('ends_on', event.target.value)}
                        data-test="allocation-ends-on"
                    />
                    <InputError message={form.errors.ends_on} />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="allocation-hours">Hours per day</Label>
                    <Input
                        id="allocation-hours"
                        type="number"
                        min="0.5"
                        max="24"
                        step="0.5"
                        value={form.data.hours_per_day}
                        onChange={(event) => form.setData('hours_per_day', event.target.value)}
                        data-test="allocation-hours"
                    />
                    <InputError message={form.errors.hours_per_day} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="allocation-status">Status</Label>
                    <Select
                        value={form.data.status}
                        onValueChange={(value) => form.setData('status', value as AllocationStatus)}
                    >
                        <SelectTrigger id="allocation-status" data-test="allocation-status">
                            <SelectValue />
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
            </div>

            <div className="grid gap-2">
                <Label htmlFor="allocation-notes">Notes</Label>
                <Textarea
                    id="allocation-notes"
                    rows={2}
                    value={form.data.notes}
                    onChange={(event) => form.setData('notes', event.target.value)}
                    data-test="allocation-notes"
                />
                <InputError message={form.errors.notes} />
            </div>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {onCancel ? (
                    <Button type="button" variant="secondary" onClick={onCancel}>
                        Cancel
                    </Button>
                ) : null}
                <Button
                    type="submit"
                    disabled={form.processing || !teamSlug}
                    data-test="allocation-submit"
                >
                    {allocation ? 'Save changes' : 'Create booking'}
                </Button>
            </div>
        </form>
    );
}
