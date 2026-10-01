import { Head, Link, usePage } from '@inertiajs/react';
import { FolderKanban } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/patterns/empty-state';
import { ProgressBar } from '@/components/patterns/progress-bar';
import { StatusBadge } from '@/components/patterns/status-badge';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import PortfolioHealthBar from '@/components/portfolio-health-bar';
import { Button } from '@/components/ui/button';
import { useTeamAccess } from '@/hooks/use-team-access';
import { optionLabel } from '@/lib/enum';
import { formatDate } from '@/lib/format';
import { statusMeta } from '@/lib/status';
import { dashboard } from '@/routes';
import { index as projectsIndex, show as showProject } from '@/routes/projects';
import type {
    DashboardInvitation,
    PortfolioHealthCounts,
    Project,
    ProjectHealthOption,
    ProjectStatusOption,
} from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    projects: Project[];
    healthCounts: PortfolioHealthCounts;
    overdueTasks: number;
    blockedTasks: number;
    statusOptions: ProjectStatusOption[];
    healthOptions: ProjectHealthOption[];
};

export default function Dashboard({
    pendingInvitations = [],
    projects,
    healthCounts,
    overdueTasks,
    blockedTasks,
    statusOptions,
    healthOptions,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const teamSlug = usePage().props.currentTeam?.slug;
    const can = useTeamAccess();
    const needsAttention = healthCounts.at_risk + healthCounts.off_track;

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 2xl:p-8">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div className="min-w-0 space-y-2">
                        <h1 className="text-xl font-semibold tracking-[-0.02em]">
                            Open work
                        </h1>
                        <p className="text-muted-foreground flex flex-wrap gap-x-3 gap-y-1 text-sm">
                            <span className="text-primary font-medium">
                                {projects.length} open
                            </span>
                            <span
                                className={
                                    needsAttention > 0
                                        ? 'text-warning-subtle-foreground'
                                        : undefined
                                }
                            >
                                {needsAttention} need attention
                            </span>
                            <span
                                data-test="portfolio-overdue"
                                className={
                                    overdueTasks > 0
                                        ? 'text-destructive'
                                        : undefined
                                }
                            >
                                {overdueTasks} overdue
                            </span>
                            <span
                                data-test="portfolio-blocked"
                                className={
                                    blockedTasks > 0
                                        ? 'text-destructive'
                                        : undefined
                                }
                            >
                                {blockedTasks} blocked
                            </span>
                        </p>
                    </div>
                    {teamSlug && can('projects.view') ? (
                        <Button size="sm" asChild>
                            <Link href={projectsIndex(teamSlug)}>
                                All projects
                            </Link>
                        </Button>
                    ) : null}
                </header>

                <div className="grid items-start gap-10 xl:grid-cols-[minmax(0,1fr)_18rem]">
                    <section aria-label="Projects" className="min-w-0">
                        {projects.length > 0 ? (
                            <ul className="divide-y border-y">
                                {projects.map((project) => {
                                    const healthTone = statusMeta(
                                        'health',
                                        project.health,
                                    ).tone;

                                    return (
                                        <li key={project.id}>
                                            <Link
                                                href={
                                                    teamSlug
                                                        ? showProject.url([
                                                              teamSlug,
                                                              project.id,
                                                          ])
                                                        : '#'
                                                }
                                                data-test="portfolio-project-row"
                                                className="hover:bg-accent/50 focus-visible:bg-accent/50 group grid gap-3 px-4 py-3.5 transition-colors focus-visible:outline-none md:grid-cols-[minmax(0,1fr)_11rem_7rem] md:items-center md:gap-6 md:px-5"
                                            >
                                                <div className="min-w-0 space-y-1.5">
                                                    <div className="flex min-w-0 items-center gap-2">
                                                        <span className="text-subtle-foreground shrink-0 font-mono text-xs">
                                                            {project.code}
                                                        </span>
                                                        <span className="truncate text-sm font-semibold group-hover:underline group-hover:underline-offset-2">
                                                            {project.name}
                                                        </span>
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <StatusBadge
                                                            kind="project"
                                                            value={
                                                                project.status
                                                            }
                                                            label={optionLabel(
                                                                statusOptions,
                                                                project.status,
                                                            )}
                                                        />
                                                        <StatusBadge
                                                            kind="health"
                                                            value={
                                                                project.health
                                                            }
                                                            label={optionLabel(
                                                                healthOptions,
                                                                project.health,
                                                            )}
                                                        />
                                                    </div>
                                                </div>

                                                <div className="space-y-1.5">
                                                    <div className="flex items-center justify-between text-xs">
                                                        <span className="text-muted-foreground">
                                                            Progress
                                                        </span>
                                                        <span className="font-medium tabular-nums">
                                                            {
                                                                project.progress_percentage
                                                            }
                                                            %
                                                        </span>
                                                    </div>
                                                    <ProgressBar
                                                        value={
                                                            project.progress_percentage
                                                        }
                                                        label={`${project.name} progress`}
                                                        tone={healthTone}
                                                    />
                                                </div>

                                                <div className="text-xs md:text-right">
                                                    <span className="text-muted-foreground md:block">
                                                        Target{' '}
                                                    </span>
                                                    <span className="font-medium tabular-nums">
                                                        {formatDate(
                                                            project.end_date,
                                                            'Not set',
                                                        )}
                                                    </span>
                                                </div>
                                            </Link>
                                        </li>
                                    );
                                })}
                            </ul>
                        ) : (
                            <EmptyState
                                compact
                                icon={FolderKanban}
                                title="No open projects"
                                description="Open projects you can see will appear here with their progress and health."
                                className="py-12"
                                action={
                                    teamSlug && can('projects.view') ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={projectsIndex(teamSlug)}
                                            >
                                                Go to projects
                                            </Link>
                                        </Button>
                                    ) : null
                                }
                            />
                        )}
                    </section>

                    <aside
                        aria-labelledby="dashboard-health-heading"
                        className="space-y-4"
                    >
                        <div>
                            <h2
                                id="dashboard-health-heading"
                                className="text-[0.9375rem] font-semibold"
                            >
                                Portfolio health
                            </h2>
                            <p className="text-muted-foreground text-xs">
                                Reported health of your open projects
                            </p>
                        </div>
                        <PortfolioHealthBar counts={healthCounts} />
                    </aside>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
