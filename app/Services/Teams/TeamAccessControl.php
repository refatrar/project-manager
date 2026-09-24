<?php

namespace App\Services\Teams;

use App\Enums\TeamModulePermission;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
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

        $role = $this->findRole($slug);

        if ($role === null) {
            return $this->defaultNames($slug);
        }

        $role->loadMissing('permissions');

        $names = [];

        foreach ($role->permissions as $permission) {
            $names[] = $permission->name;
        }

        return $names;
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

    public function labelFor(Team $team, string $slug): string
    {
        $builtIn = TeamRole::tryFrom($slug);

        if ($builtIn !== null) {
            return $builtIn->label();
        }

        $stored = $this->findRole($slug);

        return $stored !== null ? $stored->name : Str::headline($slug);
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
