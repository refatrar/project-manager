import { useHttp, usePage } from '@inertiajs/react';
import { Info } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { store, update } from '@/routes/projects';
import type {
    Priority,
    PriorityOption,
    Project,
    ProjectDetail,
    ProjectHealth,
    ProjectHealthOption,
    ProjectStatus,
    ProjectStatusOption,
    TeamMemberOption,
} from '@/types';

export type ProjectFormData = {
    code: string;
    name: string;
    description: string;
    status: ProjectStatus;
    priority: Priority;
    health: ProjectHealth;
    client_name: string;
    start_date: string;
    end_date: string;
    estimated_hours: string;
    budget: string;
    currency: string;
    project_lead_id: string;
};

const NO_PROJECT_LEAD = 'none';

export type ProjectSavedResponse = {
    project: ProjectDetail;
    message: string;
};

type Props = {
    project?: Project | ProjectDetail | null;
    statusOptions: ProjectStatusOption[];
    priorityOptions: PriorityOption[];
    healthOptions: ProjectHealthOption[];
    teamMembers: TeamMemberOption[];
    onSaved?: (project: ProjectDetail, message: string) => void;
    onCancel?: () => void;
};

function isDetail(
    project: Project | ProjectDetail | null | undefined,
): project is ProjectDetail {
    return (
        project !== null && project !== undefined && 'description' in project
    );
}

function HintButton({ label, hint }: { label: string; hint: string }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    className="text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex size-4 shrink-0 items-center justify-center rounded-full focus-visible:ring-2 focus-visible:outline-hidden"
                    aria-label={`About ${label}`}
                >
                    <Info className="size-3.5" />
                </button>
            </TooltipTrigger>
            <TooltipContent className="z-[100] max-w-xs text-left leading-relaxed">
                {hint}
            </TooltipContent>
        </Tooltip>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <section className="space-y-4">
            <h3 className="flex items-center gap-1.5 text-sm font-semibold">
                {title}
                <HintButton label={title} hint={description} />
            </h3>
            {children}
        </section>
    );
}

function Field({
    id,
    label,
    required = false,
    hint,
    error,
    className,
    children,
}: {
    id: string;
    label: string;
    required?: boolean;
    hint?: string;
    error?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'grid w-full gap-2 [&_[data-slot=select-trigger]]:w-full',
                className,
            )}
        >
            <div className="flex items-center gap-1.5">
                <Label htmlFor={id}>
                    {label}
                    {required ? (
                        <span className="text-destructive ml-0.5" aria-hidden>
                            *
                        </span>
                    ) : null}
                </Label>
                {hint ? <HintButton label={label} hint={hint} /> : null}
            </div>
            {children}
            <InputError message={error} />
        </div>
    );
}

