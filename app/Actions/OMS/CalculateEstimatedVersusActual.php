<?php

namespace App\Actions\OMS;

use App\Enums\ApprovalStatus;
use App\Models\OMS\Project;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\TimeLog;
use App\Models\User;
use Illuminate\Support\Collection;

class CalculateEstimatedVersusActual
{
    /**
     * Per project member: booked hours (`resource_allocations`) versus real
     * hours worked (`time_logs`), both scoped to this project. "Estimated"
     * is a booking's `hours_per_day` across every calendar day of its own
     * range — the same literal, no-working-day-filter convention
     * `CalculateUserAvailability` already uses for "occupied" — summed
     * across every booking that member holds here. "Actual" excludes
     * rejected/cancelled entries, the same exclusion
     * `ReconcileTaskLoggedHours` applies to `tasks.logged_hours`.
     *
     * @return Collection<int, array{user: array{id: int, name: string}, estimated_hours: float, actual_hours: float, variance_hours: float}>
     */
    public function handle(Project $project): Collection
    {
        $estimated = ResourceAllocation::query()
            ->where('project_id', $project->id)
            ->get(['user_id', 'starts_on', 'ends_on', 'hours_per_day'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows): float => (float) $rows->sum(
                fn (ResourceAllocation $allocation): float => (float) $allocation->hours_per_day
                    * ($allocation->starts_on->diffInDays($allocation->ends_on) + 1),
            ));

        $actual = TimeLog::query()
            ->where('project_id', $project->id)
            ->whereNotIn('approval_status', [ApprovalStatus::Rejected->value, ApprovalStatus::Cancelled->value])
            ->selectRaw('user_id, SUM(duration_minutes) as minutes')
            ->groupBy('user_id')
            ->pluck('minutes', 'user_id')
            ->map(fn (mixed $minutes): float => round(((int) $minutes) / 60, 2));

        $userIds = $estimated->keys()->concat($actual->keys())->unique()->values();
        $users = User::query()->whereIn('id', $userIds)->get(['id', 'name'])->keyBy('id');

        return $userIds
            ->map(function (int $userId) use ($estimated, $actual, $users): array {
                $estimatedHours = $estimated->get($userId, 0.0);
                $actualHours = $actual->get($userId, 0.0);

                return [
                    'user' => ['id' => $userId, 'name' => $users->get($userId)->name],
                    'estimated_hours' => round($estimatedHours, 2),
                    'actual_hours' => round($actualHours, 2),
                    'variance_hours' => round($actualHours - $estimatedHours, 2),
                ];
            })
            ->sortByDesc('estimated_hours')
            ->values();
    }
}
