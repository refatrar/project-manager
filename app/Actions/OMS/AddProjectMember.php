<?php

namespace App\Actions\OMS;

use App\Enums\ProjectMemberStatus;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;

class AddProjectMember
{
    /**
     * Add a user to a project, restoring a previously removed membership
     * rather than inserting a duplicate row (DESIGN.md 3.4: the unique key
     * on `project_members` deliberately excludes `deleted_at`, so a second
     * insert for the same user would violate it).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Project $project, int $userId, array $attributes, int $addedBy): ProjectMember
    {
        $existing = ProjectMember::withTrashed()
            ->where('project_id', $project->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing !== null) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->fill($attributes);
            $existing->status = ProjectMemberStatus::Active;
            $existing->left_on = null;
            $existing->updated_by = $addedBy;
            $existing->save();

            return $existing;
        }

        $member = new ProjectMember($attributes);
        $member->project_id = $project->id;
        $member->user_id = $userId;
        $member->created_by = $addedBy;
        $member->save();

        return $member;
    }
}
