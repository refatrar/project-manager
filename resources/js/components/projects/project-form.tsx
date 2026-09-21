import { useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
};

export type ProjectSavedResponse = {
    project: ProjectDetail;
    message: string;
};

type Props = {
    project?: Project | ProjectDetail | null;
    statusOptions: ProjectStatusOption[];
    priorityOptions: PriorityOption[];
    healthOptions: ProjectHealthOption[];
    onSaved?: (project: ProjectDetail, message: string) => void;
    onCancel?: () => void;
};

function isDetail(
    project: Project | ProjectDetail | null | undefined,
): project is ProjectDetail {
    return project !== null && project !== undefined && 'description' in project;
}

export default function ProjectForm({
    project = null,
    statusOptions,
    priorityOptions,
    healthOptions,
    onSaved,
    onCancel,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const detail = isDetail(project) ? project : null;

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
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2 sm:col-span-1">
                    <Label htmlFor="project-code">Code</Label>
                    <Input
                        id="project-code"
                        name="code"
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value.toUpperCase())
                        }
                        placeholder="ALPHA"
                        required
                        disabled={project !== null}
                        data-test="project-code"
                    />
                    <InputError message={form.errors.code} />
                </div>

                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="project-name">Name</Label>
                    <Input
                        id="project-name"
                        name="name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="Alpha Platform"
                        required
                        data-test="project-name"
                    />
                    <InputError message={form.errors.name} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="project-description">Description</Label>
                <Textarea
                    id="project-description"
                    name="description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    placeholder="Optional details about this project"
                    data-test="project-description"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="project-status">Status</Label>
                    <Select
                        name="status"
                        value={form.data.status}
                        onValueChange={(value) =>
                            form.setData('status', value as ProjectStatus)
                        }
                    >
                        <SelectTrigger id="project-status" data-test="project-status">
                            <SelectValue placeholder="Select a status" />
                        </SelectTrigger>
                        <SelectContent>
                            {statusOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.status} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="project-priority">Priority</Label>
                    <Select
                        name="priority"
                        value={form.data.priority}
                        onValueChange={(value) =>
                            form.setData('priority', value as Priority)
                        }
                    >
                        <SelectTrigger id="project-priority" data-test="project-priority">
                            <SelectValue placeholder="Select a priority" />
                        </SelectTrigger>
                        <SelectContent>
                            {priorityOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.priority} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="project-health">Health</Label>
                    <Select
                        name="health"
                        value={form.data.health}
                        onValueChange={(value) =>
                            form.setData('health', value as ProjectHealth)
                        }
                    >
                        <SelectTrigger id="project-health" data-test="project-health">
                            <SelectValue placeholder="Select a health" />
                        </SelectTrigger>
                        <SelectContent>
                            {healthOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={form.errors.health} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="project-start-date">Start date</Label>
                    <Input
                        id="project-start-date"
                        name="start_date"
                        type="date"
                        value={form.data.start_date}
                        onChange={(event) =>
                            form.setData('start_date', event.target.value)
                        }
                        data-test="project-start-date"
                    />
                    <InputError message={form.errors.start_date} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="project-end-date">End date</Label>
                    <Input
                        id="project-end-date"
                        name="end_date"
                        type="date"
                        value={form.data.end_date}
                        onChange={(event) =>
                            form.setData('end_date', event.target.value)
                        }
                        data-test="project-end-date"
                    />
                    <InputError message={form.errors.end_date} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-2">
                    <Label htmlFor="project-estimated-hours">Estimated hours</Label>
                    <Input
                        id="project-estimated-hours"
                        name="estimated_hours"
                        type="number"
                        min="0"
                        step="0.01"
                        value={form.data.estimated_hours}
                        onChange={(event) =>
                            form.setData('estimated_hours', event.target.value)
                        }
                        data-test="project-estimated-hours"
                    />
                    <InputError message={form.errors.estimated_hours} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="project-budget">Budget</Label>
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
                        data-test="project-budget"
                    />
                    <InputError message={form.errors.budget} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="project-currency">Currency</Label>
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
                    <InputError message={form.errors.currency} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="project-client-name">Client name</Label>
                <Input
                    id="project-client-name"
                    name="client_name"
                    value={form.data.client_name}
                    onChange={(event) =>
                        form.setData('client_name', event.target.value)
                    }
                    placeholder="Optional external client"
                    data-test="project-client-name"
                />
                <InputError message={form.errors.client_name} />
            </div>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {onCancel ? (
                    <Button type="button" variant="secondary" onClick={onCancel}>
                        Cancel
                    </Button>
                ) : null}

                <Button
                    type="submit"
                    disabled={form.processing || !teamSlug}
                    data-test="project-submit"
                >
                    {project ? 'Save changes' : 'Create project'}
                </Button>
            </div>
        </form>
    );
}
