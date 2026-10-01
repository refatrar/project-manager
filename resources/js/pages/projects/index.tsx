import { Head, Link, router, usePage } from '@inertiajs/react';
import { FolderKanban, Pencil, Plus, SearchX, X } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/patterns/empty-state';
import { FilterSelect } from '@/components/patterns/filter-select';
import { PageHeader } from '@/components/patterns/page-header';
import { Pagination } from '@/components/patterns/pagination';
import { ProgressBar } from '@/components/patterns/progress-bar';
import {
    PriorityIndicator,
    StatusBadge,
} from '@/components/patterns/status-badge';
import ProjectFormModal from '@/components/projects/project-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTeamAccess } from '@/hooks/use-team-access';
import { optionLabel } from '@/lib/enum';
import { formatDate } from '@/lib/format';
import { statusMeta } from '@/lib/status';
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

type FilterKey = 'status' | 'priority' | 'health';

export default function ProjectsIndex({
    projects,
    filters,
    statusOptions,
    priorityOptions,
    healthOptions,
    teamMembers,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const can = useTeamAccess();
    const [createOpen, setCreateOpen] = useState(false);
    const [editingProject, setEditingProject] = useState<Project | null>(null);

    const visitWithFilters = (next: Props['filters']) => {
        if (!teamSlug) {
            return;
        }

        router.get(index(teamSlug), next, {
            preserveState: true,
            preserveScroll: true,
            only: ['projects', 'filters'],
        });
    };

    const applyFilter = (key: FilterKey, value: string) => {
        visitWithFilters({
            ...filters,
            [key]: value === 'all' ? undefined : value,
        });
    };

    const activeFilters = (
        [
            ['status', 'Status', statusOptions],
            ['priority', 'Priority', priorityOptions],
            ['health', 'Health', healthOptions],
        ] as const
    )
        .filter(([key]) => filters[key])
        .map(([key, label, options]) => ({
            key,
            label,
            value: optionLabel(
                options as ReadonlyArray<{ value: string; label: string }>,
                filters[key],
            ),
        }));

    const projectUrl = (project: Project) =>
        teamSlug ? show.url([teamSlug, project.id]) : '#';

    return (
        <>
            <Head title="Projects" />

            <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 2xl:p-8">
                <PageHeader
                    title="Projects"
                    description="Every project this team is running."
                    actions={
                        can('projects.create') ? (
                            <ProjectFormModal
                                statusOptions={statusOptions}
                                priorityOptions={priorityOptions}
                                healthOptions={healthOptions}
                                teamMembers={teamMembers}
                                open={createOpen}
                                onOpenChange={setCreateOpen}
                                onSaved={(project) => {
                                    if (teamSlug) {
                                        router.visit(
                                            show.url([teamSlug, project.id]),
                                        );
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
                        ) : null
                    }
                />

                <ProjectFormModal
                    statusOptions={statusOptions}
                    priorityOptions={priorityOptions}
                    healthOptions={healthOptions}
                    teamMembers={teamMembers}
                    project={editingProject}
                    open={editingProject !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditingProject(null);
                        }
                    }}
                    onSaved={() => {
                        router.reload({ only: ['projects'] });
                    }}
                />

                <section aria-label="Filters" className="space-y-3">
                    <div className="grid grid-cols-2 gap-3 sm:max-w-2xl sm:grid-cols-3">
                        <FilterSelect
                            id="filter-status"
                            label="Status"
                            className="col-span-2 sm:col-span-1"
                            value={filters.status ?? 'all'}
                            allLabel="All statuses"
                            options={statusOptions}
                            onValueChange={(value) =>
                                applyFilter('status', value)
                            }
                            data-test="filter-status"
                        />
                        <FilterSelect
                            id="filter-priority"
                            label="Priority"
                            value={filters.priority ?? 'all'}
                            allLabel="All priorities"
                            options={priorityOptions}
                            onValueChange={(value) =>
                                applyFilter('priority', value)
                            }
                            data-test="filter-priority"
                        />
                        <FilterSelect
                            id="filter-health"
                            label="Health"
                            value={filters.health ?? 'all'}
                            allLabel="All health"
                            options={healthOptions}
                            onValueChange={(value) =>
                                applyFilter('health', value)
                            }
                            data-test="filter-health"
                        />
                    </div>

                    <div
                        className="flex min-h-7 flex-wrap items-center gap-2 text-sm"
                        aria-live="polite"
                    >
                        <span className="text-muted-foreground tabular-nums">
                            {projects.total}{' '}
                            {projects.total === 1 ? 'project' : 'projects'}
                        </span>
                        {activeFilters.length > 0 ? (
                            <>
                                <span
                                    className="bg-border h-4 w-px"
                                    aria-hidden="true"
                                />
                                {activeFilters.map((filter) => (
                                    <Badge
                                        key={filter.key}
                                        variant="outline"
                                        className="gap-1 py-0.5 pr-0.5 pl-2"
                                    >
                                        <span className="text-muted-foreground">
                                            {filter.label}:
                                        </span>
                                        {filter.value}
                                        <button
                                            type="button"
                                            className="hover:bg-accent focus-visible:ring-ring ml-0.5 inline-flex size-4 items-center justify-center rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                                            onClick={() =>
                                                applyFilter(filter.key, 'all')
                                            }
                                            aria-label={`Remove ${filter.label.toLowerCase()} filter`}
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </Badge>
                                ))}
                                <Button
                                    variant="link"
                                    size="sm"
                                    className="h-auto px-1"
                                    onClick={() => visitWithFilters({})}
                                >
                                    Clear all
                                </Button>
                            </>
                        ) : null}
                    </div>
                </section>

                {projects.data.length > 0 ? (
                    <section
                        aria-label="Projects"
                        className="bg-card overflow-hidden rounded-lg border"
                    >
                        <div
                            className="text-subtle-foreground hidden grid-cols-[minmax(0,1fr)_9rem_8rem_7rem_10rem_6rem] gap-4 border-b px-5 py-2.5 text-xs font-medium lg:grid"
                            aria-hidden="true"
                        >
                            <span>Project</span>
                            <span>Status</span>
                            <span>Health</span>
                            <span>Priority</span>
                            <span>Progress</span>
                            <span className="text-right">Target</span>
                        </div>
                        <ul className="divide-y">
                            {projects.data.map((project) => (
                                <li
                                    key={project.id}
                                    data-test="project-row"
                                    className="hover:bg-accent/40 group relative flex items-start gap-3 px-4 py-3.5 transition-colors md:px-5"
                                >
                                    <div className="grid min-w-0 flex-1 gap-3 lg:grid-cols-[minmax(0,1fr)_9rem_8rem_7rem_10rem_6rem] lg:items-center lg:gap-4">
                                        <div className="min-w-0">
                                            <Link
                                                href={projectUrl(project)}
                                                className="focus-visible:ring-ring flex min-w-0 items-center gap-2 rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                                            >
                                                <span className="text-subtle-foreground shrink-0 font-mono text-xs">
                                                    {project.code}
                                                </span>
                                                <span className="truncate text-sm font-semibold group-hover:underline group-hover:underline-offset-2">
                                                    {project.name}
                                                </span>
                                                {project.archived_at ? (
                                                    <Badge variant="neutral">
                                                        Archived
                                                    </Badge>
                                                ) : null}
                                            </Link>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 lg:contents">
                                            <div>
                                                <StatusBadge
                                                    kind="project"
                                                    value={project.status}
                                                    label={optionLabel(
                                                        statusOptions,
                                                        project.status,
                                                    )}
                                                />
                                            </div>
                                            <div>
                                                <StatusBadge
                                                    kind="health"
                                                    value={project.health}
                                                    label={optionLabel(
                                                        healthOptions,
                                                        project.health,
                                                    )}
                                                />
                                            </div>
                                            <div>
                                                <PriorityIndicator
                                                    value={project.priority}
                                                    label={optionLabel(
                                                        priorityOptions,
                                                        project.priority,
                                                    )}
                                                />
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3 lg:contents">
                                            <div className="flex min-w-0 flex-1 items-center gap-2.5">
                                                <ProgressBar
                                                    value={
                                                        project.progress_percentage
                                                    }
                                                    label={`${project.name} progress`}
                                                    tone={
                                                        statusMeta(
                                                            'health',
                                                            project.health,
                                                        ).tone
                                                    }
                                                    className="max-w-40"
                                                />
                                                <span className="w-9 shrink-0 text-right text-xs font-medium tabular-nums">
                                                    {
                                                        project.progress_percentage
                                                    }
                                                    %
                                                </span>
                                            </div>
                                            <div className="text-muted-foreground shrink-0 text-xs tabular-nums lg:text-right">
                                                <span className="lg:hidden">
                                                    Target{' '}
                                                </span>
                                                {formatDate(project.end_date)}
                                            </div>
                                        </div>
                                    </div>

                                    {project.can_update ? (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            className="shrink-0 lg:-my-1"
                                            data-test="project-update-button"
                                            onClick={() =>
                                                setEditingProject(project)
                                            }
                                            aria-label={`Edit ${project.name}`}
                                        >
                                            <Pencil />
                                        </Button>
                                    ) : (
                                        <span
                                            className="hidden size-8 shrink-0 lg:block"
                                            aria-hidden="true"
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : activeFilters.length > 0 ? (
                    <EmptyState
                        icon={SearchX}
                        title="No projects match these filters"
                        description="Try removing a filter to see more projects."
                        action={
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => visitWithFilters({})}
                            >
                                Clear filters
                            </Button>
                        }
                    />
                ) : (
                    <EmptyState
                        icon={FolderKanban}
                        title="No projects yet"
                        description="Create a project to organise tasks, members and milestones."
                        action={
                            can('projects.create') ? (
                                <Button
                                    size="sm"
                                    onClick={() => setCreateOpen(true)}
                                >
                                    <Plus /> New project
                                </Button>
                            ) : null
                        }
                    />
                )}

                <Pagination paginator={projects} />
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
