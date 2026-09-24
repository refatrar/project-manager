<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Teams\AssignTeamLeader;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamAccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registered team accounts. People who sign up appear here, and an admin
 * can create, update, or remove them and place them on a team.
 */
class UserController extends Controller
{
    public function __construct(private readonly TeamAccessControl $access) {}

    public function index(): Response
    {
        $this->access->ensureCatalogue();

        $users = User::query()
            ->with(['teams' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->paginate(20)
            ->through(fn (User $user): array => $this->present($user));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'teams' => $this->teamOptions(),
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        $user->forceFill(['email_verified_at' => Carbon::now()])->save();

        return $this->respond($request, __('User account created.'));
    }

    public function update(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (filled($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }

        $user->save();

        return $this->respond($request, __('User account updated.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User account deleted.')]);

        return to_route('admin.users.index');
    }

    public function assign(Request $request, User $user, AssignTeamLeader $assignTeamLeader): JsonResponse|RedirectResponse
    {
        $this->access->ensureCatalogue();

        $validated = $request->validate([
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->whereNull('deleted_at')],
            'role' => ['required', 'string', Rule::in($this->roleSlugs())],
        ]);

        $team = Team::query()->whereKey($validated['team_id'])->first();

        if (! $team instanceof Team) {
            abort(404);
        }

        if ($validated['role'] === TeamRole::TeamLead->value) {
            $assignTeamLeader->handle($team, $user);
        } else {
            $team->memberships()->updateOrCreate(
                ['user_id' => $user->id],
                ['role' => $validated['role']],
            );

            $user->refresh();

            if ($user->current_team_id === null) {
                $user->switchTeam($team);
            }
        }

        return $this->respond($request, __(':name is now on :team.', [
            'name' => $user->name,
            'team' => $team->name,
        ]));
    }

    public function remove(Request $request, User $user, Team $team): JsonResponse|RedirectResponse
    {
        abort_unless($user->belongsToTeam($team), 404);

        $wasCurrent = $user->isCurrentTeam($team);

        $team->memberships()->where('user_id', $user->id)->delete();

        if ($wasCurrent) {
            $next = $user->teams()->first();

            if ($next instanceof Team) {
                $user->switchTeam($next);
            } else {
                $user->update(['current_team_id' => null]);
            }
        }

        return $this->respond($request, __(':name was removed from :team.', [
            'name' => $user->name,
            'team' => $team->name,
        ]));
    }

    /**
     * @return array{id: int, name: string, email: string, created_at: string|null, teams: list<array{id: int, name: string, slug: string, role: string, role_label: string}>}
     */
    private function present(User $user): array
    {
        $teams = [];

        foreach ($user->teams as $team) {
            $role = $team->pivot->role ?? null;
            $slug = $role instanceof TeamRole ? $role->value : (string) $role;

            $teams[] = [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'role' => $slug,
                'role_label' => $slug === '' ? '' : $this->access->labelFor($team, $slug),
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at?->toDateString(),
            'teams' => $teams,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function teamOptions(): array
    {
        $teams = [];

        foreach (Team::query()->orderBy('name')->get(['id', 'name']) as $team) {
            $teams[] = [
                'id' => $team->id,
                'name' => $team->name,
            ];
        }

        return $teams;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        $options = [[
            'value' => TeamRole::TeamLead->value,
            'label' => $this->access->labelFor(new Team, TeamRole::TeamLead->value),
        ]];

        foreach ($this->access->assignableOptions(new Team) as $option) {
            $options[] = $option;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function roleSlugs(): array
    {
        $slugs = [];

        foreach ($this->roleOptions() as $option) {
            $slugs[] = $option['value'];
        }

        return $slugs;
    }

    private function respond(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('admin.users.index');
        }

        return response()->json(['message' => $message]);
    }
}
