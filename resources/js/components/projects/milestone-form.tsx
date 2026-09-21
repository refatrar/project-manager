import { useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { store, update } from '@/routes/projects/milestones';
import type { Milestone, MilestoneStatus, MilestoneStatusOption } from '@/types';

export type MilestoneFormData = {
    name: string;
    description: string;
    status: MilestoneStatus;
    due_on: string;
    is_billable: boolean;
    payment_amount: string;
};

export type MilestoneSavedResponse = {
    milestone: Milestone;
    message: string;
};

type Props = {
    projectId: number;
    milestone?: Milestone | null;
    statusOptions: MilestoneStatusOption[];
    onSaved?: (milestone: Milestone, message: string) => void;
    onCancel?: () => void;
};

export default function MilestoneForm({
    projectId,
    milestone = null,
    statusOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;

    const form = useHttp<MilestoneFormData, MilestoneSavedResponse>(
        () =>
            milestone
                ? update.put([teamSlug ?? '', projectId, milestone.id])
                : store([teamSlug ?? '', projectId]),
        {
            name: milestone?.name ?? '',
            description: milestone?.description ?? '',
            status: milestone?.status ?? 'pending',
            due_on: milestone?.due_on ?? '',
            is_billable: milestone?.is_billable ?? false,
            payment_amount: milestone?.payment_amount ?? '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.milestone, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="milestone-name">Name</Label>
                <Input
                    id="milestone-name"
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    placeholder="Beta launch"
                    required
                    data-test="milestone-name"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="milestone-description">Description</Label>
                <Textarea
                    id="milestone-description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    data-test="milestone-description"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="milestone-status">Status</Label>
                    <Select
                        value={form.data.status}
                        onValueChange={(value) =>
                            form.setData('status', value as MilestoneStatus)
                        }
                    >
                        <SelectTrigger
                            id="milestone-status"
                            data-test="milestone-status"
                        >
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

                <div className="grid gap-2">
                    <Label htmlFor="milestone-due-on">Due on</Label>
                    <Input
                        id="milestone-due-on"
                        type="date"
                        value={form.data.due_on}
                        onChange={(event) =>
                            form.setData('due_on', event.target.value)
                        }
                        data-test="milestone-due-on"
                    />
                    <InputError message={form.errors.due_on} />
                </div>
            </div>

            <div className="flex items-end gap-4">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="milestone-billable"
                        checked={form.data.is_billable}
                        onCheckedChange={(checked) =>
                            form.setData('is_billable', checked === true)
                        }
                    />
                    <Label htmlFor="milestone-billable">Billable</Label>
                </div>

                <div className="grid flex-1 gap-2">
                    <Label htmlFor="milestone-payment">Payment amount</Label>
                    <Input
                        id="milestone-payment"
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.payment_amount}
                        onChange={(event) =>
                            form.setData('payment_amount', event.target.value)
                        }
                    />
                    <InputError message={form.errors.payment_amount} />
                </div>
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
                    data-test="milestone-submit"
                >
                    {milestone ? 'Save changes' : 'Create milestone'}
                </Button>
            </div>
        </form>
    );
}
