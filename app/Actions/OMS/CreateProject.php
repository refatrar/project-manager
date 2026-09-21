<?php

namespace App\Actions\OMS;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectMemberStatus;
use App\Models\OMS\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProject
{
    /**
     * Create a project on the given team and add the creator as its owner.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $creator, array $attributes): Project
    {
        return DB::transaction(function () use ($team, $creator, $attributes): Project {
            $project = new Project($attributes);
            $project->team_id = $team->id;
            $project->slug = $this->generateUniqueSlug($team, $attributes['name']);
            $project->owner_id = $creator->id;
            $project->created_by = $creator->id;
            $project->save();

            $membership = $project->members()->make([
                'user_id' => $creator->id,
                'role' => ProjectMemberRole::Owner,
                'status' => ProjectMemberStatus::Active,
                'allocation_percentage' => 100,
                'joined_on' => now(),
            ]);
            $membership->created_by = $creator->id;
            $membership->save();

            return $project;
        });
    }

    /**
     * Generate a slug unique within the team, including soft-deleted projects
     * so an archived project's URL is never reassigned.
     *
     * @see docs/architecture-decisions.md ADR-007 style reasoning applied to slugs (DESIGN.md 3.4)
     */
    private function generateUniqueSlug(Team $team, string $name): string
    {
        $base = Str::slug($name);

        $existingSlugs = Project::withTrashed()
            ->where('team_id', $team->id)
            ->where(fn ($query) => $query
                ->where('slug', $base)
                ->orWhere('slug', 'like', $base.'-%'))
            ->pluck('slug');

        if ($existingSlugs->isEmpty()) {
            return $base;
        }

        $maxSuffix = $existingSlugs
            ->map(function (string $slug) use ($base): ?int {
                if ($slug === $base) {
                    return 0;
                }

                if (preg_match('/^'.preg_quote($base, '/').'-(\d+)$/', $slug, $matches)) {
                    return (int) $matches[1];
                }

                return null;
            })
            ->filter(fn (?int $suffix) => $suffix !== null)
            ->max() ?? 0;

        return $base.'-'.($maxSuffix + 1);
    }
}
