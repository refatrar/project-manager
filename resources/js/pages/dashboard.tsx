import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import PortfolioHealthBar from '@/components/portfolio-health-bar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show as showProject } from '@/routes/projects';
import type {
    DashboardInvitation,
    PortfolioHealthCounts,
    Project,
} from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    projects: Project[];
    healthCounts: PortfolioHealthCounts;
    overdueTasks: number;
    blockedTasks: number;
};

const healthVariant: Record<string, 'default' | 'secondary' | 'destructive'> = {
    on_track: 'default',
    at_risk: 'secondary',
    off_track: 'destructive',
};

const healthMeterClass: Record<string, string> = {
    on_track: 'bg-status-good',
    at_risk: 'bg-status-warning',
    off_track: 'bg-status-critical',
};

export default function Dashboard({
    pendingInvitations = [],
    projects,
    healthCounts,
    overdueTasks,
    blockedTasks,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const teamSlug = usePage().props.currentTeam?.slug;

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Projects</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-semibold">
                                {projects.length}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                active
                            </p>
                        </CardContent>
                    </Card>

                    <Card data-test="portfolio-overdue">
                        <CardHeader>
                            <CardTitle>Overdue tasks</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className={cn(
                                    'text-2xl font-semibold',
                                    overdueTasks > 0 && 'text-status-critical',
                                )}
                            >
                                {overdueTasks}
                            </p>
                        </CardContent>
                    </Card>

                    <Card data-test="portfolio-blocked">
                        <CardHeader>
                            <CardTitle>Blocked tasks</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                className={cn(
                                    'text-2xl font-semibold',
                                    blockedTasks > 0 && 'text-status-critical',
                                )}
                            >
                                {blockedTasks}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Portfolio health</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <PortfolioHealthBar counts={healthCounts} />
                    </CardContent>
                </Card>

                <div className="space-y-2">
                    <h2 className="text-sm font-medium">Your projects</h2>

                    {projects.length > 0 ? (
                        <div className="space-y-2">
                            {projects.map((project) => (
                                <Link
                                    key={project.id}
                                    href={
                                        teamSlug
                                            ? showProject.url([
                                                  teamSlug,
                                                  project.id,
                                              ])
                                            : '#'
                                    }
                                    data-test="portfolio-project-row"
                                    className="hover:bg-accent flex items-center justify-between gap-4 rounded-lg border p-4"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="text-muted-foreground font-mono text-xs">
                                                {project.code}
                                            </span>
                                            <span className="font-medium">
                                                {project.name}
                                            </span>
                                            <Badge
                                                variant={
                                                    healthVariant[
                                                        project.health
                                                    ] ?? 'default'
                                                }
                                            >
                                                {project.health.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                        </div>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {project.status.replace('_', ' ')} ·{' '}
                                            {project.progress_percentage}%
                                            complete
                                        </p>
                                        <div
                                            className="bg-muted mt-2 h-1.5 w-full max-w-64 overflow-hidden rounded-full"
                                            role="meter"
                                            aria-valuenow={
                                                project.progress_percentage
                                            }
                                            aria-valuemin={0}
                                            aria-valuemax={100}
                                        >
                                            <div
                                                className={cn(
                                                    'h-full rounded-full',
                                                    healthMeterClass[
                                                        project.health
                                                    ] ?? 'bg-status-good',
                                                )}
                                                style={{
                                                    width: `${project.progress_percentage}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground py-8 text-center text-sm">
                            No projects yet.
                        </p>
                    )}
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
