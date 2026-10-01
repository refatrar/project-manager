import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useIsMobile } from '@/hooks/use-mobile';
import { switchMethod } from '@/routes/teams';
import type { Team } from '@/types';

type TeamSwitcherProps = {
    inHeader?: boolean;
};

/**
 * Swap the first path segment equal to `from` for `to`. Only whole
 * segments match, so switching away from `acme` leaves `/acme-corp`
 * alone. Returns null when the URL has no such segment.
 */
function replaceSlugSegment(
    url: string,
    from: string,
    to: string,
): string | null {
    const escaped = from.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = new RegExp(`/${escaped}(?=[/?#]|$)`);

    return pattern.test(url) ? url.replace(pattern, `/${to}`) : null;
}

export function TeamSwitcher({ inHeader = false }: TeamSwitcherProps) {
    const page = usePage();
    const isMobile = useIsMobile();
    const currentTeam = page.props.currentTeam;
    const teams = page.props.teams ?? [];

    const switchTeam = (team: Team) => {
        if (currentTeam?.id === team.id) {
            return;
        }

        const previousTeamSlug = currentTeam?.slug;

        router.visit(switchMethod(team.slug), {
            onFinish: () => {
                if (!previousTeamSlug || typeof window === 'undefined') {
                    router.reload();

                    return;
                }

                const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
                const nextUrl = replaceSlugSegment(
                    currentUrl,
                    previousTeamSlug,
                    team.slug,
                );

                if (nextUrl !== null) {
                    router.visit(nextUrl, { replace: true });

                    return;
                }

                router.reload();
            },
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    data-test="team-switcher-trigger"
                    aria-label={`Switch team (current: ${currentTeam?.name ?? 'none'})`}
                    className={
                        inHeader
                            ? 'h-8 gap-1 px-2'
                            : 'text-sidebar-foreground hover:bg-sidebar-accent data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground border-sidebar-border bg-sidebar h-10 w-full justify-start gap-2.5 border px-2 group-data-[collapsible=icon]:size-8 group-data-[collapsible=icon]:border-0 group-data-[collapsible=icon]:p-0 has-[>svg]:px-2'
                    }
                >
                    <span
                        className={
                            inHeader
                                ? 'hidden'
                                : 'bg-card text-foreground flex size-6 shrink-0 items-center justify-center rounded-md border text-[0.6875rem] font-semibold group-data-[collapsible=icon]:size-8'
                        }
                        aria-hidden="true"
                    >
                        {currentTeam ? (
                            currentTeam.name.trim().charAt(0).toUpperCase()
                        ) : (
                            <Users className="size-3.5" />
                        )}
                    </span>
                    <div
                        className={
                            inHeader
                                ? 'grid flex-1 text-left text-sm leading-tight'
                                : 'grid min-w-0 flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden'
                        }
                    >
                        {inHeader ? null : (
                            <span className="text-subtle-foreground text-[0.6875rem] font-medium">
                                Team
                            </span>
                        )}
                        <span
                            className={
                                inHeader
                                    ? 'max-w-[120px] truncate font-medium'
                                    : 'truncate font-semibold'
                            }
                        >
                            {currentTeam?.name ?? 'Select team'}
                        </span>
                    </div>
                    <ChevronsUpDown
                        className={
                            inHeader
                                ? 'size-4 opacity-50'
                                : 'text-muted-foreground ml-auto group-data-[collapsible=icon]:hidden'
                        }
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className={
                    inHeader
                        ? 'w-56'
                        : 'w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg'
                }
                side={inHeader ? undefined : isMobile ? 'bottom' : 'right'}
                align={inHeader ? 'end' : 'start'}
                sideOffset={inHeader ? undefined : 4}
            >
                <DropdownMenuLabel className="text-muted-foreground text-xs">
                    Teams
                </DropdownMenuLabel>
                {teams.map((team) => (
                    <DropdownMenuItem
                        key={team.id}
                        data-test="team-switcher-item"
                        className={
                            inHeader
                                ? 'cursor-pointer gap-2'
                                : 'cursor-pointer gap-2 p-2'
                        }
                        onSelect={() => switchTeam(team)}
                    >
                        <span className="bg-muted text-muted-foreground flex size-6 shrink-0 items-center justify-center rounded-md text-[0.6875rem] font-semibold">
                            {team.name.trim().charAt(0).toUpperCase()}
                        </span>
                        <span className="truncate">{team.name}</span>
                        {currentTeam?.id === team.id && (
                            <Check className="text-primary ml-auto size-4" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
