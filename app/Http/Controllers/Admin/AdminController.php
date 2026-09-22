<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Managing other admin accounts (Phase 7) — own class name to avoid
 * colliding with the `Admin` model it manages.
 */
class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManageAdmins($request);

        return Inertia::render('admin/admins/index', [
            'admins' => Admin::query()
                ->with('roles:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (Admin $admin): array => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'roles' => $admin->roles->pluck('name'),
                ]),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeManageAdmins($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('admins', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['array'],
            'roles.*' => [Rule::exists('roles', 'id')],
        ]);

        $admin = Admin::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $admin->roles()->sync($validated['roles'] ?? []);

        $message = __('Admin account created.');

        // The create form on admin/admins submits through `useHttp`, a
        // plain JSON fetch — not an Inertia visit. It follows a redirect
        // itself and then tries to parse the resulting HTML page as JSON,
        // which throws before its `onSuccess` ever runs. So a redirect
        // only works for a real Inertia visit (`X-Inertia` header
        // present); everything else gets a plain JSON body instead,
        // matching `UserWorkScheduleController::store()`'s established
        // convention.
        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('admin.admins.index');
        }

        return response()->json(['message' => $message]);
    }

    private function authorizeManageAdmins(Request $request): void
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        abort_unless($admin->hasPermission(AdminPermission::ManageAdmins->value), 403);
    }
}
