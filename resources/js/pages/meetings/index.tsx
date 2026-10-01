import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/patterns/empty-state';
import { FilterSelect } from '@/components/patterns/filter-select';
import { Pagination } from '@/components/patterns/pagination';
import Heading from '@/components/heading';
import MeetingFormModal from '@/components/meetings/meeting-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTeamAccess } from '@/hooks/use-team-access';
import { optionLabel } from '@/lib/enum';
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

            <div className="mx-auto flex h-full w-full max-w-[1600px] flex-1 flex-col gap-6 p-4 md:p-6 2xl:p-8">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
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

                <section
                    aria-label="Filters"
                    className="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap"
                >
                    <FilterSelect
                        id="meetings-filter-status"
                        label="Status"
                        value={filters.status ?? 'all'}
                        allLabel="All statuses"
                        options={statusOptions}
                        onValueChange={(value) => applyFilter('status', value)}
                        className="sm:w-44"
                        data-test="filter-status"
                    />
                    <FilterSelect
                        id="meetings-filter-type"
                        label="Type"
                        value={filters.type ?? 'all'}
                        allLabel="All types"
                        options={typeOptions}
                        onValueChange={(value) => applyFilter('type', value)}
                        className="sm:w-44"
                        data-test="filter-type"
                    />
                </section>

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
                                        {optionLabel(
                                            statusOptions,
                                            meeting.status,
                                        )}
                                    </Badge>
                                    {meeting.project ? (
                                        <Badge variant="outline">
                                            {meeting.project.code}
                                        </Badge>
                                    ) : null}
                                </div>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {optionLabel(typeOptions, meeting.type)} ·{' '}
                                    {new Date(
                                        meeting.scheduled_start,
                                    ).toLocaleString()}
                                </p>
                            </div>
                        </Link>
                    ))}

                    {meetings.data.length === 0 ? (
                        <EmptyState
                            icon={CalendarDays}
                            title="No meetings yet"
                            description={
                                filters.status || filters.type
                                    ? 'No meetings match these filters.'
                                    : 'Scheduled meetings for this team will appear here.'
                            }
                        />
                    ) : null}
                </div>

                <Pagination paginator={meetings} />
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
