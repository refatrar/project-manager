import type { LucideIcon } from 'lucide-react';
import {
    Archive,
    Ban,
    Circle,
    CircleAlert,
    CircleCheck,
    CircleDashed,
    CircleDot,
    CirclePause,
    CircleSlash,
    CircleX,
    Clock,
    Eye,
    FlaskConical,
    RotateCcw,
    SignalHigh,
    SignalLow,
    SignalMedium,
    Siren,
    TriangleAlert,
} from 'lucide-react';

/**
 * Visual meaning of an enum value: a tone (design system §2.3) and an icon,
 * so status is never conveyed by colour alone. Labels are not defined here;
 * they come from the server's `options()` (RULES.md §3).
 */
export type Tone = 'neutral' | 'info' | 'success' | 'warning' | 'destructive';

export type StatusMeta = { tone: Tone; icon: LucideIcon };

export type StatusKind =
    | 'task'
    | 'priority'
    | 'health'
    | 'project'
    | 'module'
    | 'milestone'
    | 'sprint'
    | 'approval'
    | 'allocation'
    | 'meeting';

const neutral = (icon: LucideIcon): StatusMeta => ({ tone: 'neutral', icon });

const MAP: Record<StatusKind, Record<string, StatusMeta>> = {
    task: {
        backlog: neutral(CircleDashed),
        todo: neutral(Circle),
        in_progress: { tone: 'info', icon: CircleDot },
        blocked: { tone: 'destructive', icon: Ban },
        in_review: { tone: 'info', icon: Eye },
        changes_requested: { tone: 'warning', icon: RotateCcw },
        ready_for_qa: { tone: 'info', icon: FlaskConical },
        done: { tone: 'success', icon: CircleCheck },
        cancelled: neutral(CircleSlash),
    },
    priority: {
        low: neutral(SignalLow),
        medium: neutral(SignalMedium),
        high: { tone: 'warning', icon: SignalHigh },
        critical: { tone: 'destructive', icon: Siren },
    },
    health: {
        on_track: { tone: 'success', icon: CircleCheck },
        at_risk: { tone: 'warning', icon: TriangleAlert },
        off_track: { tone: 'destructive', icon: CircleX },
    },
    project: {
        planning: neutral(CircleDashed),
        active: { tone: 'info', icon: CircleDot },
        on_hold: { tone: 'warning', icon: CirclePause },
        completed: { tone: 'success', icon: CircleCheck },
        cancelled: neutral(CircleSlash),
        archived: neutral(Archive),
    },
    module: {
        planning: neutral(CircleDashed),
        in_progress: { tone: 'info', icon: CircleDot },
        on_hold: { tone: 'warning', icon: CirclePause },
        completed: { tone: 'success', icon: CircleCheck },
        cancelled: neutral(CircleSlash),
    },
    milestone: {
        pending: neutral(Circle),
        in_progress: { tone: 'info', icon: CircleDot },
        completed: { tone: 'success', icon: CircleCheck },
        missed: { tone: 'destructive', icon: CircleAlert },
        cancelled: neutral(CircleSlash),
    },
    sprint: {
        planned: neutral(CircleDashed),
        active: { tone: 'info', icon: CircleDot },
        completed: { tone: 'success', icon: CircleCheck },
        cancelled: neutral(CircleSlash),
    },
    approval: {
        pending: { tone: 'warning', icon: Clock },
        submitted: { tone: 'info', icon: CircleDot },
        approved: { tone: 'success', icon: CircleCheck },
        rejected: { tone: 'destructive', icon: CircleX },
        cancelled: neutral(CircleSlash),
    },
    allocation: {
        planned: neutral(CircleDashed),
        confirmed: { tone: 'info', icon: CircleDot },
        completed: { tone: 'success', icon: CircleCheck },
        cancelled: neutral(CircleSlash),
    },
    meeting: {
        scheduled: neutral(Clock),
        in_progress: { tone: 'info', icon: CircleDot },
        completed: { tone: 'success', icon: CircleCheck },
        cancelled: neutral(CircleSlash),
    },
};

export function statusMeta(
    kind: StatusKind,
    value: string | null | undefined,
): StatusMeta {
    return (value && MAP[kind][value]) || neutral(Circle);
}

/** Text colour for a tone, for icons and inline text on a normal surface. */
export const toneText: Record<Tone, string> = {
    neutral: 'text-muted-foreground',
    info: 'text-info',
    success: 'text-success',
    warning: 'text-warning',
    destructive: 'text-destructive',
};

/** Solid fill for a tone, for dots, bars and meters. */
export const toneFill: Record<Tone, string> = {
    neutral: 'bg-subtle-foreground',
    info: 'bg-info',
    success: 'bg-success',
    warning: 'bg-warning',
    destructive: 'bg-destructive',
};
