import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, ListFilter } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/patterns/empty-state';
import { FilterSelect } from '@/components/patterns/filter-select';
import { LabelChip } from '@/components/patterns/label-chip';
import {
    PriorityIndicator,
    StatusBadge,
} from '@/components/patterns/status-badge';
import { UserAvatar } from '@/components/patterns/user-avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { optionLabel } from '@/lib/enum';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { show as showTask } from '@/routes/projects/tasks';
import type {
    MilestoneOption,
    PriorityOption,
    ProjectMember,
    SprintOption,
    Task,
    TaskLabel,
    TaskStatusOption,
} from '@/types';

type Props = {
    projectId: number;
    tasks: Task[];
    members: ProjectMember[];
    milestones: MilestoneOption[];
    sprints: SprintOption[];
    labels: TaskLabel[];
    statusOptions: TaskStatusOption[];
    priorityOptions: PriorityOption[];
};

export default function TaskListView({
    projectId,
    tasks,
    members,
    milestones,
    sprints,
    labels,
    statusOptions,
    priorityOptions,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [assigneeId, setAssigneeId] = useState('all');
    const [labelId, setLabelId] = useState('all');
    const [milestoneId, setMilestoneId] = useState('all');
    const [sprintId, setSprintId] = useState('all');
    const [dueBefore, setDueBefore] = useState('');
    const [overdueOnly, setOverdueOnly] = useState(false);

    const filtered = useMemo(() => {
        return tasks.filter((task) => {
            if (
                assigneeId !== 'all' &&
                !task.assignees.some((a) => String(a.id) === assigneeId)
            ) {
                return false;
            }

            if (
                labelId !== 'all' &&
                !task.labels.some((l) => String(l.id) === labelId)
            ) {
                return false;
            }

            if (
                milestoneId !== 'all' &&
                String(task.milestone_id ?? '') !== milestoneId
            ) {
                return false;
            }

            if (sprintId !== 'all' && String(task.sprint_id ?? '') !== sprintId) {
                return false;
            }

            const dueDate = task.due_at?.slice(0, 10) ?? null;

            if (dueBefore && (!dueDate || dueDate > dueBefore)) {
                return false;
            }

            if (overdueOnly && !task.is_overdue) {
                return false;
            }

            return true;
        });
    }, [tasks, assigneeId, labelId, milestoneId, sprintId, dueBefore, overdueOnly]);

    const activeFilterCount = [
        assigneeId !== 'all',
        labelId !== 'all',
        milestoneId !== 'all',
        sprintId !== 'all',
        dueBefore !== '',
        overdueOnly,
    ].filter(Boolean).length;

    const clearFilters = () => {
        setAssigneeId('all');
        setLabelId('all');
        setMilestoneId('all');
        setSprintId('all');
        setDueBefore('');
        setOverdueOnly(false);
    };

    return (
        <div className="space-y-4">
            <section
                aria-label="Task filters"
                className="grid grid-cols-2 items-end gap-3 border-b pb-3 sm:grid-cols-3 lg:flex lg:flex-wrap"
            >
                <FilterSelect
                    id="task-filter-assignee"
                    label="Assignee"
                    value={assigneeId}
                    allLabel="All assignees"
                    options={members.map((member) => ({
                        value: String(member.user.id),
                        label: member.user.name,
                    }))}
                    onValueChange={setAssigneeId}
                    className="lg:w-44"
                    data-test="filter-assignee"
                />
                <FilterSelect
                    id="task-filter-label"
                    label="Label"
                    value={labelId}
                    allLabel="All labels"
                    options={labels.map((label) => ({
                        value: String(label.id),
                        label: label.name,
                    }))}
                    onValueChange={setLabelId}
                    className="lg:w-44"
                    data-test="filter-label"
                />
                <FilterSelect
                    id="task-filter-milestone"
                    label="Milestone"
                    value={milestoneId}
                    allLabel="All milestones"
                    options={milestones.map((milestone) => ({
                        value: String(milestone.id),
                        label: milestone.name,
                    }))}
                    onValueChange={setMilestoneId}
                    className="lg:w-44"
                    data-test="filter-milestone"
                />
                <FilterSelect
                    id="task-filter-sprint"
                    label="Sprint"
                    value={sprintId}
                    allLabel="All sprints"
                    options={sprints.map((sprint) => ({
                        value: String(sprint.id),
                        label: sprint.name,
                    }))}
                    onValueChange={setSprintId}
                    className="lg:w-44"
                    data-test="filter-sprint"
                />
                <div className="grid min-w-0 gap-1.5 lg:w-44">
                    <Label
                        htmlFor="task-filter-due-before"
                        className="text-muted-foreground text-xs"
                    >
                        Due before
                    </Label>
                    <Input
                        id="task-filter-due-before"
                        type="date"
                        className={cn(
                            'h-8',
                            dueBefore &&
                                'border-primary/60 bg-primary/5 dark:bg-primary/10',
                        )}
                        value={dueBefore}
                        onChange={(event) => setDueBefore(event.target.value)}
                        data-test="filter-due-before"
                    />
                </div>
                <div className="flex h-8 items-center gap-2">
                    <Checkbox
                        id="filter-overdue"
                        checked={overdueOnly}
                        onCheckedChange={(checked) =>
                            setOverdueOnly(checked === true)
                        }
                        data-test="filter-overdue"
                    />
                    <Label htmlFor="filter-overdue" className="text-[0.8125rem]">
                        Overdue only
                    </Label>
                </div>
            </section>

            <div
                className="text-muted-foreground flex min-h-7 flex-wrap items-center gap-2 text-sm"
                aria-live="polite"
            >
                <span className="tabular-nums">
                    {activeFilterCount > 0
                        ? `${filtered.length} of ${tasks.length} tasks`
                        : `${tasks.length} ${tasks.length === 1 ? 'task' : 'tasks'}`}
                </span>
                {activeFilterCount > 0 ? (
                    <Button
                        variant="link"
                        size="sm"
                        className="h-7 px-1"
                        onClick={clearFilters}
                    >
                        Clear filters
                    </Button>
                ) : null}
            </div>

            {filtered.length > 0 ? (
                <ul className="divide-y border-b">
                    {filtered.map((task) => (
                        <li key={task.id}>
                            <Link
                                href={
                                    teamSlug
                                        ? showTask.url([teamSlug, projectId, task.id])
                                        : '#'
                                }
                                data-test="task-list-row"
                                className="hover:bg-accent/50 focus-visible:ring-ring flex flex-col gap-2 px-4 py-3 transition-colors focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset md:grid md:grid-cols-[minmax(0,1fr)_9.5rem_6rem_5.5rem_4.5rem] md:items-center md:gap-4"
                            >
                                <div className="min-w-0">
                                    <div className="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
                                        <span className="text-subtle-foreground font-mono text-xs">
                                            {task.reference}
                                        </span>
                                        <span className="text-sm font-medium [overflow-wrap:anywhere]">
                                            {task.title}
                                        </span>
                                    </div>
                                    {task.labels.length > 0 ? (
                                        <div className="mt-1.5 flex flex-wrap gap-1">
                                            {task.labels.map((label) => (
                                                <LabelChip
                                                    key={label.id}
                                                    name={label.name}
                                                    color={label.color}
                                                />
                                            ))}
                                        </div>
                                    ) : null}
                                </div>

                                <div className="flex flex-wrap items-center gap-x-3 gap-y-2 md:contents">
                                    <div>
                                        <StatusBadge
                                            kind="task"
                                            value={task.status}
                                            label={optionLabel(
                                                statusOptions,
                                                task.status,
                                            )}
                                        />
                                    </div>
                                    <PriorityIndicator
                                        value={task.priority}
                                        label={optionLabel(
                                            priorityOptions,
                                            task.priority,
                                        )}
                                        showLabel
                                    />
                                    <span
                                        className={cn(
                                            'inline-flex items-center gap-1 text-xs tabular-nums',
                                            task.is_overdue
                                                ? 'text-destructive-foreground font-medium'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        <CalendarDays
                                            className="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        <span className="sr-only">
                                            {task.is_overdue ? 'Overdue, due ' : 'Due '}
                                        </span>
                                        {formatDate(task.due_at, 'No date')}
                                    </span>
                                    <span className="flex items-center md:justify-end">
                                        {task.assignees.length > 0 ? (
                                            <>
                                                <span
                                                    className="flex -space-x-1.5"
                                                    title={task.assignees
                                                        .map((a) => a.name)
                                                        .join(', ')}
                                                >
                                                    {task.assignees
                                                        .slice(0, 3)
                                                        .map((assignee) => (
                                                            <UserAvatar
                                                                key={assignee.id}
                                                                name={assignee.name}
                                                                size="xs"
                                                            />
                                                        ))}
                                                    {task.assignees.length > 3 ? (
                                                        <span
                                                            className="bg-muted text-muted-foreground ring-card inline-flex size-5 items-center justify-center rounded-full text-[0.625rem] font-semibold ring-2"
                                                            aria-hidden="true"
                                                        >
                                                            +{task.assignees.length - 3}
                                                        </span>
                                                    ) : null}
                                                </span>
                                                <span className="sr-only">
                                                    Assigned to{' '}
                                                    {task.assignees
                                                        .map((a) => a.name)
                                                        .join(', ')}
                                                </span>
                                            </>
                                        ) : (
                                            <span className="text-subtle-foreground text-xs">
                                                Unassigned
                                            </span>
                                        )}
                                    </span>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <EmptyState
                    icon={ListFilter}
                    title={
                        tasks.length === 0
                            ? 'No tasks yet'
                            : 'No tasks match these filters'
                    }
                    description={
                        tasks.length === 0
                            ? 'Tasks added on the board will show up here.'
                            : 'Try removing a filter to see more tasks.'
                    }
                    action={
                        tasks.length > 0 && activeFilterCount > 0 ? (
                            <Button variant="outline" size="sm" onClick={clearFilters}>
                                Clear filters
                            </Button>
                        ) : undefined
                    }
                />
            )}
        </div>
    );
}
