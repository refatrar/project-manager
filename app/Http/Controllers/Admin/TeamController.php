<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Teams\AssignTeamLeader;
use App\Actions\Teams\CreateTeam;
use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\Admin;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Team creation and team-leader assignment (Phase 7): the one place teams
 * are created now — self-service creation is gone from Settings → Teams.
 */
class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManageTeams($request);

        $teams = Team::query()
            ->withCount('members')
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'members_count' => $team->members_count,
                'leader' => $team->owner()?->only(['id', 'name', 'email']),
            ]);

        return Inertia::render('admin/teams/index', [
            'teams' => $teams,
        ]);
    }

    public function store(SaveTeamRequest $request, CreateTeam $createTeam): JsonResponse|RedirectResponse
    {
        $this->authorizeManageTeams($request);

        $createTeam->handle(null, $request->validated('name'));

        return $this->respond($request, __('Team created. Assign a leader below to make it usable.'));
    }

    public function assignLeader(Request $request, Team $team, AssignTeamLeader $assignTeamLeader): JsonResponse|RedirectResponse
    {
        $this->authorizeManageTeams($request);

        $email = $request->validate(['email' => ['required', 'email']])['email'];

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => __('No registered user has that email address.'),
            ]);
        }

        $assignTeamLeader->handle($team, $user);

        return $this->respond($request, __(':name is now the leader of ":team".', ['name' => $user->name, 'team' => $team->name]));
    }

    /**
     * The admin/teams UI submits through `useHttp`, a plain JSON fetch —
     * not an Inertia visit. It follows a redirect itself and then tries to
     * parse the resulting HTML page as JSON, which throws before its
     * `onSuccess` ever runs. So a redirect only works for a real Inertia
     * visit (`X-Inertia` header present, e.g. a no-JS fallback); everything
     * else gets a plain JSON body instead, matching
     * `WorkScheduleController::store()`'s established convention.
     */
    private function respond(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('admin.teams.index');
        }

        return response()->json(['message' => $message]);
    }

    private function authorizeManageTeams(Request $request): void
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        abort_unless($admin->hasPermission(AdminPermission::ManageTeams->value), 403);
    }
}
