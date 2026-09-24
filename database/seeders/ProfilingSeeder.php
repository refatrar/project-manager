<?php

namespace Database\Seeders;

use App\Enums\AllocationStatus;
use App\Enums\ApprovalStatus;
use App\Enums\Priority;
use App\Enums\ProjectHealth;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectMemberStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskAssignmentStatus;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Enums\TimeLogActivityType;
use App\Enums\TimeLogSource;
use App\Enums\TimeOffType;
use App\Models\OMS\Project;
use App\Models\Setup\TaskType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Not wired into `DatabaseSeeder` — this is opt-in, run-when-needed volume
 * data for profiling the availability and progress queries against
 * realistic indexes (Cross-Cutting: "Seed data large enough to profile the
 * availability and progress queries" / "Query-plan review of every
 * composite index"), not demo/showcase data for a screenshot.
 *
 * Run with: php artisan db:seed --class=ProfilingSeeder
 *
 * Writes with raw bulk inserts wherever the row shape is simple and
 * uniform, rather than one Eloquent `create()` call per row — at this
 * volume (tens of thousands of rows across time_logs/resource_allocations
 * alone) the query-per-row cost of factories dominates seed time.
 */
class ProfilingSeeder extends Seeder
{
    use WithoutModelEvents;

    private const USER_COUNT = 150;

    private const PROJECT_COUNT = 30;

    private const TASKS_PER_PROJECT = 40;

    public function run(): void
    {
        $this->command->info('Seeding profiling data — this writes tens of thousands of rows, expect a minute or two.');

        $owner = User::factory()->create([
            'name' => 'Profiling Owner',
            'email' => 'profiling-owner@example.com',
        ]);

        // Not the `CreateTeam` action: it leaves `slug` for `Team`'s own
        // `creating` event to fill in, which `WithoutModelEvents` (this
        // whole seeder) suppresses. `Team::factory()` sets `slug` itself
        // in its own definition, so it doesn't depend on that event.
        $team = Team::factory()->create(['name' => 'Profiling Team']);
        $team->memberships()->create(['user_id' => $owner->id, 'role' => TeamRole::TeamLead]);
        $owner->forceFill(['current_team_id' => $team->id])->save();

        $members = User::factory(self::USER_COUNT)->create();
        // `concat`, not `push` — `push` mutates `$members` in place and
        // returns the same instance, which would silently fold the owner
        // into the team-membership rows built from `$members` below.
        $allUsers = $members->concat([$owner]);

        $now = Carbon::now();
        $membershipRows = $members->map(fn (User $user): array => [
            'team_id' => $team->id,
            'user_id' => $user->id,
            'role' => TeamRole::Member->value,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        foreach (array_chunk($membershipRows, 500) as $chunk) {
            DB::table('team_members')->insert($chunk);
        }

        $this->seedWorkSchedule();
        $this->seedTimeOffRequests($allUsers, $team->id);

        $taskTypeIds = TaskType::query()->pluck('id');
        if ($taskTypeIds->isEmpty()) {
            $taskTypeIds = TaskType::factory(6)->create()->pluck('id');
        }

        $projects = Project::factory(self::PROJECT_COUNT)->create([
            'team_id' => $team->id,
            'status' => ProjectStatus::Active,
            'health' => ProjectHealth::OnTrack,
        ]);

        $this->seedProjectMembers($projects, $allUsers);
        $taskIds = $this->seedTasks($projects, $taskTypeIds);
        $this->seedTaskAssignments($taskIds, $allUsers);
        $this->seedResourceAllocations($projects, $allUsers, $taskIds);
        $this->seedTimeLogs($allUsers, $projects, $taskIds, $team->id);

        $this->command->info(sprintf(
            'Done: %d users, %d projects, %d tasks, %d time logs, %d resource allocations.',
            self::USER_COUNT + 1,
            self::PROJECT_COUNT,
            $taskIds->count(),
            DB::table('time_logs')->where('team_id', $team->id)->count(),
            DB::table('resource_allocations')->whereIn('project_id', $projects->pluck('id'))->count(),
        ));
    }

    /**
     * The schedule is global now (TASKS.md 7.10), not per-user — one
     * Monday–Friday open-ended version covers every profiled user, which
     * also shrinks this seeder's own volume/time versus the old per-user
     * pass.
     */
    private function seedWorkSchedule(): void
    {
        $now = Carbon::now();
        $rows = [];

        foreach (range(1, 5) as $dayOfWeek) {
            $rows[] = [
                'day_of_week' => $dayOfWeek,
                'is_working_day' => true,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'break_minutes' => 60,
                'capacity_hours' => 8,
                'effective_from' => '2026-01-01',
                'effective_until' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('work_schedules')->insert($rows);
    }

    /**
     * A minority of users have a time-off request somewhere in the next
     * 60 days, roughly 2/3 already approved — real signal for
     * `approvedBetween` to filter against.
     *
     * @param  Collection<int, User>  $users
     */
    private function seedTimeOffRequests($users, int $teamId): void
    {
        $rows = [];
        $now = Carbon::now();
        $sample = $users->random((int) ceil($users->count() * 0.2));

        foreach ($sample as $user) {
            $startsOn = Carbon::today()->addDays(random_int(-30, 60));
            $endsOn = $startsOn->copy()->addDays(random_int(0, 4));
            $approved = random_int(1, 3) !== 1;

            $rows[] = [
                'user_id' => $user->id,
                'team_id' => $teamId,
                'type' => Arr::random(TimeOffType::cases())->value,
                'status' => $approved ? ApprovalStatus::Approved->value : ApprovalStatus::Pending->value,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'is_full_day' => true,
                'approved_by' => $approved ? $user->id : null,
                'approved_at' => $approved ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('time_off_requests')->insert($chunk);
        }
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, User>  $users
     */
    private function seedProjectMembers($projects, $users): void
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($projects as $project) {
            $projectMembers = $users->random(min(8, $users->count()));

            foreach ($projectMembers as $index => $user) {
                $rows[] = [
                    'project_id' => $project->id,
                    'user_id' => $user->id,
                    'role' => $index === 0 ? ProjectMemberRole::Manager->value : ProjectMemberRole::Developer->value,
                    'status' => ProjectMemberStatus::Active->value,
                    'allocation_percentage' => 100,
                    'joined_on' => $now->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('project_members')->insert($chunk);
        }
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, int>  $taskTypeIds
     * @return Collection<int, int> task IDs grouped implicitly by insertion order per project
     */
    private function seedTasks($projects, $taskTypeIds)
    {
        $rows = [];
        $now = Carbon::now();
        $statuses = TaskStatus::cases();

        foreach ($projects as $project) {
            for ($i = 0; $i < self::TASKS_PER_PROJECT; $i++) {
                $status = Arr::random($statuses);

                $rows[] = [
                    'project_id' => $project->id,
                    'task_type_id' => $taskTypeIds->random(),
                    'number' => $i + 1,
                    'title' => fake()->sentence(4),
                    'status' => $status->value,
                    'priority' => Arr::random(Priority::cases())->value,
                    'position' => $i,
                    'estimated_hours' => random_int(1, 40),
                    'logged_hours' => 0,
                    // A third with no due date, a third overdue, a third
                    // upcoming — real spread for the `(status, due_at)`
                    // cross-project overdue sweep to filter against.
                    'due_at' => random_int(0, 2) === 0 ? null : Carbon::today()->addDays(random_int(-30, 30)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('tasks')->insert($chunk);
        }

        return DB::table('tasks')->whereIn('project_id', $projects->pluck('id'))->pluck('id');
    }

    /**
     * One assignee per task — enough for the `(user_id, status)` "my open
     * work across every project" index to have real rows to filter.
     *
     * @param  Collection<int, int>  $taskIds
     * @param  Collection<int, User>  $users
     */
    private function seedTaskAssignments($taskIds, $users): void
    {
        $rows = [];
        $now = Carbon::now();
        $statuses = TaskAssignmentStatus::cases();

        foreach ($taskIds as $taskId) {
            $rows[] = [
                'task_id' => $taskId,
                'user_id' => $users->random()->id,
                'role' => 'assignee',
                'status' => Arr::random($statuses)->value,
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('task_assignments')->insert($chunk);
        }
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, User>  $users
     * @param  Collection<int, int>  $taskIds
     */
    private function seedResourceAllocations($projects, $users, $taskIds): void
    {
        $rows = [];
        $now = Carbon::now();

        foreach ($projects as $project) {
            $bookedUsers = $users->random(min(6, $users->count()));

            foreach ($bookedUsers as $user) {
                $startsOn = Carbon::today()->addDays(random_int(-14, 30));

                $rows[] = [
                    'user_id' => $user->id,
                    'project_id' => $project->id,
                    'task_id' => random_int(0, 4) === 0 ? null : $taskIds->random(),
                    'status' => Arr::random([AllocationStatus::Planned, AllocationStatus::Confirmed])->value,
                    'starts_on' => $startsOn->toDateString(),
                    'ends_on' => $startsOn->copy()->addDays(random_int(1, 14))->toDateString(),
                    'hours_per_day' => Arr::random([2, 4, 6, 8]),
                    'allocation_percentage' => 100,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('resource_allocations')->insert($chunk);
        }
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, int>  $taskIds
     */
    private function seedTimeLogs($users, $projects, $taskIds, int $teamId): void
    {
        $rows = [];
        $now = Carbon::now();
        $activityTypes = TimeLogActivityType::cases();

        foreach ($users->random(min(80, $users->count())) as $user) {
            $entriesForUser = random_int(20, 60);

            for ($i = 0; $i < $entriesForUser; $i++) {
                $loggedOn = Carbon::today()->subDays(random_int(0, 60));
                $startedAt = $loggedOn->copy()->setTime(random_int(8, 15), Arr::random([0, 15, 30, 45]));
                $durationMinutes = Arr::random([30, 60, 90, 120, 180]);

                $rows[] = [
                    'team_id' => $teamId,
                    'user_id' => $user->id,
                    'project_id' => $projects->random()->id,
                    'task_id' => random_int(0, 3) === 0 ? null : $taskIds->random(),
                    'activity_type' => Arr::random($activityTypes)->value,
                    'source' => TimeLogSource::Manual->value,
                    'started_at' => $startedAt,
                    'ended_at' => $startedAt->copy()->addMinutes($durationMinutes),
                    'duration_minutes' => $durationMinutes,
                    'logged_on' => $loggedOn->toDateString(),
                    'is_billable' => true,
                    'approval_status' => ApprovalStatus::Pending->value,
                    'created_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('time_logs')->insert($chunk);
        }
    }
}
