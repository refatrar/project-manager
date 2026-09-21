import { useHttp, usePage } from '@inertiajs/react';
import { Pencil, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import TimeOffRequestFormModal from '@/components/time-off/time-off-request-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cancel } from '@/routes/time-off-requests';
import type { TimeOffRequest, TimeOffTypeOption } from '@/types';

type CancelledResponse = {
    message: string;
};

type Props = {
    requests: TimeOffRequest[];
    typeOptions: TimeOffTypeOption[];
    onChanged: () => void;
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    pending: 'secondary',
    approved: 'default',
    rejected: 'destructive',
    cancelled: 'outline',
};

export default function TimeOffRequestList({ requests, typeOptions, onChanged }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [editingRequest, setEditingRequest] = useState<TimeOffRequest | null>(null);
    const form = useHttp<Record<string, never>, CancelledResponse>({});

    const cancelRequest = (request: TimeOffRequest) => {
        if (!teamSlug) {
            return;
        }

        void form.patch(cancel.url([teamSlug, request.id]), {
            onSuccess: (response) => {
                toast.success(response.message);
                onChanged();
            },
        });
    };

    if (requests.length === 0) {
        return <p className="text-muted-foreground py-4 text-center text-sm">No time off requested yet.</p>;
    }

    return (
        <>
            <ul className="space-y-2">
                {requests.map((request) => (
                    <li
                        key={request.id}
                        data-test="time-off-request-row"
                        className="flex flex-wrap items-start justify-between gap-3 rounded-lg border p-3"
                    >
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-medium capitalize">
                                    {request.type.replace('_', ' ')}
                                </span>
                                <Badge variant={statusVariant[request.status] ?? 'outline'}>
                                    {request.status}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground text-sm">
                                {request.starts_on} – {request.ends_on}
                                {!request.is_full_day && request.total_hours !== null
                                    ? ` · ${request.total_hours}h`
                                    : ''}
                            </p>
                            {request.reason ? (
                                <p className="text-muted-foreground mt-1 text-sm">{request.reason}</p>
                            ) : null}
                            {request.status !== 'pending' && request.approver ? (
                                <p className="text-muted-foreground mt-1 text-xs">
                                    Decided by {request.approver.name}
                                    {request.decision_note ? `: ${request.decision_note}` : ''}
                                </p>
                            ) : null}
                        </div>

                        {request.status === 'pending' ? (
                            <div className="flex shrink-0 items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 w-7 p-0"
                                    data-test="time-off-edit"
                                    onClick={() => setEditingRequest(request)}
                                >
                                    <Pencil className="h-3.5 w-3.5" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 w-7 p-0"
                                    data-test="time-off-cancel"
                                    disabled={form.processing || !teamSlug}
                                    onClick={() => cancelRequest(request)}
                                >
                                    <X className="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        ) : null}
                    </li>
                ))}
            </ul>

            <TimeOffRequestFormModal
                open={editingRequest !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setEditingRequest(null);
                    }
                }}
                request={editingRequest}
                typeOptions={typeOptions}
                onSaved={onChanged}
            />
        </>
    );
}
