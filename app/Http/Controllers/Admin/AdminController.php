<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Managing other admin accounts. Any signed-in platform admin can do this.
 * Admins are not assigned roles; team-module permissions live on team roles.
 */
class AdminController extends Controller
{
    public function index(): Response
    {
        $admins = [];

        foreach (Admin::query()->orderBy('name')->get() as $admin) {
            $admins[] = [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ];
        }

        return Inertia::render('admin/admins/index', [
            'admins' => $admins,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('admins', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ]);

        Admin::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $message = __('Admin account created.');

        if ($request->header('X-Inertia')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

            return to_route('admin.admins.index');
        }

        return response()->json(['message' => $message]);
    }
}
