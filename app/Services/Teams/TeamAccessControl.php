<?php

namespace App\Services\Teams;

use App\Enums\ProjectMemberRole;
use App\Enums\TeamModulePermission;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\OMS\Project;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Team-panel RBAC on `spatie/laravel-permission`. The platform admin assigns
 * permissions to global `web`-guard roles. Every team account with that role
 * gets the same grants, and team-module policies read them from here.
 *
 * Until the catalogue has been seeded, checks fall back to the built-in
 * Team Lead / Member matrix so factory-built teams keep working.
 */
class TeamAccessControl
{
    private ?bool $catalogueSeeded = null;

    /**
     * @var array<string, array{name: string, description: string}>
     */
    private const SYSTEM_ROLES = [
        'team_lead' => [
            'name' => 'Team Lead',
            'description' => 'Team lead. Starts with every team-module permission, including managing every project and meeting on the team; the platform admin can change that.',
        ],
        'member' => [
            'name' => 'Member',
            'description' => 'Works on projects they belong to and their own time and to-dos.',
        ],
    ];

    public function syncCatalogue(): void
    {
        $names = [];

        foreach (TeamModulePermission::cases() as $permission) {
            $names[] = $permission->value;

            Permission::query()->updateOrCreate(
                ['name' => $permission->value, 'guard_name' => 'web'],
                ['label' => $permission->label(), 'module' => $permission->module()],
            );
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', $names)
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create the built-in team roles once. Later edits from the admin panel
     * are left in place; defaults apply only the first time a role is created.
     */
    public function ensureCatalogue(): void
    {
        $this->syncCatalogue();

        foreach (self::SYSTEM_ROLES as $slug => $definition) {
            $role = Role::query()->firstOrCreate(
                ['team_id' => null, 'slug' => $slug, 'guard_name' => 'web'],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                ],
            );

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($this->defaultNames($slug));
            }
        }
    }

    public function allows(User $user, Team $team, TeamModulePermission $permission): bool
    {
        return in_array($permission->value, $user->teamAccessList($team), true);
    }

