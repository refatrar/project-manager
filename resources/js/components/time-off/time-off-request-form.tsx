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
import { store, update } from '@/routes/time-off-requests';
import type { TimeOffRequest, TimeOffType, TimeOffTypeOption } from '@/types';

export type TimeOffRequestFormData = {
    type: TimeOffType | '';
    starts_on: string;
    ends_on: string;
    is_full_day: boolean;
    start_time: string;
    end_time: string;
    reason: string;
};

export type TimeOffRequestSavedResponse = {
    message: string;
};

type Props = {
    request?: TimeOffRequest | null;
    typeOptions: TimeOffTypeOption[];
    onSaved?: (message: string) => void;
    onCancel?: () => void;
};

export default function TimeOffRequestForm({
    request = null,
    typeOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<TimeOffRequestFormData, TimeOffRequestSavedResponse>(
        () =>
            request
                ? update([teamSlug ?? '', request.id])
                : store(teamSlug ?? ''),
        {
            type: request?.type ?? '',
            starts_on: request?.starts_on ?? '',
            ends_on: request?.ends_on ?? '',
            is_full_day: request?.is_full_day ?? true,
            start_time: request?.start_time?.slice(0, 5) ?? '',
            end_time: request?.end_time?.slice(0, 5) ?? '',
            reason: request?.reason ?? '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => onSaved?.(response.message),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="time-off-type">Type</Label>
                <Select
                    value={form.data.type}
                    onValueChange={(value) => form.setData('type', value as TimeOffType)}
                >
                    <SelectTrigger id="time-off-type" data-test="time-off-type">
                        <SelectValue placeholder="Select a type" />
                    </SelectTrigger>
                    <SelectContent>
                        {typeOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.type} />
            </div>

            <div className="grid w-full min-w-0 grid-cols-2 gap-4 *:min-w-0">
                <div className="grid gap-2">
                    <Label htmlFor="time-off-starts-on">Starts on</Label>
                    <Input
                        id="time-off-starts-on"
                        type="date"
                        value={form.data.starts_on}
                        onChange={(event) => form.setData('starts_on', event.target.value)}
                        data-test="time-off-starts-on"
                    />
                    <InputError message={form.errors.starts_on} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="time-off-ends-on">Ends on</Label>
                    <Input
                        id="time-off-ends-on"
                        type="date"
                        value={form.data.ends_on}
                        onChange={(event) => form.setData('ends_on', event.target.value)}
                        data-test="time-off-ends-on"
                    />
                    <InputError message={form.errors.ends_on} />
                </div>
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="time-off-full-day"
                    checked={form.data.is_full_day}
                    onCheckedChange={(checked) => form.setData('is_full_day', checked === true)}
                    data-test="time-off-full-day"
                />
                <Label htmlFor="time-off-full-day">Full day</Label>
            </div>

            {!form.data.is_full_day ? (
                <div className="grid w-full min-w-0 grid-cols-2 gap-4 *:min-w-0">
                    <div className="grid gap-2">
                        <Label htmlFor="time-off-start-time">Start time</Label>
                        <Input
                            id="time-off-start-time"
                            type="time"
                            value={form.data.start_time}
                            onChange={(event) => form.setData('start_time', event.target.value)}
                            data-test="time-off-start-time"
                        />
                        <InputError message={form.errors.start_time} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="time-off-end-time">End time</Label>
                        <Input
                            id="time-off-end-time"
                            type="time"
                            value={form.data.end_time}
                            onChange={(event) => form.setData('end_time', event.target.value)}
                            data-test="time-off-end-time"
                        />
                        <InputError message={form.errors.end_time} />
                    </div>
                </div>
            ) : null}

            <div className="grid gap-2">
                <Label htmlFor="time-off-reason">Reason</Label>
                <Textarea
                    id="time-off-reason"
                    rows={3}
                    value={form.data.reason}
                    onChange={(event) => form.setData('reason', event.target.value)}
                    data-test="time-off-reason"
                />
                <InputError message={form.errors.reason} />
            </div>

            <div className="flex justify-end gap-2">
                {onCancel ? (
                    <Button type="button" variant="secondary" onClick={onCancel}>
                        Cancel
                    </Button>
                ) : null}
                <Button
                    type="submit"
                    disabled={form.processing || !teamSlug}
                    data-test="time-off-submit"
                >
                    {request ? 'Save changes' : 'Request time off'}
                </Button>
            </div>
        </form>
    );
}
