import { InfoIcon } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { TeamInvitationContext } from '@/types';

type Props = {
    invitation: TeamInvitationContext;
    action: 'Log in' | 'Register';
};

export default function TeamInvitationAlert({ invitation, action }: Props) {
    return (
        <Alert variant="info" data-test="team-invitation-alert">
            <InfoIcon />
            <AlertDescription>
                {action} to join the "{invitation.teamName}" team.
            </AlertDescription>
        </Alert>
    );
}
