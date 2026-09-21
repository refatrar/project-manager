import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import TimeOffApprovalQueue from '@/components/time-off/time-off-approval-queue';
import TimeOffRequestFormModal from '@/components/time-off/time-off-request-form-modal';
import TimeOffRequestList from '@/components/time-off/time-off-request-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as timeOffIndex } from '@/routes/time-off-requests';
import type { TimeOffRequest, TimeOffTypeOption } from '@/types';

type Props = {
    myRequests: TimeOffRequest[];
    pendingApprovals: TimeOffRequest[];
    isApprover: boolean;
    typeOptions: TimeOffTypeOption[];
};

export default function TimeOffIndex({
    myRequests,
    pendingApprovals,
    isApprover,
    typeOptions,
}: Props) {
    const refresh = () => router.reload({ only: ['myRequests', 'pendingApprovals'] });

    return (
        <>
            <Head title="Time Off" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Time Off"
                        description="Approved time off reduces your available capacity; pending requests do not."
                    />

                    <TimeOffRequestFormModal typeOptions={typeOptions} onSaved={refresh}>
                        <Button type="button" data-test="time-off-create-button">
                            <Plus /> Request time off
                        </Button>
                    </TimeOffRequestFormModal>
                </div>

                {isApprover ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Pending approvals</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <TimeOffApprovalQueue requests={pendingApprovals} onChanged={refresh} />
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Your requests</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TimeOffRequestList
                            requests={myRequests}
                            typeOptions={typeOptions}
                            onChanged={refresh}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TimeOffIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Time Off',
            href: props.currentTeam ? timeOffIndex(props.currentTeam.slug) : '/',
        },
    ],
});
