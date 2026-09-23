<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\CalculateCycleTimeReport;
use App\Actions\OMS\CalculateEstimatedVersusActual;
use App\Actions\OMS\CalculateProjectMemberCapacity;
use App\Actions\OMS\CalculateProjectProgress;
use App\Actions\OMS\CalculateUserAvailability;
use App\Actions\OMS\CreateProject;
use App\Actions\OMS\DetectOverAllocatedBookings;
use App\Actions\OMS\RecordActivity;
use App\Enums\AllocationStatus;
use App\Enums\MilestoneStatus;
use App\Enums\Priority;
use App\Enums\ProjectHealth;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectModuleStatus;
use App\Enums\ProjectStatus;
use App\Enums\SprintStatus;
use App\Enums\TaskAssignmentRole;
use App\Enums\TaskStatus;
use App\Enums\TaskTypeStatus;
use App\Enums\TeamModulePermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\OMS\SaveProjectRequest;
use App\Models\OMS\Activity;
use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\ProjectProgressSnapshot;
use App\Models\OMS\ResourceAllocation;
use App\Models\OMS\Sprint;
use App\Models\OMS\Task;
use App\Models\Setup\Label;
use App\Models\Setup\TaskType;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of the projects visible to the user on this team.
     */
    public function index(Request $request, Team $current_team): Response
    {
        Gate::authorize('viewAny', [Project::class, $current_team]);

        $user = $request->user('web');

        $projects = Project::query()
            ->where('team_id', $current_team->id)
            ->when(
                ! $this->hasWideVisibility($request, $current_team),
                fn ($query) => $query->forMember($user),
            )
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->string('priority')->isNotEmpty(), fn ($query) => $query->where('priority', $request->string('priority')->value()))
            ->when($request->string('health')->isNotEmpty(), fn ($query) => $query->where('health', $request->string('health')->value()))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Project $project): array => $project->toListArray());

        return Inertia::render('projects/index', [
            'projects' => $projects,
            'filters' => $request->only(['status', 'priority', 'health']),
            'statusOptions' => ProjectStatus::options(),
            'priorityOptions' => Priority::options(),
            'healthOptions' => ProjectHealth::options(),
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(SaveProjectRequest $request, Team $current_team, CreateProject $createProject): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', [Project::class, $current_team]);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $project = $createProject->handle($current_team, $user, $request->safe()->only([
            'code', 'name', 'description', 'status', 'priority', 'health', 'color',
            'client_name', 'start_date', 'end_date', 'estimated_hours', 'budget', 'currency',
        ]));

        return $this->savedResponse($request, $current_team, $project, __('Project created.'), 201);
    }

    /**
     * Display the project workspace.
     */
    public function show(
        Team $current_team,
        Project $project,
        CalculateProjectProgress $calculateProgress,
        CalculateCycleTimeReport $calculateCycleTime,
        DetectOverAllocatedBookings $detectOverAllocatedBookings,
        CalculateUserAvailability $calculateUserAvailability,
        CalculateEstimatedVersusActual $calculateEstimatedVersusActual,
        CalculateProjectMemberCapacity $calculateProjectMemberCapacity,
    ): Response {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('view', $project);

        $project->load('owner:id,name');

        $modules = $project->modules()
            ->orderBy('position')
            ->get()
            ->map(fn (ProjectModule $module): array => $module->toListArray());

        $memberModels = $project->members()
            ->with('user:id,name,email')
            ->get();
        $members = $memberModels->map(fn (ProjectMember $member): array => $member->toListArray());

        // FR-8.8: a member's schedule and project-scoped availability is
        // visible only to whoever can manage this project (the same
        // threshold `Gate::authorize('update', $project)` already applies
        // to booking hours) — never to every project viewer, and never
        // touching another project's bookings even for someone who can.
        $memberCapacity = Gate::allows('update', $project)
            ? $calculateProjectMemberCapacity->handle(
                $project,
                $project->members()->active()->with('user:id,name,email')->get(),
                $calculateUserAvailability,
            )
            : collect();

        $memberUserIds = $project->members()->active()->pluck('user_id');

        $availableUsers = $current_team->members()
            ->whereNotIn('users.id', $memberUserIds)
            ->get(['users.id', 'users.name', 'users.email']);

        // Not scoped to open(): the board needs its Done column populated too.
        $tasks = $project->tasks()
            ->with(['taskType:id,name', 'assignees:id,name', 'assignments.user:id,name', 'labels:id,name,color'])
            ->orderBy('position')
            ->get()
            ->map(fn (Task $task): array => $task->toBoardArray());

        $taskTypes = TaskType::query()
            ->where('status', TaskTypeStatus::Active)
            ->orderBy('name')
            ->get(['id', 'name']);

        $milestones = $project->milestones()
            ->orderBy('position')
            ->get()
            ->map(fn (Milestone $milestone): array => $milestone->toListArray());

        $sprints = $project->sprints()
            ->orderBy('starts_on')
            ->get()
            ->map(fn (Sprint $sprint): array => $sprint->toListArray());

        $allocations = $project->resourceAllocations()
            ->with(['user:id,name', 'task:id,number,title'])
            ->orderBy('starts_on')
            ->get();
        $overAllocatedFlags = $detectOverAllocatedBookings->handle($allocations, $calculateUserAvailability);
        $resourceAllocations = $allocations->map(fn (ResourceAllocation $allocation): array => [
            ...$allocation->toListArray(),
            'over_allocated' => $overAllocatedFlags[$allocation->id] ?? false,
        ]);

        $labels = Label::query()
            ->where('team_id', $current_team->id)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $progress = $calculateProgress->handle($project);
        $cycleTime = $calculateCycleTime->handle($project);

        // Not paginated: acceptable at a single project's scale, same
        // reasoning as the task list view (see TASKS.md 1.5). Revisit with
        // NFR-2 if a project's activity volume grows large.
        $activities = $project->activities()
            ->with('user:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Activity $activity): array => $activity->toListArray());

        $burndown = $project->progressSnapshots()
            ->whereNull('sprint_id')
            ->where('snapshot_on', '>=', Carbon::now()->subDays(30)->toDateString())
            ->orderBy('snapshot_on')
            ->get(['snapshot_on', 'total_tasks', 'completed_tasks'])
            ->map(fn (ProjectProgressSnapshot $snapshot): array => [
                'date' => $snapshot->snapshot_on->toDateString(),
                'total' => $snapshot->total_tasks,
                'completed' => $snapshot->completed_tasks,
                'open' => $snapshot->total_tasks - $snapshot->completed_tasks,
            ]);

        return Inertia::render('projects/show', [
            'project' => $project->toDetailArray(),
            'modules' => $modules,
            'members' => $members,
            'memberCapacity' => $memberCapacity,
            'availableUsers' => $availableUsers,
            'tasks' => $tasks,
            'taskTypes' => $taskTypes,
            'milestones' => $milestones,
            'sprints' => $sprints,
            'resourceAllocations' => $resourceAllocations,
            'allocationStatusOptions' => AllocationStatus::options(),
            'estimatedVsActual' => $calculateEstimatedVersusActual->handle($project),
            'labels' => $labels,
            'progress' => [
                'totalTasks' => $progress->totalTasks,
                'completedTasks' => $progress->completedTasks,
                'inProgressTasks' => $progress->inProgressTasks,
                'blockedTasks' => $progress->blockedTasks,
                'overdueTasks' => $progress->overdueTasks,
                'progressPercentage' => $progress->progressPercentage,
            ],
            'burndown' => $burndown,
            'activities' => $activities,
            'cycleTime' => [
                'avgLeadTimeMinutes' => $cycleTime->avgLeadTimeMinutes,
                'avgCycleTimeMinutes' => $cycleTime->avgCycleTimeMinutes,
                'completedTaskCount' => $cycleTime->completedTaskCount,
                'statusBreakdown' => $cycleTime->statusBreakdown,
            ],
            'milestoneStatusOptions' => MilestoneStatus::options(),
            'sprintStatusOptions' => SprintStatus::options(),
            'taskStatusOptions' => TaskStatus::options(),
            'taskAssignmentRoleOptions' => TaskAssignmentRole::options(),
            'statusOptions' => ProjectStatus::options(),
            'priorityOptions' => Priority::options(),
            'healthOptions' => ProjectHealth::options(),
            'moduleStatusOptions' => ProjectModuleStatus::options(),
            'memberRoleOptions' => ProjectMemberRole::options(),
        ]);
    }

    /**
     * Update the specified project.
     */
    public function update(SaveProjectRequest $request, Team $current_team, Project $project): JsonResponse|RedirectResponse
    {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('update', $project);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $project->fill($request->safe()->only([
            'code', 'name', 'description', 'status', 'priority', 'health', 'color',
            'client_name', 'start_date', 'end_date', 'estimated_hours', 'budget', 'currency',
        ]));
        $project->updated_by = $user->id;
        $project->save();

        return $this->savedResponse($request, $current_team, $project, __('Project updated.'));
    }

    /**
     * Archive the specified project, keeping its history intact.
     */
    public function archive(Request $request, Team $current_team, Project $project, RecordActivity $recordActivity): JsonResponse|RedirectResponse
    {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('archive', $project);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $project->archived_at = Carbon::now();
        $project->status = ProjectStatus::Archived;
        $project->updated_by = $user->id;
        $project->save();

        $recordActivity->handle(
            team: $current_team,
            project: $project,
            userId: $user->id,
            event: 'project.archived',
            description: "Project \"{$project->name}\" was archived",
        );

        return $this->savedResponse($request, $current_team, $project, __('Project archived.'));
    }

    /**
     * Soft delete the specified project.
     */
    public function destroy(Request $request, Team $current_team, Project $project): JsonResponse|RedirectResponse
    {
        $this->authorizeProjectOnTeam($current_team, $project);
        Gate::authorize('delete', $project);

        $user = $request->user('web');
        abort_unless($user !== null, 403);

        $project->deleted_by = $user->id;
        $project->save();
        $project->delete();

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

            return to_route('projects.index', ['current_team' => $current_team->slug]);
        }

        return response()->json([
            'message' => __('Project deleted.'),
        ]);
    }

    /**
     * A project scoped by the URL's current team, or a 404, so a task
     * cannot be reached through another team's URL (NFR-1).
     */
    private function authorizeProjectOnTeam(Team $current_team, Project $project): void
    {
        abort_unless($project->team_id === $current_team->id, 404);
    }

    /**
     * Determine whether the user's team role grants visibility into every
     * project on the team, independent of project membership (ADR-011).
     */
    private function hasWideVisibility(Request $request, Team $team): bool
    {
        $user = $request->user('web');

        return $user !== null && $user->teamCan($team, TeamModulePermission::ViewAllProjects);
    }

    /**
     * Return a JSON payload for modal forms, or an Inertia redirect for page visits.
     */
    private function savedResponse(Request $request, Team $team, Project $project, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('projects.show', ['current_team' => $team->slug, 'project' => $project]);
        }

        return response()->json([
            'project' => $project->toDetailArray(),
            'message' => $message,
        ], $status);
    }
}
