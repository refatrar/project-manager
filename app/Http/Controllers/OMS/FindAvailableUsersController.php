<?php

namespace App\Http\Controllers\OMS;

use App\Actions\OMS\FindAvailableUsers;
use App\Enums\TeamModulePermission;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FindAvailableUsersController extends Controller
{
    /**
     * Answer "who has N free hours per day between two dates" (RD.md
     * FR-8.5). Restricted to a team admin, same wide-visibility threshold
     * used everywhere else in the app that touches capacity data — even
     * though this only ever exposes a plain yes/no per person, not the
     * hours or bookings behind it, it's the same kind of cross-member
     * capacity signal FR-8.8/FR-8.9 (still open, still needs an ADR) are
     * about, so this deliberately doesn't open it to project managers
     * more broadly until that's resolved.
     */
    public function index(Request $request, Team $current_team, FindAvailableUsers $findAvailableUsers): Response
    {
        $user = $request->user('web');
        abort_unless($user !== null, 403);

        abort_unless($user->teamCan($current_team, TeamModulePermission::ViewAvailability), 403);

        $filters = $request->only(['from', 'to', 'hours_per_day']);
        $results = null;

        if ($request->filled('from') && $request->filled('to') && $request->filled('hours_per_day')) {
            $validated = $request->validate([
                'from' => ['required', 'date'],
                'to' => ['required', 'date', 'after_or_equal:from'],
                'hours_per_day' => ['required', 'numeric', 'min:0.5', 'max:24'],
            ]);

            $candidates = $current_team->members()->get();

            $results = $findAvailableUsers
                ->handle(
                    $candidates,
                    Carbon::parse($validated['from']),
                    Carbon::parse($validated['to']),
                    (float) $validated['hours_per_day'],
                )
                ->map(fn (User $available): array => [
                    'id' => $available->id,
                    'name' => $available->name,
                    'email' => $available->email,
                ])
                ->values();
        }

        return Inertia::render('availability/index', [
            'filters' => $filters,
            'results' => $results,
        ]);
    }
}