export default function ProjectForm({
    project = null,
    statusOptions,
    priorityOptions,
    healthOptions,
    teamMembers,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const detail = isDetail(project) ? project : null;
    const isEditing = project !== null;

    const form = useHttp<ProjectFormData, ProjectSavedResponse>(
        () =>
            project
                ? update.put([teamSlug ?? '', project.id])
                : store(teamSlug ?? ''),
        {
            code: project?.code ?? '',
            name: project?.name ?? '',
            description: detail?.description ?? '',
            status: project?.status ?? 'planning',
            priority: project?.priority ?? 'medium',
            health: project?.health ?? 'on_track',
            client_name: detail?.client_name ?? '',
            start_date: project?.start_date ?? '',
            end_date: project?.end_date ?? '',
            estimated_hours: detail?.estimated_hours ?? '',
            budget: detail?.budget ?? '',
            currency: detail?.currency ?? '',
            project_lead_id: detail?.project_lead
                ? String(detail.project_lead.id)
                : '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.project, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 space-y-8 overflow-y-auto px-6 py-5">
                <Section
                    title="Basics"
                    description="The name people see, and a short code used in lists."
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            id="project-name"
                            label="Name"
                            required
                            className="sm:col-span-2"
                            error={form.errors.name}
                        >
                            <Input
                                id="project-name"
                                name="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Website redesign"
                                required
                                data-test="project-name"
                            />
                        </Field>

                        <Field
                            id="project-code"
                            label="Code"
                            required
                            hint={
                                isEditing
                                    ? 'The code stays the same after the project is created.'
                                    : 'Letters and numbers only, for example WEB. This cannot be changed later.'
                            }
                            error={form.errors.code}
                        >
                            <Input
                                id="project-code"
                                name="code"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="WEB"
                                required
                                disabled={isEditing}
                                data-test="project-code"
                            />
                        </Field>
                    </div>

                    <Field
                        id="project-description"
                        label="Description"
                        hint="What this project is for, and what done looks like."
                        error={form.errors.description}
                    >
                        <Textarea
                            id="project-description"
                            name="description"
                            value={form.data.description}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            placeholder="Rebuild the marketing site and launch it in Q2."
                            rows={3}
                            data-test="project-description"
                        />
                    </Field>
                </Section>

                <Section
                    title="People"
                    description="Who leads the work, and who it is for."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="project-lead"
                            label="Project lead"
                            hint="Can create and assign tasks and manage meetings on this project. Leave this blank until you choose someone."
                            error={form.errors.project_lead_id}
                        >
                            <Select
                                name="project_lead_id"
                                value={
                                    form.data.project_lead_id ||
                                    NO_PROJECT_LEAD
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'project_lead_id',
                                        value === NO_PROJECT_LEAD ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="project-lead"
                                    data-test="project-lead"
                                >
                                    <SelectValue placeholder="No project lead" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_PROJECT_LEAD}>
                                        No project lead
                                    </SelectItem>
                                    {teamMembers.map((member) => (
                                        <SelectItem
                                            key={member.id}
                                            value={String(member.id)}
                                        >
                                            {member.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>

                        <Field
                            id="project-client-name"
                            label="Client"
                            hint="The external client, if this work is for someone outside the team."
                            error={form.errors.client_name}
                        >
                            <Input
                                id="project-client-name"
                                name="client_name"
                                value={form.data.client_name}
                                onChange={(event) =>
                                    form.setData(
                                        'client_name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Acme Co."
                                data-test="project-client-name"
                            />
                        </Field>
                    </div>
                </Section>

                <Section
                    title="Status"
                    description="These start with sensible defaults. Change them when the project moves."
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            id="project-status"
                            label="Status"
                            hint="Where the project is in its life."
                            error={form.errors.status}
                        >
                            <Select
                                name="status"
                                value={form.data.status}
                                onValueChange={(value) =>
                                    form.setData(
                                        'status',
                                        value as ProjectStatus,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="project-status"
                                    data-test="project-status"
                                >
                                    <SelectValue placeholder="Select a status" />
                                </SelectTrigger>
                                <SelectContent>
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
                        </Field>

                        <Field
                            id="project-priority"
                            label="Priority"
                            hint="How urgent this is compared with other work."
                            error={form.errors.priority}
                        >
                            <Select
                                name="priority"
                                value={form.data.priority}
                                onValueChange={(value) =>
                                    form.setData('priority', value as Priority)
                                }
                            >
                                <SelectTrigger
                                    id="project-priority"
                                    data-test="project-priority"
                                >
                                    <SelectValue placeholder="Select a priority" />
                                </SelectTrigger>
                                <SelectContent>
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
                        </Field>

                        <Field
                            id="project-health"
                            label="Health"
                            hint="Whether delivery is on track."
                            error={form.errors.health}
                        >
                            <Select
                                name="health"
                                value={form.data.health}
                                onValueChange={(value) =>
                                    form.setData(
                                        'health',
                                        value as ProjectHealth,
                                    )
                                }
                            >
                                <SelectTrigger
                                    id="project-health"
                                    data-test="project-health"
                                >
                                    <SelectValue placeholder="Select health" />
                                </SelectTrigger>
                                <SelectContent>
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
                        </Field>
                    </div>
                </Section>

                <Section
                    title="Schedule"
                    description="Leave these blank if the dates are not set yet. The end date must be on or after the start date."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="project-start-date"
                            label="Start date"
                            error={form.errors.start_date}
                        >
                            <Input
                                id="project-start-date"
                                name="start_date"
                                type="date"
                                value={form.data.start_date}
                                onChange={(event) =>
                                    form.setData(
                                        'start_date',
                                        event.target.value,
                                    )
                                }
                                data-test="project-start-date"
                            />
                        </Field>

                        <Field
                            id="project-end-date"
                            label="End date"
                            error={form.errors.end_date}
                        >
                            <Input
                                id="project-end-date"
                                name="end_date"
                                type="date"
                                value={form.data.end_date}
                                min={form.data.start_date || undefined}
                                onChange={(event) =>
                                    form.setData('end_date', event.target.value)
                                }
                                data-test="project-end-date"
                            />
                        </Field>
                    </div>
                </Section>

                <Section
                    title="Effort and budget"
                    description="A rough plan for hours and money. Currency is a 3-letter code, such as USD."
                >
                    <div className="grid gap-4 sm:grid-cols-[1fr_1fr_7rem]">
                        <Field
                            id="project-estimated-hours"
                            label="Estimated hours"
                            error={form.errors.estimated_hours}
                        >
                            <Input
                                id="project-estimated-hours"
                                name="estimated_hours"
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.estimated_hours}
                                onChange={(event) =>
                                    form.setData(
                                        'estimated_hours',
                                        event.target.value,
                                    )
                                }
                                placeholder="120"
                                data-test="project-estimated-hours"
                            />
                        </Field>

                        <Field
                            id="project-budget"
                            label="Budget"
                            error={form.errors.budget}
                        >
                            <Input
                                id="project-budget"
                                name="budget"
                                type="number"
                                min="0"
                                step="0.01"
                                value={form.data.budget}
                                onChange={(event) =>
                                    form.setData('budget', event.target.value)
                                }
                                placeholder="10000"
                                data-test="project-budget"
                            />
                        </Field>

                        <Field
                            id="project-currency"
                            label="Currency"
                            error={form.errors.currency}
                        >
                            <Input
                                id="project-currency"
                                name="currency"
                                maxLength={3}
                                value={form.data.currency}
                                onChange={(event) =>
                                    form.setData(
                                        'currency',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="USD"
                                data-test="project-currency"
                            />
                        </Field>
                    </div>
                </Section>
            </div>

            <div className="bg-muted/40 flex shrink-0 flex-col-reverse gap-2 border-t px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-muted-foreground text-xs">
                    Fields marked with * are required.
                </p>
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    {onCancel ? (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={onCancel}
                        >
                            Cancel
                        </Button>
                    ) : null}

                    <Button
                        type="submit"
                        disabled={form.processing || !teamSlug}
                        data-test="project-submit"
                    >
                        {form.processing
                            ? isEditing
                                ? 'Saving…'
                                : 'Creating…'
                            : isEditing
                              ? 'Save changes'
                              : 'Create project'}
                    </Button>
                </div>
            </div>
        </form>
    );
}
