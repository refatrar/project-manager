<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * The net-new capability TASKS.md 7.8 asked for on top of migrating the
 * existing fixed 4 permissions onto `spatie/laravel-permission`: an admin
 * can define a new, module-scoped permission key at runtime, not just
 * assign the ones `AdminPermission` already hard-codes. Gated by the same
 * `roles.manage` permission `RoleController` uses — creating a permission
 * is part of the same "manage access control" capability as creating a
 * role, not a fourth permission tier.
 */
class PermissionController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeManageRoles($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->where('guard_name', 'admin')],
            'module' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
        ]);

        Permission::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'admin',
            'module' => $validated['module'],
            'label' => $validated['label'],
        ]);

        return $this->respond($request, __('Permission created.'));
    }

    public function update(Request $request, Permission $permission): JsonResponse|RedirectResponse
    {
        $this->authorizeManageRoles($request);

        $this->abortIfBuiltIn($permission);

        $validated = $request->validate([
            'module' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
        ]);

        $permission->update($validated);

        return $this->respond($request, __('Permission updated.'));
    }

    public function destroy(Request $request, Permission $permission): JsonResponse|RedirectResponse
    {
        $this->authorizeManageRoles($request);

        $this->abortIfBuiltIn($permission);

        $permission->delete();

        return $this->respond($request, __('Permission deleted.'));
    }

    /**
     * The 4 permission keys `AdminPermission` hard-codes are what the
     * application code itself branches on (every `authorizeManageX()`
     * helper across the admin controllers) — renaming or deleting one
     * here would lock an admin out of a whole panel section with no way
     * back in, the same reasoning `Role::is_system` already protects
     * against for roles.
     */
    private function abortIfBuiltIn(Permission $permission): void
    {
        $builtIn = array_map(fn (AdminPermission $case): string => $case->value, AdminPermission::cases());

        abort_if(in_array($permission->name, $builtIn, true), 403, __('This permission is built in and cannot be changed.'));
    }

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
