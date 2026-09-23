<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        return Inertia::render('admin/dashboard', [
            'admin' => [
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'teamCount' => Team::query()->count(),
            'leaderlessTeamCount' => Team::query()->whereDoesntHave('memberships')->count(),
        ]);
    }
}
