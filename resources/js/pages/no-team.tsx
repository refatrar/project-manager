import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import TeamInvitationController from '@/actions/App/Http/Controllers/Teams/TeamInvitationController';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { logout } from '@/routes';
import type { DashboardInvitation } from '@/types';

type Props = {
    pendingInvitations: DashboardInvitation[];
};

export default function NoTeam({ pendingInvitations }: Props) {
    const [processingCode, setProcessingCode] = useState<string | null>(null);

    const acceptInvitation = (invitation: DashboardInvitation) => {
        router.visit(TeamInvitationController.accept(invitation), {
            onStart: () => setProcessingCode(invitation.code),
            onFinish: () => setProcessingCode(null),
        });
    };

    const declineInvitation = (invitation: DashboardInvitation) => {
        router.visit(TeamInvitationController.decline(invitation), {
            onStart: () => setProcessingCode(invitation.code),
            onFinish: () => setProcessingCode(null),
        });
    };

    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <Head title="Waiting for a team" />

            <div className="flex w-full max-w-md flex-col items-center gap-6">
                <AppLogoIcon className="text-foreground size-9 fill-current" />

                <Card className="w-full">
                    <CardHeader>
                        <CardTitle>You're not on a team yet</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-muted-foreground text-sm">
                            An administrator needs to assign you as a team
                            leader, or an existing team leader needs to invite
                            you, before you can get started.
                        </p>

                        {pendingInvitations.length > 0 ? (
                            <div
                                className="space-y-3"
                                data-test="no-team-invitations"
                            >
                                <p className="text-sm font-medium">
                                    Pending invitations
                                </p>
                                {pendingInvitations.map((invitation) => (
                                    <div
                                        key={invitation.code}
                                        data-test="pending-invitation-row"
                                        className="rounded-lg border p-3"
                                    >
                                        <p className="text-sm font-medium">
                                            {invitation.team.name}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {invitation.inviterName} invited you
                                            to join this team.
                                        </p>
                                        <div className="mt-3 flex justify-end gap-2">
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                data-test="pending-invitation-decline"
                                                disabled={
                                                    processingCode ===
                                                    invitation.code
                                                }
                                                onClick={() =>
                                                    declineInvitation(
                                                        invitation,
                                                    )
                                                }
                                            >
                                                Decline
                                            </Button>
                                            <Button
                                                size="sm"
                                                data-test="pending-invitation-accept"
                                                disabled={
                                                    processingCode ===
                                                    invitation.code
                                                }
                                                onClick={() =>
                                                    acceptInvitation(invitation)
                                                }
                                            >
                                                Accept
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : null}
                    </CardContent>
                </Card>

                <Link
                    href={logout()}
                    as="button"
                    className="text-muted-foreground text-sm underline-offset-4 hover:underline"
                    data-test="no-team-logout"
                >
                    Log out
                </Link>
            </div>
        </div>
    );
}