    /**
     * @return list<string>
     */
    public function grantedNames(User $user, Team $team): array
    {
        $slug = $user->membershipRoleSlug($team);

        if ($slug === null) {
            return [];
        }

        return $this->permissionNamesFor($slug);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function assignableOptions(Team $team): array
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereNull('team_id')
            ->where('slug', '!=', TeamRole::TeamLead->value)
            ->orderBy('name')
            ->get();

        if ($roles->isEmpty()) {
            return array_values(TeamRole::assignable());
        }

        $options = [];

        foreach ($roles as $role) {
            $options[] = [
                'value' => (string) $role->slug,
                'label' => $role->name,
            ];
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public function assignableSlugs(Team $team): array
    {
        return array_column($this->assignableOptions($team), 'value');
    }

    /**
     * The assignable roles the actor may hand out on the team — only those
     * whose every permission the actor already holds. Without this, anyone
     * with `member:update` or `invitation:create` could give themselves (or
     * a second account) a custom role more powerful than their own.
     *
     * @return list<array{value: string, label: string}>
     */
    public function grantableOptions(User $actor, Team $team): array
    {
        $held = $actor->teamAccessList($team);

        return array_values(array_filter(
            $this->assignableOptions($team),
            fn (array $option): bool => array_diff($this->permissionNamesFor($option['value']), $held) === [],
        ));
    }

    /**
     * @return list<string>
     */
    public function grantableSlugs(User $actor, Team $team): array
    {
        return array_column($this->grantableOptions($actor, $team), 'value');
    }

    /**
     * @return list<string>
     */
    private function permissionNamesFor(string $slug): array
    {
        $role = $this->findRole($slug);

        // The built-in matrix only stands in while the catalogue has never
        // been seeded (factory-built teams in tests). Once roles exist, a
        // missing row grants nothing rather than silently re-granting the
        // defaults an admin may have taken away.
        if ($role === null) {
            return $this->catalogueSeeded() ? [] : $this->defaultNames($slug);
        }

        $role->loadMissing('permissions');

        return $role->permissions->pluck('name')->values()->all();
    }

    /**
     * Where to land the user on the team: the dashboard when their role
     * can see it, else the first team page it can open — so a role
     * without `dashboard.view` doesn't log straight into a 403. Team
     * settings is the last resort; every member can open it.
     */
    public function homeUrl(User $user, Team $team): string
    {
        $pages = [
            'dashboard.view' => 'dashboard',
            'my-day.view' => 'my-day',
            'projects.view' => 'projects.index',
            'meetings.view' => 'meetings.index',
            'todos.manage' => 'todo-lists.index',
            'time-off.view' => 'time-off-requests.index',
            'time-logs.manage' => 'time-logs.index',
            'timesheet.view' => 'timesheet.index',
        ];

        $granted = $user->teamAccessList($team);

        foreach ($pages as $permission => $routeName) {
            if (in_array($permission, $granted, true)) {
                return route($routeName, ['current_team' => $team->slug]);
            }
        }

        return route('teams.edit', ['team' => $team->slug]);
    }

    /**
     * Whether the timesheet-approvals queue can hold anything for the user
     * — the same people `TimeLogPolicy::decide` lets act: a team-wide
     * approver, or anyone managing a project (manage-all, Project Lead, or
     * an Owner/Manager/Lead project membership) who can use Projects.
     * Drives only the nav link; the queue itself filters by the policy.
     */
    public function canApproveTimesheets(User $user, Team $team): bool
    {
        if ($user->teamCan($team, TeamModulePermission::DecideTimesheets)) {
            return true;
        }

        if (! $user->teamCan($team, TeamModulePermission::ViewProjects)) {
            return false;
        }

        if ($user->teamCan($team, TeamModulePermission::ManageAllProjects) || $user->teamCan($team, TeamModulePermission::ViewAllProjects)) {
            return true;
        }

        return Project::query()
            ->where('team_id', $team->id)
            ->where(fn ($query) => $query
                ->where('project_lead_id', $user->id)
                ->orWhereHas('members', fn ($members) => $members
                    ->active()
                    ->where('user_id', $user->id)
                    ->whereIn('role', array_map(
                        fn (ProjectMemberRole $role): string => $role->value,
                        array_filter(ProjectMemberRole::cases(), fn (ProjectMemberRole $role): bool => $role->canManageProject()),
                    ))))
            ->exists();
    }

    private function catalogueSeeded(): bool
    {
        return $this->catalogueSeeded ??= Role::query()
            ->where('guard_name', 'web')
            ->whereNull('team_id')
            ->exists();
    }

    public function labelFor(Team $team, string $slug): string
    {
        // The stored name wins, even for the built-in slugs: the admin panel
        // can rename them, and the role pickers already list stored names.
        $stored = $this->findRole($slug);

        if ($stored !== null) {
            return $stored->name;
        }

        return TeamRole::tryFrom($slug)?->label() ?? Str::headline($slug);
    }

    /**
     * @return array{role: string, role_label: string}
     */
    public function describe(Team $team, TeamRole|string|null $role): array
    {
        $slug = $role instanceof TeamRole ? $role->value : (string) $role;

        return [
            'role' => $slug,
            'role_label' => $slug === '' ? '' : $this->labelFor($team, $slug),
        ];
    }

    public function findRole(string $slug): ?Role
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->whereNull('team_id')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * @return list<string>
     */
    public function defaultNames(string $slug): array
    {
        return array_map(
            fn (TeamModulePermission $permission): string => $permission->value,
            $this->defaultsFor($slug),
        );
    }

    /**
     * @return list<TeamModulePermission>
     */
    public function defaultsFor(string $slug): array
    {
        $role = TeamRole::tryFrom($slug);

        if ($role === TeamRole::TeamLead) {
            return TeamModulePermission::cases();
        }

        if ($role === TeamRole::Member) {
            return [
                TeamModulePermission::ViewDashboard,
                TeamModulePermission::ViewMyDay,
                TeamModulePermission::ViewProjects,
                TeamModulePermission::CreateProjects,
                TeamModulePermission::ViewMeetings,
                TeamModulePermission::CreateMeetings,
                TeamModulePermission::ManageTodos,
                TeamModulePermission::ViewTimeOff,
                TeamModulePermission::ManageTimeOff,
                TeamModulePermission::ManageTimeLogs,
                TeamModulePermission::ViewTimesheet,
                TeamModulePermission::SubmitTimesheet,
                TeamModulePermission::ManageSetup,
            ];
        }

        // A brand-new custom role (not one of the 2 built-ins) starts with
        // no permissions — the admin panel form that creates it always
        // submits the permissions to grant in the same request.
        return [];
    }

    /**
     * Team-settings permissions stay addressable as `TeamPermission` from
     * `TeamPolicy`. The catalogue stores the same string.
     */
    public function fromTeamPermission(TeamPermission $permission): TeamModulePermission
    {
        return TeamModulePermission::from($permission->value);
    }
}
