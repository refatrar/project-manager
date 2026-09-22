<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin-panel role management (Phase 7's configurable RBAC — see
 * docs/architecture-decisions.md ADR-015 for why this is a separate system
 * from `TeamRole`/`TeamPermission`, not a replacement for it).
 */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManageRoles($request);

        return Inertia::render('admin/roles/index', [
            'roles' => Role::query()
                ->with('permissions:id,key,label,group')
                ->withCount('admins')
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role): array => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'is_system' => $role->is_system,
                    'admins_count' => $role->admins_count,
                    'permission_ids' => $role->permissions->pluck('id'),
                ]),
            'permissions' => Permission::query()
                ->orderBy('group')
                ->orderBy('label')
                ->get(['id', 'key', 'label', 'group']),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeManageRoles($request);

        $validated = $this->validated($request);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'],
        ]);
        $role->permissions()->sync($validated['permissions']);

        return $this->respond($request, __('Role created.'));
    }

    public function update(Request $request, Role $role): JsonResponse|RedirectResponse
    {
        $this->authorizeManageRoles($request);

        $validated = $this->validated($request);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
        ]);

        // A protected role's permission set is fixed (it exists precisely
        // so there's always at least one working way into the panel) —
        // its name/description can still be edited, just not stripped of
        // access.
        if (! $role->is_system) {
            $role->permissions()->sync($validated['permissions']);
        }

        return $this->respond($request, __('Role updated.'));
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeManageRoles($request);

        abort_if($role->is_system, 403, __('This role is protected and cannot be deleted.'));

        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return to_route('admin.roles.index');
    }

    /**
     * @return array{name: string, description: string|null, permissions: array<int, int>}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'id')],
        ]);

        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'] ?? [],
        ];
    }

    /**
     * The create/edit forms on admin/roles submit through `useHttp`, a
     * plain JSON fetch — not an Inertia visit. It follows a redirect
     * itself and then tries to parse the resulting HTML page as JSON,
     * which throws before its `onSuccess` ever runs. So a redirect only
     * works for a real Inertia visit (`X-Inertia` header present, e.g. a
     * no-JS fallback); everything else gets a plain JSON body instead,
     * matching `WorkScheduleController::store()`'s established
     * convention.
     */
    private function respond(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('admin.roles.index');
        }

        return response()->json(['message' => $message]);
    }

    private function authorizeManageRoles(Request $request): void
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        abort_unless($admin->hasPermission(AdminPermission::ManageRoles->value), 403);
    }
}
