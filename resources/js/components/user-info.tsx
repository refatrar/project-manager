import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { Team, User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    team = null,
}: {
    user: User;
    showEmail?: boolean;
    team?: Team | null;
}) {
    const getInitials = useInitials();
    const showAvatar = Boolean(user.avatar && user.avatar !== '');

    return (
        <>
            <Avatar className="size-8 overflow-hidden rounded-full">
                {showAvatar ? (
                    <AvatarImage
                        src={user.avatar ?? undefined}
                        alt={user.name}
                    />
                ) : null}
                <AvatarFallback className="bg-secondary text-secondary-foreground rounded-full text-xs font-semibold">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold">{user.name}</span>
                {team ? (
                    <span className="text-muted-foreground truncate text-xs">
                        {team.name}
                    </span>
                ) : null}
                {!team && showEmail ? (
                    <span className="text-muted-foreground truncate text-xs">
                        {user.email}
                    </span>
                ) : null}
            </div>
        </>
    );
}
