import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import ProjectFormModal from '@/components/projects/project-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, show } from '@/routes/projects';
import type {
    Paginated,
    Priority,
    PriorityOption,
    Project,
    ProjectHealth,
    ProjectHealthOption,
    ProjectStatus,
    ProjectStatusOption,
    TeamMemberOption,
} from '@/types';

type Props = {
    projects: Paginated<Project>;
    filters: {
        status?: ProjectStatus;
        priority?: Priority;
        health?: ProjectHealth;
    };
    statusOptions: ProjectStatusOption[];
    priorityOptions: PriorityOption[];
    healthOptions: ProjectHealthOption[];
    teamMembers: TeamMemberOption[];
};

const healthVariant: Record<
    ProjectHealth,
    'default' | 'secondary' | 'destructive'
> = {
    on_track: 'default',
    at_risk: 'secondary',
    off_track: 'destructive',
};

export default function ProjectsIndex({
    projects,
    filters,
    statusOptions,
    priorityOptions,
    healthOptions,
    teamMembers,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [createOpen, setCreateOpen] = useState(false);

    const applyFilter = (
        key: 'status' | 'priority' | 'health',
        value: string,
    ) => {
        if (!teamSlug) {
            return;
        }

        router.get(
            index(teamSlug),
            { ...filters, [key]: value === 'all' ? undefined : value },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['projects', 'filters'],
            },
        );
    };

    return (
        <>
            <Head title="Projects" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Projects"
                        description="Every project this team is running."
                    />

                    <ProjectFormModal
                        statusOptions={statusOptions}
                        priorityOptions={priorityOptions}
                        healthOptions={healthOptions}
                        teamMembers={teamMembers}
                        open={createOpen}
                        onOpenChange={setCreateOpen}
                        onSaved={(project) => {
                            if (teamSlug) {
                                router.visit(show.url([teamSlug, project.id]));
                            }
                        }}
                    >
                        <Button
                            type="button"
                            data-test="projects-create-button"
                        >
                            <Plus /> New project
                        </Button>
                    </ProjectFormModal>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) => applyFilter('status', value)}
                    >
                        <SelectTrigger
                            className="w-40"
                            data-test="filter-status"
                        >
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {statusOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.priority ?? 'all'}
                        onValueChange={(value) =>
                            applyFilter('priority', value)
                        }
                    >
                        <SelectTrigger
                            className="w-40"
                            data-test="filter-priority"
                        >
                            <SelectValue placeholder="Priority" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All priorities</SelectItem>
                            {priorityOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.health ?? 'all'}
                        onValueChange={(value) => applyFilter('health', value)}
                    >
                        <SelectTrigger
                            className="w-40"
                            data-test="filter-health"
                        >
                            <SelectValue placeholder="Health" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All health</SelectItem>
                            {healthOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-3">
                    {projects.data.map((project) => (
                        <Link
                            key={project.id}
                            href={
                                teamSlug
                                    ? show.url([teamSlug, project.id])
                                    : '#'
                            }
                            data-test="project-row"
                            className="hover:bg-accent flex items-center justify-between gap-4 rounded-lg border p-4"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-muted-foreground font-mono text-xs">
                                        {project.code}
                                    </span>
                                    <span className="font-medium">
                                        {project.name}
                                    </span>
                                    <Badge
                                        variant={healthVariant[project.health]}
                                    >
                                        {project.health.replace('_', ' ')}
                                    </Badge>
                                    {project.archived_at ? (
                                        <Badge variant="outline">
                                            Archived
                                        </Badge>
                                    ) : null}
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {project.status.replace('_', ' ')} ·{' '}
                                    {project.priority} priority ·{' '}
                                    {project.progress_percentage}% complete
                                </p>
                            </div>
                        </Link>
                    ))}

                    {projects.data.length === 0 ? (
                        <p className="text-muted-foreground py-8 text-center">
                            No projects yet.
                        </p>
                    ) : null}
                </div>

                {projects.last_page > 1 ? (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        {projects.links.map((link, linkIndex) =>
                            link.url ? (
                                <Button
                                    key={`${link.label}-${linkIndex}`}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link href={link.url} preserveState>
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    key={`${link.label}-${linkIndex}`}
                                    variant="outline"
                                    size="sm"
                                    disabled
                                >
                                    <span
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                </Button>
                            ),
                        )}
                    </div>
                ) : null}
            </div>
        </>
    );
}

ProjectsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Projects',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
