<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TeamInvitation;
use App\Services\Teams\TeamAccessControl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Assigns the permissions team accounts run with. Roles are global on the
 * `web` guard; each team module's policy reads the role stored on the
 * membership.
 */
class TeamRoleController extends Controller
{
    public function __construct(private readonly TeamAccessControl $access) {}

    public function index(): Response
    {
        $this->access->ensureCatalogue();

        $roles = [];

        foreach (Role::query()->where('guard_name', 'web')->whereNull('team_id')->with('permissions:id,name')->orderBy('name')->get() as $role) {
            $permissionIds = [];

            foreach ($role->permissions as $permission) {
                $permissionIds[] = $permission->id;
            }

            $roles[] = [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'accounts_count' => Membership::query()->where('role', $role->slug)->count(),
                'permission_ids' => $permissionIds,
            ];
        }

        return Inertia::render('admin/team-roles/index', [
            'roles' => $roles,
            'permissions' => $this->permissionOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->access->ensureCatalogue();

        $validated = $this->validated($request);
        $slug = $this->uniqueSlug(Str::slug($validated['name']));

        abort_if(
            TeamRole::tryFrom($slug) !== null,
            422,
            __('That name is reserved for a built-in role.'),
        );

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'team_id' => null,
            'slug' => $slug,
            'description' => $validated['description'],
            'is_system' => false,
        ]);
        $role->syncPermissions($validated['permissions']);

        return $this->respond($request, __('Role created.'));
    }

    public function update(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        $this->teamRole($role);

        $validated = $this->validated($request, $role);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
        ]);
        $role->syncPermissions($validated['permissions']);

        return $this->respond($request, __('Role updated.'));
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->teamRole($role);

        abort_if($role->is_system, 403, __('This role is built in and cannot be deleted.'));

        $assigned = Membership::query()->where('role', $role->slug)->exists()
            || TeamInvitation::query()->whereNull('accepted_at')->where('role', $role->slug)->exists();

        abort_if($assigned, 422, __('Reassign team accounts before deleting this role.'));

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.team-roles.index');
    }

    /**
     * @return array{name: string, description: string|null, permissions: array<int, int>}
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->where(
                    fn ($query) => $query->whereNull('team_id')->where('guard_name', 'web'),
                )->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [
                Rule::exists('permissions', 'id')->where('guard_name', 'web'),
            ],
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'] ?? [],
        ];
    }

    /**
     * @return list<array{id: int, name: string, label: string|null, module: string|null}>
     */
    private function permissionOptions(): array
    {
        $options = [];

        foreach (Permission::query()->where('guard_name', 'web')->orderBy('module')->orderBy('label')->get(['id', 'name', 'label', 'module']) as $permission) {
            $options[] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'label' => $permission->label,
                'module' => $permission->module,
            ];
        }

        return $options;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'role';
        $candidate = $slug;
        $suffix = 2;

        while (Role::query()->whereNull('team_id')->where('guard_name', 'web')->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function teamRole(Role $role): void
    {
        abort_unless($role->guard_name === 'web' && $role->team_id === null, 404);
    }

    private function respond(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('admin.team-roles.index');
        }

        return response()->json(['message' => $message]);
    }
}
