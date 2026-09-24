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
use App\Models\OMS\Holiday;
use App\Models\OMS\Meeting;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\Sprint;
use App\Models\OMS\Task;
use App\Models\OMS\TaskAssignment;
use App\Models\OMS\TimeLog;
use App\Models\OMS\WorkSchedule;
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
            ['email' => 'rafiq@kaz-software.com'],
            ['name' => 'Rafiqul Islam', 'password' => bcrypt('password')],
        );

        $team = $this->team($owner);
        $members = $this->members($team);
        $this->workSchedule();
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
        $team = Team::factory()->create(['name' => 'PHP 360']);
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
            ['name' => 'Hasib Bin Siddique', 'email' => 'hasib@kaz-software.com', 'role' => TeamRole::Member],
            ['name' => 'Mohammad Rana', 'email' => 'rana@kaz-software.com', 'role' => TeamRole::Member],
            ['name' => 'Fardin Ahsan', 'email' => 'fardin@kaz-software.com', 'role' => TeamRole::Member],
            ['name' => 'Md Al-amin', 'email' => 'alamin@kaz-software.com', 'role' => TeamRole::Member],
            ['name' => 'Muhammad Mahedi Hasan', 'email' => 'mahedi@kaz-software.com', 'role' => TeamRole::Member],
            ['name' => 'Fazle Rabbi', 'email' => 'rabbi@kaz-software.com', 'role' => TeamRole::Member],
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
    /**
     * The platform's single global schedule (TASKS.md 7.10 — replaces the
     * former per-user template) plus a couple of demo holidays, so the
     * holiday calendar and the availability-zeroing behavior have
     * something to show on a fresh `db:seed`.
     */
    private function workSchedule(): void
    {
        if (WorkSchedule::query()->exists()) {
            return;
        }

        foreach (range(1, 7) as $dayOfWeek) {
            $isWorkingDay = $dayOfWeek <= 5;

            WorkSchedule::factory()->create([
                'day_of_week' => $dayOfWeek,
                'is_working_day' => $isWorkingDay,
                'start_time' => $isWorkingDay ? '09:00:00' : null,
                'end_time' => $isWorkingDay ? '18:00:00' : null,
                'break_minutes' => $isWorkingDay ? 60 : 0,
                'capacity_hours' => $isWorkingDay ? 8 : 0,
                'effective_from' => '2026-01-01',
            ]);
        }

        Holiday::query()->firstOrCreate(['date' => '2026-01-01'], ['name' => "New Year's Day"]);
        Holiday::query()->firstOrCreate(['date' => '2026-12-25'], ['name' => 'Christmas Day']);
    }
}
