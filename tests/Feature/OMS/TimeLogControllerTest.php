<?php

namespace Tests\Feature\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TimeLogSource;
use App\Models\OMS\TimeLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_start_and_stop_a_timer(): void
    {
        $user = User::factory()->create();

        $startResponse = $this
            ->actingAs($user)
            ->postJson($this->route($user, 'time-logs.start'), ['activity_type' => 'development']);

        $startResponse->assertCreated();
        $timeLog = TimeLog::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(TimeLogSource::Timer, $timeLog->source);
        $this->assertNull($timeLog->ended_at);

        $stopResponse = $this
            ->actingAs($user)
            ->patchJson($this->route($user, 'time-logs.stop', $timeLog));

        $stopResponse->assertOk();
        $this->assertNotNull($timeLog->fresh()->ended_at);
    }

    public function test_a_user_cannot_start_a_second_timer_while_one_is_running(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson($this->route($user, 'time-logs.start'), ['activity_type' => 'development'])->assertCreated();

        $response = $this
            ->actingAs($user)
            ->postJson($this->route($user, 'time-logs.start'), ['activity_type' => 'research']);

        $response->assertStatus(422);
    }

    public function test_a_manual_entry_computes_duration_and_logged_on(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->route($user, 'time-logs.store'), [
                'activity_type' => 'development',
                'is_billable' => true,
                'started_at' => '2026-03-09T09:00',
                'ended_at' => '2026-03-09T11:30',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('time_logs', [
            'user_id' => $user->id,
            'duration_minutes' => 150,
            'logged_on' => '2026-03-09',
            'source' => TimeLogSource::Manual->value,
        ]);
    }

    public function test_a_user_can_edit_a_pending_entry(): void
    {
        $user = User::factory()->create();
        $timeLog = TimeLog::factory()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'started_at' => '2026-03-09 09:00:00',
            'ended_at' => '2026-03-09 10:00:00',
            'approval_status' => ApprovalStatus::Pending,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->route($user, 'time-logs.update', $timeLog), [
                'activity_type' => 'qa',
                'is_billable' => false,
                'started_at' => '2026-03-09T09:00',
                'ended_at' => '2026-03-09T12:00',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('time_logs', ['id' => $timeLog->id, 'activity_type' => 'qa', 'duration_minutes' => 180]);
    }

    public function test_a_user_cannot_edit_an_approved_entry(): void
    {
        $user = User::factory()->create();
        $timeLog = TimeLog::factory()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'approval_status' => ApprovalStatus::Approved,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->route($user, 'time-logs.update', $timeLog), [
                'activity_type' => 'qa',
                'is_billable' => false,
                'started_at' => '2026-03-09T09:00',
                'ended_at' => '2026-03-09T12:00',
            ]);

        $response->assertForbidden();
    }

    public function test_a_user_can_delete_a_pending_entry(): void
    {
        $user = User::factory()->create();
        $timeLog = TimeLog::factory()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'approval_status' => ApprovalStatus::Pending,
        ]);

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->route($user, 'time-logs.destroy', $timeLog));

        $response->assertOk();
        $this->assertSoftDeleted('time_logs', ['id' => $timeLog->id]);
    }

    public function test_a_user_cannot_stop_someone_elses_timer(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->currentTeam->members()->attach($other, ['role' => 'member']);
        $this->actingAs($owner)->postJson($this->route($owner, 'time-logs.start'), ['activity_type' => 'development'])->assertCreated();
        $timeLog = TimeLog::query()->where('user_id', $owner->id)->firstOrFail();

        $response = $this
            ->actingAs($other)
            ->patchJson($this->route($other, 'time-logs.stop', $timeLog, $owner->currentTeam));

        $response->assertForbidden();
    }

    public function test_an_entry_from_another_team_cannot_be_reached_through_the_users_own_team_url(): void
    {
        $user = User::factory()->create();
        $foreignTimeLog = TimeLog::factory()->create();

        $response = $this
            ->actingAs($user)
            ->deleteJson($this->route($user, 'time-logs.destroy', $foreignTimeLog));

        $response->assertNotFound();
    }

    private function route(User $user, string $name, ?TimeLog $timeLog = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)->slug,
            'time_log' => $timeLog,
        ]));
    }
}
