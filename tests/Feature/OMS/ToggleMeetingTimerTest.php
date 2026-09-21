<?php

namespace Tests\Feature\OMS;

use App\Actions\OMS\ToggleMeetingTimer;
use App\Enums\TimeLogActivityType;
use App\Enums\TimeLogSource;
use App\Models\OMS\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ToggleMeetingTimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_starting_a_timer_writes_a_running_time_log_against_the_meeting(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id]);

        $timeLog = app(ToggleMeetingTimer::class)->start($meeting, $user);

        $this->assertSame($meeting->id, $timeLog->meeting_id);
        $this->assertSame($user->id, $timeLog->user_id);
        $this->assertSame($meeting->team_id, $timeLog->team_id);
        $this->assertSame(TimeLogActivityType::Meeting, $timeLog->activity_type);
        $this->assertSame(TimeLogSource::Timer, $timeLog->source);
        $this->assertNull($timeLog->ended_at);
    }

    public function test_a_user_cannot_start_a_second_timer_while_one_is_running(): void
    {
        $user = User::factory()->create();
        $meetingA = Meeting::factory()->create(['team_id' => $user->currentTeam->id]);
        $meetingB = Meeting::factory()->create(['team_id' => $user->currentTeam->id]);

        app(ToggleMeetingTimer::class)->start($meetingA, $user);

        $this->expectException(RuntimeException::class);
        app(ToggleMeetingTimer::class)->start($meetingB, $user);
    }

    public function test_stopping_a_timer_stamps_its_duration(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id]);
        $toggle = app(ToggleMeetingTimer::class);
        $timeLog = $toggle->start($meeting, $user);
        $timeLog->started_at = now()->subMinutes(15);
        $timeLog->save();

        $stopped = $toggle->stop($meeting, $user);

        $this->assertSame($timeLog->id, $stopped->id);
        $this->assertNotNull($stopped->ended_at);
        $this->assertSame(15, $stopped->duration_minutes);
    }

    public function test_stopping_with_no_running_timer_raises(): void
    {
        $user = User::factory()->create();
        $meeting = Meeting::factory()->create(['team_id' => $user->currentTeam->id]);

        $this->expectException(RuntimeException::class);
        app(ToggleMeetingTimer::class)->stop($meeting, $user);
    }
}
