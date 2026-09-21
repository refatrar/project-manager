<?php

namespace App\Actions\OMS;

use App\Data\ProjectProgressData;
use App\Enums\ProjectHealth;
use App\Models\OMS\Project;
use Illuminate\Support\Carbon;

class DetermineProjectHealth
{
    /**
     * How many percentage points a project may fall behind its expected
     * schedule position before being marked at risk / off track.
     */
    private const AT_RISK_GAP = 15;

    private const OFF_TRACK_GAP = 40;

    /**
     * How far logged hours may exceed the estimate, as a ratio, before
     * being treated as an hours-variance signal (1.2 = 20% over budget).
     */
    private const HOURS_AT_RISK_RATIO = 1.2;

    /**
     * Determine the project's health from its schedule position and hours
     * variance. The worse of the two signals wins.
     */
    public function handle(Project $project, ProjectProgressData $progress): ProjectHealth
    {
        return $this->fromSchedule($project, $progress)
            ->worse($this->fromHoursVariance($progress));
    }

    /**
     * Compare actual progress against where the project should be given
     * how much of its planned schedule has elapsed.
     */
    private function fromSchedule(Project $project, ProjectProgressData $progress): ProjectHealth
    {
        if ($project->end_date === null) {
            return ProjectHealth::OnTrack;
        }

        $now = Carbon::now();

        if ($now->greaterThan($project->end_date) && $progress->progressPercentage < 100) {
            return ProjectHealth::OffTrack;
        }

        if ($project->start_date === null || ! $project->end_date->greaterThan($project->start_date)) {
            return ProjectHealth::OnTrack;
        }

        $totalDays = $project->start_date->diffInDays($project->end_date);
        $elapsedDays = min(max($project->start_date->diffInDays($now, false), 0), $totalDays);
        $expectedProgress = $totalDays > 0 ? ($elapsedDays / $totalDays) * 100 : 100;
        $gap = $expectedProgress - $progress->progressPercentage;

        return match (true) {
            $gap > self::OFF_TRACK_GAP => ProjectHealth::OffTrack,
            $gap > self::AT_RISK_GAP => ProjectHealth::AtRisk,
            default => ProjectHealth::OnTrack,
        };
    }

    /**
     * Flag a project whose logged hours have outpaced its estimate while
     * work remains unfinished.
     */
    private function fromHoursVariance(ProjectProgressData $progress): ProjectHealth
    {
        $estimated = (float) $progress->estimatedHours;

        if ($estimated <= 0 || $progress->progressPercentage >= 100) {
            return ProjectHealth::OnTrack;
        }

        $ratio = (float) $progress->loggedHours / $estimated;

        return $ratio > self::HOURS_AT_RISK_RATIO ? ProjectHealth::AtRisk : ProjectHealth::OnTrack;
    }
}
