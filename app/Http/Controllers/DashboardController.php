<?php

namespace App\Http\Controllers;

use App\Actions\Teams\FindPendingInvitations;
use App\Enums\ProjectHealth;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\OMS\Task;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Team $current_team, FindPendingInvitations $findPendingInvitations): Response
    {
        return Inertia::render('dashboard', [
            'pendingInvitations' => $findPendingInvitations->handle($request->user('web')),
            ...$this->portfolio($request, $current_team),
        ]);
    }

    /**
     * Build the portfolio summary: every project visible to the user on
     * this team, plus team-wide overdue and blocked counts. Visibility
     * mirrors ProjectPolicy — team Owner/Admin see everything, everyone
     * else only the projects they're an active member of.
     *
     * @return array{projects: array<int, array<string, mixed>>, healthCounts: array<string, int>, overdueTasks: int, blockedTasks: int}
     */
    private function portfolio(Request $request, Team $current_team): array
    {
        $user = $request->user('web');
        $role = $user?->teamRole($current_team);
        $hasWideVisibility = $role !== null && $role->isAtLeast(TeamRole::Admin);

        $projects = Project::query()
            ->where('team_id', $current_team->id)
            ->open()
            ->when(! $hasWideVisibility, fn ($query) => $query->forMember($user))
            ->orderBy('name')
            ->get();

        $projectIds = $projects->pluck('id');

        $overdueTasks = Task::query()
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('due_at')
            ->where('due_at', '<', Carbon::now())
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Cancelled->value])
            ->count();

        $blockedTasks = Task::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', TaskStatus::Blocked->value)
            ->count();

        $healthCounts = collect(ProjectHealth::cases())
            ->mapWithKeys(fn (ProjectHealth $health) => [
                $health->value => $projects->where('health', $health)->count(),
            ])
            ->all();

        return [
            'projects' => $projects->map(fn (Project $project): array => $project->toListArray())->values()->all(),
            'healthCounts' => $healthCounts,
            'overdueTasks' => $overdueTasks,
            'blockedTasks' => $blockedTasks,
        ];
    }
}
