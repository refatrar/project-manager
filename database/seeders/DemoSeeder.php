<?php

namespace Database\Seeders;

use App\Actions\OMS\AssignTask;
use App\Actions\OMS\ChangeTaskStatus;
use App\Actions\OMS\CreateProject;
use App\Actions\OMS\CreateTask;
use App\Enums\MeetingAttendeeRole;
use App\Enums\MeetingStatus;
use App\Enums\Priority;
use App\Enums\ProjectMemberRole;
use App\Enums\TaskAssignmentRole;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Enums\TimeLogActivityType;
use App\Enums\TimeLogSource;
use App\Models\OMS\Meeting;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\Sprint;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\OMS\TimeLog;
use App\Models\OMS\UserWorkSchedule;
use App\Models\Setup\TaskType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Realistic, showcase-scale demo data — one team, two projects, a handful
 * of named people, a sprint, an assignment or two, some logged time, and a
 * meeting with published minutes. Not `ProfilingSeeder`'s job (volume for
 * exercising indexes) — this is what a fresh `migrate:fresh --seed` shows
 * someone exploring the app for the first time, so it uses the same
 * actions and factories the app itself uses, not bulk inserts.
 *
 * A connected Git repository is deliberately not part of this seeder —
 * Git integration is on hold (see TASKS.md's Phase 5).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')],
        );

        $team = $this->team($owner);
        $members = $this->members($team);
        $this->workSchedules($members->concat([$owner]));

        $taskTypes = TaskType::query()->orderBy('name')->limit(6)->get();

        $crm = $this->project($team, $owner, [
            'name' => 'Aurora CRM',
            'code' => 'AUR',
            'description' => 'Customer relationship management platform for the sales team.',
        ]);
        $mobile = $this->project($team, $owner, [
            'name' => 'Beacon Mobile App',
            'code' => 'BEA',
            'description' => 'Companion mobile app for field technicians.',
            // Deliberately not on_track like Aurora CRM - a portfolio of
            // one health status everywhere doesn't show the dashboard's
            // health breakdown doing anything.
            'health' => 'at_risk',
        ]);

        $this->addProjectMembers($crm, $members, ['manager', 'developer', 'designer']);
        $this->addProjectMembers($mobile, $members, ['developer', 'developer', 'qa_engineer']);

        $this->modules($crm, ['Lead Management', 'Reporting Dashboard']);
        $this->modules($mobile, ['Offline Sync', 'Push Notifications']);

        $this->resourceAllocations($crm, $mobile, $members);

        $sprint = Sprint::query()->where('project_id', $crm->id)->where('name', 'Sprint 1')->first()
            ?? Sprint::factory()->active()->create([
                'project_id' => $crm->id,
                'name' => 'Sprint 1',
                'starts_on' => Carbon::today()->subDays(3),
                'ends_on' => Carbon::today()->addDays(10),
            ]);

        $crmTasks = $this->tasks($crm, $taskTypes, $owner, $sprint);
        $mobileTasks = $this->tasks($mobile, $taskTypes, $owner, null);

        $this->assignments($crmTasks, $mobileTasks, $members, $owner);

        $this->timeLogs($team, $crmTasks->concat($mobileTasks), $members->push($owner));
        $this->meeting($team, $crm, $owner, $members);
    }

    private function team(User $owner): Team
    {
        if ($owner->currentTeam !== null && ! $owner->currentTeam->is_personal) {
            return $owner->currentTeam;
        }

        // Not the `CreateTeam` action: it leaves `slug` for `Team`'s own
        // `creating` event to fill in, which breaks if this seeder is ever
        // called from a context using `WithoutModelEvents` (as
        // `ProfilingSeeder` learned the hard way — see MEMORY.md).
        // `Team::factory()` sets `slug` itself, so it doesn't depend on
        // that event either way.
        $team = Team::factory()->create(['name' => 'Kazsoft Demo']);
        $team->memberships()->create(['user_id' => $owner->id, 'role' => TeamRole::Owner]);
        $owner->forceFill(['current_team_id' => $team->id])->save();
        (new LabelSeeder)->run($team);

        return $team;
    }

    /**
     * @return Collection<int, User>
     */
    private function members(Team $team): Collection
    {
        $people = [
            ['name' => 'Alex Rivera', 'email' => 'alex@example.com', 'role' => 'admin'],
            ['name' => 'Jordan Lee', 'email' => 'jordan@example.com', 'role' => 'member'],
            ['name' => 'Sam Patel', 'email' => 'sam@example.com', 'role' => 'member'],
            ['name' => 'Taylor Kim', 'email' => 'taylor@example.com', 'role' => 'member'],
        ];

        return collect($people)->map(function (array $person) use ($team): User {
            $user = User::firstOrCreate(
                ['email' => $person['email']],
                ['name' => $person['name'], 'password' => bcrypt('password')],
            );

            if (! $team->members()->where('user_id', $user->id)->exists()) {
                $team->members()->attach($user, ['role' => $person['role']]);
            }

            return $user;
        });
    }

    /**
     * A standard Mon-Fri, 9-to-5 schedule for everyone — without it, every
     * capacity-driven screen (availability search, timesheet, the team
     * capacity heatmap) has nothing to show on a fresh demo team.
     *
     * @param  Collection<int, User>  $people
     */
    private function workSchedules(Collection $people): void
    {
        if (UserWorkSchedule::query()->whereIn('user_id', $people->pluck('id'))->exists()) {
            return;
        }

        foreach ($people as $person) {
            foreach (range(1, 7) as $dayOfWeek) {
                $isWorkingDay = $dayOfWeek <= 5;

                UserWorkSchedule::factory()->create([
                    'user_id' => $person->id,
                    'day_of_week' => $dayOfWeek,
                    'is_working_day' => $isWorkingDay,
                    'start_time' => $isWorkingDay ? '09:00:00' : null,
                    'end_time' => $isWorkingDay ? '18:00:00' : null,
                    'break_minutes' => $isWorkingDay ? 60 : 0,
                    'capacity_hours' => $isWorkingDay ? 8 : 0,
                    'effective_from' => '2026-01-01',
                ]);
            }
        }
    }

    /**
     * A few forward bookings spanning today, so the team capacity heatmap
     * and availability search show a realistic mix on their default
     * (current) date range instead of an all-zero grid.
     *
     * @param  Collection<int, User>  $members
     */
    private function resourceAllocations(Project $crm, Project $mobile, Collection $members): void
    {
        if (ResourceAllocation::query()->whereIn('project_id', [$crm->id, $mobile->id])->exists()) {
            return;
        }

        $today = Carbon::today();

        ResourceAllocation::factory()->create([
            'user_id' => $members[0]->id,
            'project_id' => $crm->id,
            'starts_on' => $today,
            'ends_on' => $today->copy()->addDays(9),
            'hours_per_day' => 6,
            'allocation_percentage' => 75,
        ]);

        ResourceAllocation::factory()->create([
            'user_id' => $members[1]->id,
            'project_id' => $mobile->id,
            'starts_on' => $today,
            'ends_on' => $today->copy()->addDays(4),
            'hours_per_day' => 8,
            'allocation_percentage' => 100,
        ]);

        ResourceAllocation::factory()->create([
            'user_id' => $members[2]->id,
            'project_id' => $crm->id,
            'starts_on' => $today,
            'ends_on' => $today->copy()->addDays(13),
            'hours_per_day' => 2,
            'allocation_percentage' => 25,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function project(Team $team, User $owner, array $attributes): Project
    {
        $existing = Project::query()->where('team_id', $team->id)->where('code', $attributes['code'])->first();
        if ($existing !== null) {
            return $existing;
        }

        return app(CreateProject::class)->handle($team, $owner, [
            ...$attributes,
            'status' => 'active',
            'priority' => Priority::Medium,
            'start_date' => Carbon::today()->subWeeks(2),
            'end_date' => Carbon::today()->addMonths(3),
        ]);
    }

    /**
     * @param  Collection<int, User>  $members
     * @param  array<int, string>  $roles
     */
    private function addProjectMembers(Project $project, Collection $members, array $roles): void
    {
        foreach ($members as $index => $user) {
            if (ProjectMember::query()->where('project_id', $project->id)->where('user_id', $user->id)->exists()) {
                continue;
            }

            ProjectMember::factory()->create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'role' => ProjectMemberRole::from($roles[$index % count($roles)]),
            ]);
        }
    }

    /**
     * @param  array<int, string>  $names
     */
    private function modules(Project $project, array $names): void
    {
        if ($project->modules()->exists()) {
            return;
        }

        foreach ($names as $index => $name) {
            ProjectModule::factory()->create([
                'project_id' => $project->id,
                'name' => $name,
                'position' => $index,
            ]);
        }
    }

    /**
     * @param  Collection<int, TaskType>  $taskTypes
     * @return Collection<int, Task>
     */
    private function tasks(Project $project, Collection $taskTypes, User $owner, ?Sprint $sprint): Collection
    {
        if ($project->tasks()->exists()) {
            return $project->tasks()->get();
        }

        $statuses = [TaskStatus::Backlog, TaskStatus::Backlog, TaskStatus::InProgress, TaskStatus::InProgress, TaskStatus::Done, TaskStatus::Todo];
        $createTask = app(CreateTask::class);
        $changeTaskStatus = app(ChangeTaskStatus::class);

        return collect($statuses)->map(function (TaskStatus $status) use ($project, $taskTypes, $owner, $sprint, $createTask, $changeTaskStatus): Task {
            // Created via the real action (not the factory) so
            // `projects.next_task_number` stays correct for whatever task
            // someone creates next through the actual UI on this project.
            $task = $createTask->handle($project, $owner, [
                'task_type_id' => $taskTypes->random()->id,
                'sprint_id' => $sprint?->id,
                'title' => fake()->sentence(4),
                'priority' => fake()->randomElement([Priority::Low, Priority::Medium, Priority::High]),
            ]);
            // `status` has a DB-level default only — `$task` has no
            // in-memory value for it until read back, and `ChangeTaskStatus`
            // needs a real `TaskStatus` instance to diff against.
            $task = $task->fresh();

            if ($status !== TaskStatus::Backlog) {
                $changeTaskStatus->handle($task, $status, 0, $owner);
                $task = $task->fresh();
            }

            return $task;
        });
    }

    /**
     * @param  Collection<int, Task>  $crmTasks
     * @param  Collection<int, Task>  $mobileTasks
     * @param  Collection<int, User>  $members
     */
    private function assignments(Collection $crmTasks, Collection $mobileTasks, Collection $members, User $owner): void
    {
        if (TaskAssignment::query()->whereIn('task_id', $crmTasks->concat($mobileTasks)->pluck('id'))->exists()) {
            return;
        }

        $assignTask = app(AssignTask::class);
        foreach ($crmTasks->take(4)->values() as $index => $task) {
            $assignTask->assign($task, $members[$index % $members->count()]->id, TaskAssignmentRole::Assignee, null, $owner);
        }
        foreach ($mobileTasks->take(4)->values() as $index => $task) {
            $assignTask->assign($task, $members[($index + 1) % $members->count()]->id, TaskAssignmentRole::Assignee, null, $owner);
        }
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, User>  $users
     */
    private function timeLogs(Team $team, Collection $tasks, Collection $users): void
    {
        if (TimeLog::query()->where('team_id', $team->id)->exists()) {
            return;
        }

        foreach ($tasks->take(6) as $index => $task) {
            $user = $users[$index % $users->count()];
            $startedAt = Carbon::today()->subDays($index)->setTime(9, 0);

            TimeLog::factory()->create([
                'team_id' => $team->id,
                'user_id' => $user->id,
                'project_id' => $task->project_id,
                'task_id' => $task->id,
                'activity_type' => TimeLogActivityType::Development,
                'source' => TimeLogSource::Manual,
                'started_at' => $startedAt,
                'ended_at' => $startedAt->copy()->addHours(2),
                'duration_minutes' => 120,
                'logged_on' => $startedAt->toDateString(),
            ]);
        }
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function meeting(Team $team, Project $project, User $organizer, Collection $members): void
    {
        if (Meeting::query()->where('team_id', $team->id)->exists()) {
            return;
        }

        $scheduledStart = Carbon::today()->subDays(2)->setTime(10, 0);

        $meeting = new Meeting([
            'project_id' => $project->id,
            'title' => 'Aurora CRM Sprint Kickoff',
            'type' => 'planning',
            'agenda' => 'Review sprint goals and confirm task assignments.',
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledStart->copy()->addHour(),
        ]);
        $meeting->team_id = $team->id;
        $meeting->status = MeetingStatus::Completed;
        $meeting->organized_by = $organizer->id;
        $meeting->minutes = 'Walked through the sprint board. Everyone confirmed their assigned tasks and flagged no blockers.';
        $meeting->decisions = 'Sprint 1 goal confirmed: ship the lead management module.';
        $meeting->recorded_by = $organizer->id;
        $meeting->minutes_published_at = $scheduledStart->copy()->addHours(2);
        $meeting->created_by = $organizer->id;
        $meeting->save();

        $meeting->attendees()->create([
            'user_id' => $organizer->id,
            'role' => MeetingAttendeeRole::Organizer,
            'attendance_status' => 'attended',
            'responded_at' => $scheduledStart,
            'joined_at' => $scheduledStart,
        ]);

        foreach ($members->take(3) as $index => $member) {
            $meeting->attendees()->create([
                'user_id' => $member->id,
                'role' => $index === 0 ? MeetingAttendeeRole::NoteTaker : MeetingAttendeeRole::Participant,
                'attendance_status' => 'attended',
                'responded_at' => $scheduledStart,
                'joined_at' => $scheduledStart,
            ]);
        }

        $meeting->agendaItems()->create([
            'title' => 'Review sprint goals',
            'position' => 1,
            'is_discussed' => true,
        ]);
        $meeting->agendaItems()->create([
            'title' => 'Confirm task assignments',
            'position' => 2,
            'is_discussed' => true,
        ]);
    }
}
