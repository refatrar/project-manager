<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A signed-in platform admin editing their own account.
 */
class ProfileController extends Controller
{
    public function edit(): Response
    {
        $admin = $this->admin();

        return Inertia::render('admin/profile/edit', [
            'profile' => $admin->toProfileArray(),
            'passwordRules' => Password::default()->toPasswordRulesString(),
        ]);
    }

    public function update(UpdateAdminProfileRequest $request): RedirectResponse
    {
        $admin = $this->admin();
        $admin->fill($request->safe()->only(['name', 'email']));
        $admin->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('admin.profile.edit');
    }

    public function updatePassword(UpdateAdminPasswordRequest $request): RedirectResponse
    {
        $admin = $this->admin();
        $admin->password = $request->validated('password');
        $admin->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return to_route('admin.profile.edit');
    }

    private function admin(): Admin
    {
        /** @var Admin $admin */
        $admin = request()->user('admin');

        return $admin;
    }
}
