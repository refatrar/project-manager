import { Check, X } from 'lucide-react';
import { useState } from 'react';
import DecideTimeOffRequestModal from '@/components/time-off/decide-time-off-request-modal';
import { Button } from '@/components/ui/button';
import type { TimeOffRequest } from '@/types';

type Props = {
    requests: TimeOffRequest[];
    onChanged: () => void;
};

export default function TimeOffApprovalQueue({ requests, onChanged }: Props) {
    const [decision, setDecision] = useState<{ request: TimeOffRequest; decision: 'approved' | 'rejected' } | null>(null);

    if (requests.length === 0) {
        return <p className="text-muted-foreground py-4 text-center text-sm">Nothing pending approval.</p>;
    }

    return (
        <>
            <ul className="space-y-2">
                {requests.map((request) => (
                    <li
                        key={request.id}
                        data-test="approval-queue-row"
                        className="flex flex-wrap items-start justify-between gap-3 rounded-lg border p-3"
                    >
                        <div className="min-w-0">
                            <p className="text-sm font-medium">
                                {request.user.name}
                                <span className="text-muted-foreground font-normal capitalize"> · {request.type.replace('_', ' ')}</span>
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {request.starts_on} – {request.ends_on}
                                {!request.is_full_day && request.total_hours !== null
                                    ? ` · ${request.total_hours}h`
                                    : ''}
                            </p>
                            {request.reason ? (
                                <p className="text-muted-foreground mt-1 text-sm">{request.reason}</p>
                            ) : null}
                        </div>

                        <div className="flex shrink-0 items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                data-test="approval-approve"
                                onClick={() => setDecision({ request, decision: 'approved' })}
                            >
                                <Check className="h-4 w-4" /> Approve
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                data-test="approval-reject"
                                onClick={() => setDecision({ request, decision: 'rejected' })}
                            >
                                <X className="h-4 w-4" /> Reject
                            </Button>
                        </div>
                    </li>
                ))}
            </ul>

            <DecideTimeOffRequestModal
                request={decision?.request ?? null}
                decision={decision?.decision ?? 'approved'}
                open={decision !== null}
                onOpenChange={(nextOpen) => {
                    if (!nextOpen) {
                        setDecision(null);
                    }
                }}
                onDecided={onChanged}
            />
        </>
    );
}
