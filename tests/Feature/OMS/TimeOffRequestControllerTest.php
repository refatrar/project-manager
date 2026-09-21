<?php

namespace Tests\Feature\OMS;

use App\Enums\ApprovalStatus;
use App\Enums\TeamRole;
use App\Models\OMS\TimeOffRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeOffRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_file_a_full_day_request(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->route($user, 'time-off-requests.store'), [
                'type' => 'vacation',
                'starts_on' => '2026-04-01',
                'ends_on' => '2026-04-03',
                'is_full_day' => true,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('time_off_requests', [
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
            'status' => ApprovalStatus::Pending->value,
            'total_hours' => null,
        ]);
    }

    public function test_a_partial_day_request_computes_total_hours(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->route($user, 'time-off-requests.store'), [
                'type' => 'personal',
                'starts_on' => '2026-04-01',
                'ends_on' => '2026-04-01',
                'is_full_day' => false,
                'start_time' => '09:00',
                'end_time' => '13:00',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('time_off_requests', [
            'user_id' => $user->id,
            'total_hours' => 4,
        ]);
    }

    public function test_a_partial_day_request_without_times_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->postJson($this->route($user, 'time-off-requests.store'), [
                'type' => 'personal',
                'starts_on' => '2026-04-01',
                'ends_on' => '2026-04-01',
                'is_full_day' => false,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['start_time', 'end_time']);
    }

    public function test_a_user_can_edit_their_own_pending_request(): void
    {
        $user = User::factory()->create();
        $timeOffRequest = TimeOffRequest::factory()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->route($user, 'time-off-requests.update', $timeOffRequest), [
                'type' => 'sick',
                'starts_on' => $timeOffRequest->starts_on->toDateString(),
                'ends_on' => $timeOffRequest->ends_on->toDateString(),
                'is_full_day' => true,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('time_off_requests', ['id' => $timeOffRequest->id, 'type' => 'sick']);
    }

    public function test_a_user_cannot_edit_a_decided_request(): void
    {
        $user = User::factory()->create();
        $timeOffRequest = TimeOffRequest::factory()->approved()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->putJson($this->route($user, 'time-off-requests.update', $timeOffRequest), [
                'type' => 'sick',
                'starts_on' => $timeOffRequest->starts_on->toDateString(),
                'ends_on' => $timeOffRequest->ends_on->toDateString(),
                'is_full_day' => true,
            ]);

        $response->assertForbidden();
    }

    public function test_a_user_can_cancel_their_own_pending_request(): void
    {
        $user = User::factory()->create();
        $timeOffRequest = TimeOffRequest::factory()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->route($user, 'time-off-requests.cancel', $timeOffRequest));

        $response->assertOk();
        $this->assertDatabaseHas('time_off_requests', ['id' => $timeOffRequest->id, 'status' => ApprovalStatus::Cancelled->value]);
    }

    public function test_a_plain_member_cannot_cancel_someone_elses_request(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        $timeOffRequest = TimeOffRequest::factory()->create([
            'user_id' => $owner->id,
            'team_id' => $owner->currentTeam->id,
        ]);

        $response = $this
            ->actingAs($member)
            ->patchJson($this->route($member, 'time-off-requests.cancel', $timeOffRequest, $owner->currentTeam));

        $response->assertForbidden();
    }

    public function test_a_team_admin_can_approve_a_pending_request(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        $timeOffRequest = TimeOffRequest::factory()->create([
            'user_id' => $member->id,
            'team_id' => $owner->currentTeam->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->patchJson($this->route($owner, 'time-off-requests.decide', $timeOffRequest), [
                'decision' => 'approved',
                'decision_note' => 'Enjoy!',
            ]);

        $response->assertOk();
        $timeOffRequest->refresh();
        $this->assertSame(ApprovalStatus::Approved, $timeOffRequest->status);
        $this->assertSame($owner->id, $timeOffRequest->approved_by);
        $this->assertNotNull($timeOffRequest->approved_at);
        $this->assertSame('Enjoy!', $timeOffRequest->decision_note);
    }

    public function test_a_plain_member_cannot_approve_a_request(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        $timeOffRequest = TimeOffRequest::factory()->create([
            'user_id' => $owner->id,
            'team_id' => $owner->currentTeam->id,
        ]);

        $response = $this
            ->actingAs($member)
            ->patchJson($this->route($member, 'time-off-requests.decide', $timeOffRequest, $owner->currentTeam), [
                'decision' => 'approved',
            ]);

        $response->assertForbidden();
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        $user = User::factory()->create();
        $timeOffRequest = TimeOffRequest::factory()->approved()->create([
            'user_id' => $user->id,
            'team_id' => $user->currentTeam->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson($this->route($user, 'time-off-requests.decide', $timeOffRequest), [
                'decision' => 'rejected',
            ]);

        $response->assertForbidden();
    }

    public function test_the_index_page_only_shows_the_approval_queue_to_admins(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $owner->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);
        TimeOffRequest::factory()->create([
            'user_id' => $owner->id,
            'team_id' => $owner->currentTeam->id,
        ]);

        $response = $this
            ->actingAs($member)
            ->get(route('time-off-requests.index', ['current_team' => $owner->currentTeam->slug]));

        $response->assertInertia(fn ($page) => $page
            ->component('time-off/index')
            ->where('isApprover', false)
            ->has('pendingApprovals', 0));
    }

    public function test_the_index_page_shows_the_approval_queue_to_an_admin(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $owner->currentTeam->members()->attach($admin, ['role' => TeamRole::Admin->value]);
        TimeOffRequest::factory()->create([
            'user_id' => $owner->id,
            'team_id' => $owner->currentTeam->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('time-off-requests.index', ['current_team' => $owner->currentTeam->slug]));

        $response->assertInertia(fn ($page) => $page
            ->component('time-off/index')
            ->where('isApprover', true)
            ->has('pendingApprovals', 1));
    }

    private function route(User $user, string $name, ?TimeOffRequest $timeOffRequest = null, ?Team $team = null): string
    {
        return route($name, array_filter([
            'current_team' => ($team ?? $user->currentTeam)->slug,
            'time_off_request' => $timeOffRequest,
        ]));
    }
}
