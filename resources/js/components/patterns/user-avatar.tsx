import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

type Person = { id: number; name: string };

const sizes = {
    xs: 'size-5 text-[0.625rem]',
    sm: 'size-6 text-[0.6875rem]',
    md: 'size-8 text-xs',
} as const;

type Size = keyof typeof sizes;

export function UserAvatar({
    name,
    size = 'sm',
    className,
}: {
    name: string;
    size?: Size;
    className?: string;
}) {
    const getInitials = useInitials();

    return (
        <span
            className={cn(
                'bg-secondary text-secondary-foreground ring-card inline-flex shrink-0 items-center justify-center rounded-full font-semibold ring-2 select-none',
                sizes[size],
                className,
            )}
            aria-hidden="true"
        >
            {getInitials(name)}
        </span>
    );
}

/**
 * Overlapping avatars for a set of people, with the full list available to
 * screen readers and in a tooltip.
 */
export function AvatarStack({
    people,
    max = 3,
    size = 'sm',
    className,
}: {
    people: Person[];
    max?: number;
    size?: Size;
    className?: string;
}) {
    if (people.length === 0) {
        return null;
    }

    const shown = people.slice(0, max);
    const overflow = people.length - shown.length;
    const names = people.map((person) => person.name).join(', ');

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    className={cn(
                        'inline-flex items-center -space-x-1.5',
                        className,
                    )}
                    tabIndex={0}
                    aria-label={`Assigned to ${names}`}
                >
                    {shown.map((person) => (
                        <UserAvatar
                            key={person.id}
                            name={person.name}
                            size={size}
                        />
                    ))}
                    {overflow > 0 ? (
                        <span
                            className={cn(
                                'bg-muted text-muted-foreground ring-card inline-flex items-center justify-center rounded-full font-semibold ring-2',
                                sizes[size],
                            )}
                            aria-hidden="true"
                        >
                            +{overflow}
                        </span>
                    ) : null}
                </span>
            </TooltipTrigger>
            <TooltipContent>{names}</TooltipContent>
        </Tooltip>
    );
}
