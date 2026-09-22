<?php

namespace Tests\Feature\Teams;

use App\Actions\Teams\CreateTeam;
use App\Models\Setup\Label;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_team_seeds_default_labels(): void
    {
        $user = User::factory()->create();

        $team = app(CreateTeam::class)->handle($user, 'A New Team');

        $labels = Label::query()->where('team_id', $team->id)->pluck('name');
        $this->assertTrue($labels->contains('Bug'));
        $this->assertTrue($labels->contains('Feature'));
        $this->assertGreaterThanOrEqual(6, $labels->count());
    }

    public function test_each_team_gets_its_own_default_labels(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $teamA = app(CreateTeam::class)->handle($userA, 'Team A');
        $teamB = app(CreateTeam::class)->handle($userB, 'Team B');

        $this->assertNotSame(
            Label::query()->where('team_id', $teamA->id)->where('name', 'Bug')->value('id'),
            Label::query()->where('team_id', $teamB->id)->where('name', 'Bug')->value('id'),
        );
    }

    public function test_a_team_created_with_no_owner_has_no_members_but_still_gets_default_labels(): void
    {
        $team = app(CreateTeam::class)->handle(null, 'Admin-Provisioned Team');

        $this->assertSame(0, $team->members()->count());
        $this->assertTrue(
            Label::query()->where('team_id', $team->id)->pluck('name')->contains('Bug'),
        );
    }
}
