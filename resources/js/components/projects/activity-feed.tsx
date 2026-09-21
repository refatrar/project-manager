import { useMemo, useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Activity, ProjectMember } from '@/types';

type Props = {
    activities: Activity[];
    members: ProjectMember[];
};

const EVENT_LABELS: Record<string, string> = {
    'project.created': 'Project',
    'project.archived': 'Project',
    'project.deleted': 'Project',
    'module.created': 'Module',
    'module.deleted': 'Module',
    'member.added': 'Member',
    'member.removed': 'Member',
    'task.created': 'Task',
    'task.deleted': 'Task',
    'task.status_changed': 'Status',
    'task.assigned': 'Assignment',
    'task.unassigned': 'Assignment',
    'milestone.created': 'Milestone',
    'milestone.deleted': 'Milestone',
    'sprint.created': 'Sprint',
    'sprint.deleted': 'Sprint',
    'dependency.added': 'Dependency',
    'dependency.removed': 'Dependency',
};

function timeAgo(iso: string): string {
    const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    const units: [number, string][] = [
        [60, 'second'],
        [60, 'minute'],
        [24, 'hour'],
        [7, 'day'],
        [4.34524, 'week'],
        [12, 'month'],
        [Number.POSITIVE_INFINITY, 'year'],
    ];

    let value = seconds;
    for (const [amount, unit] of units) {
        if (value < amount) {
            const rounded = Math.floor(value);
            return `${rounded} ${unit}${rounded === 1 ? '' : 's'} ago`;
        }
        value /= amount;
    }

    return 'just now';
}

export default function ActivityFeed({ activities, members }: Props) {
    const [userId, setUserId] = useState('all');

    const filtered = useMemo(() => {
        if (userId === 'all') {
            return activities;
        }

        return activities.filter((activity) => String(activity.user?.id) === userId);
    }, [activities, userId]);

    return (
        <div className="space-y-4">
            <div className="grid gap-1">
                <Select value={userId} onValueChange={setUserId}>
                    <SelectTrigger className="w-56" data-test="activity-filter-user">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Everyone</SelectItem>
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

            {filtered.length > 0 ? (
                <ol className="space-y-3">
                    {filtered.map((activity) => (
                        <li
                            key={activity.id}
                            data-test="activity-row"
                            className="flex items-start justify-between gap-4 rounded-lg border p-3"
                        >
                            <div className="min-w-0">
                                <p className="text-sm">{activity.description}</p>
                                <p className="text-muted-foreground mt-1 text-xs">
                                    {EVENT_LABELS[activity.event] ?? activity.event}
                                    {activity.user ? ` · ${activity.user.name}` : ''}
                                </p>
                            </div>
                            {activity.createdAt ? (
                                <span
                                    className="text-muted-foreground shrink-0 text-xs"
                                    title={activity.createdAt}
                                >
                                    {timeAgo(activity.createdAt)}
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ol>
            ) : (
                <p className="text-muted-foreground py-8 text-center text-sm">
                    No activity yet.
                </p>
            )}
        </div>
    );
}
