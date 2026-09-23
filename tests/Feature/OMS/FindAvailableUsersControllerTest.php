<?php

namespace Tests\Feature\OMS;

use App\Enums\TeamRole;
use App\Models\OMS\WorkSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindAvailableUsersControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_team_admin_can_search_for_available_people(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        WorkSchedule::factory()->create([
            'day_of_week' => 1,
            'capacity_hours' => 8,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('availability.index', [
                'current_team' => $owner->currentTeam->slug,
                'from' => '2026-03-09',
                'to' => '2026-03-09',
                'hours_per_day' => 4,
            ]));

        $response->assertOk();
        $response->assertInertia(function ($page) use ($member) {
            $page->component('availability/index');
            $ids = collect($page->toArray()['props']['results'])->pluck('id');
            // The schedule is global (TASKS.md 7.10), so every team member
            // now has capacity — the owner qualifies too, not just $member.
            $this->assertTrue($ids->contains($member->id));
        });
    }

    public function test_a_plain_member_cannot_search_for_available_people(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

        $response = $this
            ->actingAs($member)
            ->get(route('availability.index', ['current_team' => $owner->currentTeam->slug]));

        $response->assertForbidden();
    }

    public function test_no_search_yet_returns_null_results(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('availability.index', ['current_team' => $owner->currentTeam->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('availability/index')
            ->where('results', null));
    }
}
