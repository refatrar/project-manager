<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\SubmitTimesheet;
use App\Enums\ApprovalStatus;
use App\Models\OMS\TimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubmitTimesheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_submits_pending_finished_entries_in_the_week(): void
    {
        $user = User::factory()->create();
        $timeLog = TimeLog::factory()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'logged_on' => '2026-03-10',
            'approval_status' => ApprovalStatus::Pending,
        ]);

        $count = app(SubmitTimesheet::class)->handle(
            $user,
            $user->currentTeam,
            Carbon::parse('2026-03-09'),
            Carbon::parse('2026-03-15'),
        );

        $this->assertSame(1, $count);
        $this->assertSame(ApprovalStatus::Submitted, $timeLog->fresh()->approval_status);
    }

    public function test_it_does_not_submit_a_running_timer(): void
    {
        $user = User::factory()->create();
        $timeLog = TimeLog::factory()->running()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'logged_on' => Carbon::today(),
        ]);

        app(SubmitTimesheet::class)->handle(
            $user,
            $user->currentTeam,
            Carbon::today()->startOfWeek(),
            Carbon::today()->endOfWeek(),
        );

        $this->assertSame(ApprovalStatus::Pending, $timeLog->fresh()->approval_status);
    }

    public function test_it_does_not_resubmit_an_already_decided_entry(): void
    {
        $user = User::factory()->create();
        $timeLog = TimeLog::factory()->approved()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'logged_on' => '2026-03-10',
        ]);

        app(SubmitTimesheet::class)->handle(
            $user,
            $user->currentTeam,
            Carbon::parse('2026-03-09'),
            Carbon::parse('2026-03-15'),
        );

        $this->assertSame(ApprovalStatus::Approved, $timeLog->fresh()->approval_status);
    }

    public function test_it_does_not_submit_entries_outside_the_week(): void
    {
        $user = User::factory()->create();
        $timeLog = TimeLog::factory()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'logged_on' => '2026-04-01',
            'approval_status' => ApprovalStatus::Pending,
        ]);

        app(SubmitTimesheet::class)->handle(
            $user,
            $user->currentTeam,
            Carbon::parse('2026-03-09'),
            Carbon::parse('2026-03-15'),
        );

        $this->assertSame(ApprovalStatus::Pending, $timeLog->fresh()->approval_status);
    }
}
