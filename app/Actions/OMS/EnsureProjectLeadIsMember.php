<?php

namespace App\Actions\OMS;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectMemberStatus;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\User;

class EnsureProjectLeadIsMember
{
    public function __construct(private AddProjectMember $addProjectMember) {}

    /**
     * Add the project's assigned lead, and the team's Team Lead, to the
     * member list when they are not already active members. An existing
     * membership keeps its role.
     */
    public function handle(Project $project, int $addedBy): void
    {
        $this->ensureMember($project, $project->project_lead_id, $addedBy);

        $teamLead = $project->team->owner();

        $this->ensureMember($project, $teamLead instanceof User ? $teamLead->id : null, $addedBy);
    }

    /**
     * Add one user as a project lead member, restoring a removed membership.
     */
    private function ensureMember(Project $project, ?int $userId, int $addedBy): void
    {
        if ($userId === null) {
            return;
        }

        $existing = ProjectMember::withTrashed()
            ->where('project_id', $project->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing !== null && ! $existing->trashed() && $existing->status === ProjectMemberStatus::Active) {
            return;
        }

        $this->addProjectMember->handle($project, $userId, [
            'role' => ProjectMemberRole::Lead,
            'allocation_percentage' => 100,
            'joined_on' => now()->toDateString(),
        ], $addedBy);
    }
}
