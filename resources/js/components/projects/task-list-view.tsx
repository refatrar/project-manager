import { Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show as showTask } from '@/routes/projects/tasks';
import type {
    MilestoneOption,
    ProjectMember,
    SprintOption,
    Task,
    TaskLabel,
} from '@/types';

type Props = {
    projectId: number;
    tasks: Task[];
    members: ProjectMember[];
    milestones: MilestoneOption[];
    sprints: SprintOption[];
    labels: TaskLabel[];
};

export default function TaskListView({
    projectId,
    tasks,
    members,
    milestones,
    sprints,
    labels,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const [assigneeId, setAssigneeId] = useState('all');
    const [labelId, setLabelId] = useState('all');
    const [milestoneId, setMilestoneId] = useState('all');
    const [sprintId, setSprintId] = useState('all');
    const [dueBefore, setDueBefore] = useState('');
    const [overdueOnly, setOverdueOnly] = useState(false);

    const filtered = useMemo(() => {
        const today = new Date().toISOString().slice(0, 10);

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

            if (overdueOnly && (!dueDate || dueDate >= today)) {
                return false;
            }

            return true;
        });
    }, [tasks, assigneeId, labelId, milestoneId, sprintId, dueBefore, overdueOnly]);

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-end gap-3">
                <div className="grid gap-1">
                    <Label className="text-xs">Assignee</Label>
                    <Select value={assigneeId} onValueChange={setAssigneeId}>
                        <SelectTrigger className="w-40" data-test="filter-assignee">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All assignees</SelectItem>
                            {members.map((member) => (
                                <SelectItem
                                    key={member.user.id}
                                    value={String(member.user.id)}
                                >
                                    {member.user.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-1">
                    <Label className="text-xs">Label</Label>
                    <Select value={labelId} onValueChange={setLabelId}>
                        <SelectTrigger className="w-40" data-test="filter-label">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All labels</SelectItem>
                            {labels.map((label) => (
                                <SelectItem
                                    key={label.id}
                                    value={String(label.id)}
                                >
                                    {label.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-1">
                    <Label className="text-xs">Milestone</Label>
                    <Select value={milestoneId} onValueChange={setMilestoneId}>
                        <SelectTrigger
                            className="w-40"
                            data-test="filter-milestone"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All milestones</SelectItem>
                            {milestones.map((milestone) => (
                                <SelectItem
                                    key={milestone.id}
                                    value={String(milestone.id)}
                                >
                                    {milestone.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-1">
                    <Label className="text-xs">Sprint</Label>
                    <Select value={sprintId} onValueChange={setSprintId}>
                        <SelectTrigger className="w-40" data-test="filter-sprint">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All sprints</SelectItem>
                            {sprints.map((sprint) => (
                                <SelectItem
                                    key={sprint.id}
                                    value={String(sprint.id)}
                                >
                                    {sprint.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-1">
                    <Label className="text-xs">Due before</Label>
                    <Input
                        type="date"
                        className="w-40"
                        value={dueBefore}
                        onChange={(event) => setDueBefore(event.target.value)}
                        data-test="filter-due-before"
                    />
                </div>

                <div className="flex items-center gap-2 pb-2">
                    <Checkbox
                        id="filter-overdue"
                        checked={overdueOnly}
                        onCheckedChange={(checked) =>
                            setOverdueOnly(checked === true)
                        }
                        data-test="filter-overdue"
                    />
                    <Label htmlFor="filter-overdue" className="text-xs">
                        Overdue only
                    </Label>
                </div>
            </div>

            <div className="space-y-2">
                {filtered.map((task) => (
                    <Link
                        key={task.id}
                        href={
                            teamSlug
                                ? showTask.url([teamSlug, projectId, task.id])
                                : '#'
                        }
                        data-test="task-list-row"
                        className="hover:bg-accent flex items-center justify-between gap-4 rounded-lg border p-3"
                    >
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-muted-foreground font-mono text-xs">
                                    {task.reference}
                                </span>
                                <span className="text-sm font-medium">
                                    {task.title}
                                </span>
                                {task.labels.map((label) => (
                                    <Badge
                                        key={label.id}
                                        variant="secondary"
                                        style={
                                            label.color
                                                ? { backgroundColor: label.color }
                                                : undefined
                                        }
                                    >
                                        {label.name}
                                    </Badge>
                                ))}
                            </div>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {task.status.replace('_', ' ')} ·{' '}
                                {task.priority} priority
                                {task.due_at
                                    ? ` · due ${task.due_at.slice(0, 10)}`
                                    : ''}
                                {task.assignees.length > 0
                                    ? ` · ${task.assignees.map((a) => a.name).join(', ')}`
                                    : ''}
                            </p>
                        </div>
                    </Link>
                ))}

                {filtered.length === 0 ? (
                    <p className="text-muted-foreground py-8 text-center text-sm">
                        No tasks match these filters.
                    </p>
                ) : null}
            </div>
        </div>
    );
}
