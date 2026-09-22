<?php

namespace Tests\Feature\OMS;

use App\Enums\TeamRole;
use App\Models\OMS\UserWorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeamCapacityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_admin_can_view_the_capacity_heatmap(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Jordan Lee']);
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        UserWorkSchedule::factory()->create([
            'user_id' => $member->id,
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('team-capacity.index', [
                'current_team' => $owner->currentTeam->slug,
                'from' => '2026-03-09',
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('team-capacity/index')
            ->where('from', '2026-03-09')
            ->where('to', '2026-03-22')
            ->has('members', 2)
            ->has('members.0.days', 14));
    }

    public function test_a_plain_member_cannot_view_the_capacity_heatmap(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($member)
            ->get(route('team-capacity.index', ['current_team' => $owner->currentTeam->slug]));

        $response->assertForbidden();
    }

    public function test_the_window_defaults_to_the_current_weeks_monday(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('team-capacity.index', ['current_team' => $owner->currentTeam->slug]));

        $expectedFrom = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $expectedTo = Carbon::parse($expectedFrom)->addDays(13)->toDateString();

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('team-capacity/index')
            ->where('from', $expectedFrom)
            ->where('to', $expectedTo)
            ->etc());
    }

    public function test_a_non_working_day_reports_zero_capacity(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        UserWorkSchedule::factory()->nonWorkingDay()->create([
            'user_id' => $member->id,
            'day_of_week' => 6,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('team-capacity.index', [
                'current_team' => $owner->currentTeam->slug,
                'from' => '2026-03-09',
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('team-capacity/index')
            // 2026-03-09 is a Monday, so day_of_week 6 (Saturday) is index 5.
            ->where('members.1.days.5.date', '2026-03-14')
            ->where('members.1.days.5.capacity_hours', 0));
    }
}
