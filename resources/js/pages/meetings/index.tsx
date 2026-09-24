import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import MeetingFormModal from '@/components/meetings/meeting-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTeamAccess } from '@/hooks/use-team-access';
import { index, show } from '@/routes/meetings';
import type {
    Meeting,
    MeetingStatus,
    MeetingStatusOption,
    MeetingType,
    MeetingTypeOption,
    Paginated,
    ProjectOption,
} from '@/types';

type Props = {
    meetings: Paginated<Meeting>;
    filters: {
        status?: MeetingStatus;
        type?: MeetingType;
    };
    projects: ProjectOption[];
    statusOptions: MeetingStatusOption[];
    typeOptions: MeetingTypeOption[];
};

const statusVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    scheduled: 'default',
    in_progress: 'secondary',
    completed: 'outline',
    cancelled: 'destructive',
};

export default function MeetingsIndex({
    meetings,
    filters,
    projects,
    statusOptions,
    typeOptions,
}: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const can = useTeamAccess();
    const [createOpen, setCreateOpen] = useState(false);

    const applyFilter = (key: 'status' | 'type', value: string) => {
        if (!teamSlug) {
            return;
        }

        router.get(
            index(teamSlug),
            { ...filters, [key]: value === 'all' ? undefined : value },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['meetings', 'filters'],
            },
        );
    };

    return (
        <>
            <Head title="Meetings" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title="Meetings"
                        description="Every meeting scheduled for this team."
                    />

                    {can('meetings.create') ? (
                        <MeetingFormModal
                            typeOptions={typeOptions}
                            projects={projects}
                            open={createOpen}
                            onOpenChange={setCreateOpen}
                            onSaved={(meeting) => {
                                if (teamSlug) {
                                    router.visit(
                                        show.url([teamSlug, meeting.id]),
                                    );
                                }
                            }}
                        >
                            <Button
                                type="button"
                                data-test="meetings-create-button"
                            >
                                <Plus /> Schedule meeting
                            </Button>
                        </MeetingFormModal>
                    ) : null}
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
                        value={filters.type ?? 'all'}
                        onValueChange={(value) => applyFilter('type', value)}
                    >
                        <SelectTrigger className="w-40" data-test="filter-type">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All types</SelectItem>
                            {typeOptions.map((option) => (
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
                    {meetings.data.map((meeting) => (
                        <Link
                            key={meeting.id}
                            href={
                                teamSlug
                                    ? show.url([teamSlug, meeting.id])
                                    : '#'
                            }
                            data-test="meeting-row"
                            className="hover:bg-accent flex items-center justify-between gap-4 rounded-lg border p-4"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-medium">
                                        {meeting.title}
                                    </span>
                                    <Badge
                                        variant={
                                            statusVariant[meeting.status] ??
                                            'default'
                                        }
                                    >
                                        {meeting.status.replace('_', ' ')}
                                    </Badge>
                                    {meeting.project ? (
                                        <Badge variant="outline">
                                            {meeting.project.code}
                                        </Badge>
                                    ) : null}
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {meeting.type.replace('_', ' ')} ·{' '}
                                    {new Date(
                                        meeting.scheduled_start,
                                    ).toLocaleString()}
                                </p>
                            </div>
                        </Link>
                    ))}

                    {meetings.data.length === 0 ? (
                        <p className="text-muted-foreground py-8 text-center">
                            No meetings yet.
                        </p>
                    ) : null}
                </div>

                {meetings.last_page > 1 ? (
                    <div className="flex flex-wrap items-center justify-center gap-2">
                        {meetings.links.map((link, linkIndex) =>
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

MeetingsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Meetings',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
