<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OMS\MeetingAgendaItemController;
use App\Http\Controllers\OMS\MeetingAttendeeController;
use App\Http\Controllers\OMS\MeetingController;
use App\Http\Controllers\OMS\MilestoneController;
use App\Http\Controllers\OMS\MyDayController;
use App\Http\Controllers\OMS\ProjectController;
use App\Http\Controllers\OMS\ProjectMemberController;
use App\Http\Controllers\OMS\ProjectModuleController;
use App\Http\Controllers\OMS\ResourceAllocationController;
use App\Http\Controllers\OMS\SprintController;
use App\Http\Controllers\OMS\TaskAssignmentController;
use App\Http\Controllers\OMS\TaskController;
use App\Http\Controllers\OMS\TaskDependencyController;
use App\Http\Controllers\OMS\TimeOffRequestController;
use App\Http\Controllers\OMS\TodoItemController;
use App\Http\Controllers\OMS\TodoListController;
use App\Http\Controllers\OMS\UserWorkScheduleController;
use App\Http\Controllers\Setup\LabelController;
use App\Http\Controllers\Setup\ScopeController;
use App\Http\Controllers\Setup\TaskTypeController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::prefix('setup')->name('setup.')->group(function () {
            Route::resource('scopes', ScopeController::class)->except(['create', 'show', 'edit']);
            Route::resource('task-types', TaskTypeController::class)->except(['create', 'show', 'edit']);
            Route::resource('labels', LabelController::class)->except(['create', 'show', 'edit']);
        });

        // Every create/edit interaction here happens via a modal on the
        // index or show page rather than a dedicated route (ADR-013).
        Route::resource('projects', ProjectController::class)->except(['create', 'edit']);
        Route::patch('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');

        // Registered before the resource so the literal "reorder" segment
        // isn't swallowed by the {module} route-model-binding on update.
        Route::patch('projects/{project}/modules/reorder', [ProjectModuleController::class, 'reorder'])->name('projects.modules.reorder');
        Route::resource('projects.modules', ProjectModuleController::class)->except(['create', 'index', 'show', 'edit']);

        Route::resource('projects.members', ProjectMemberController::class)->except(['create', 'index', 'show', 'edit']);

        // Registered before the resource so the literal "move" segment
        // isn't swallowed by the {task} route-model-binding on update.
        Route::patch('projects/{project}/tasks/{task}/move', [TaskController::class, 'move'])->name('projects.tasks.move');
        // `show` is kept: the task detail page is a legitimate deep-linkable
        // exception to the modal-only rule (ADR-013), not a create/edit route.
        Route::resource('projects.tasks', TaskController::class)->except(['create', 'index', 'edit']);

        Route::resource('projects.tasks.assignments', TaskAssignmentController::class)->except(['create', 'index', 'show', 'edit', 'update']);
        Route::resource('projects.tasks.dependencies', TaskDependencyController::class)->except(['create', 'index', 'show', 'edit', 'update']);

        Route::resource('projects.milestones', MilestoneController::class)->except(['create', 'index', 'show', 'edit']);
        Route::resource('projects.sprints', SprintController::class)->except(['create', 'index', 'show', 'edit']);

        // Named "allocation" (not the default "resource_allocation"), to
        // match the controller's parameter name.
        Route::resource('projects.resource-allocations', ResourceAllocationController::class)
            ->except(['create', 'index', 'show', 'edit'])
            ->parameters(['resource-allocations' => 'allocation']);

        // Personal to-do lists (FR-5.1 – FR-5.5), managed entirely on one
        // page (ADR-013) — no separate create/edit/show routes.
        Route::resource('todo-lists', TodoListController::class)->except(['create', 'show', 'edit']);

        // Registered before the resource so the literal "toggle" segment
        // isn't swallowed by the {item} route-model-binding on update.
        Route::patch('todo-lists/{todo_list}/items/{item}/toggle', [TodoItemController::class, 'toggle'])->name('todo-lists.items.toggle');
        Route::post('todo-lists/{todo_list}/items/{item}/promote', [TodoItemController::class, 'promote'])->name('todo-lists.items.promote');
        Route::resource('todo-lists.items', TodoItemController::class)->except(['create', 'index', 'show', 'edit']);

        Route::get('my-day', [MyDayController::class, 'index'])->name('my-day');

        // The acting user's own weekly capacity template (FR-8.1) — no
        // route-bound model, always scoped to the authenticated user.
        Route::get('work-schedule', [UserWorkScheduleController::class, 'index'])->name('work-schedule.index');
        Route::post('work-schedule', [UserWorkScheduleController::class, 'store'])->name('work-schedule.store');

        // Registered before the resource so the literal "cancel"/"decide"
        // segments aren't swallowed by the {time_off_request} binding.
        Route::patch('time-off-requests/{time_off_request}/cancel', [TimeOffRequestController::class, 'cancel'])->name('time-off-requests.cancel');
        Route::patch('time-off-requests/{time_off_request}/decide', [TimeOffRequestController::class, 'decide'])->name('time-off-requests.decide');
        Route::resource('time-off-requests', TimeOffRequestController::class)->except(['create', 'show', 'edit', 'destroy']);

        // `show` is kept: the meeting workspace is a deep-linkable page,
        // not a create/edit route (ADR-013).
        Route::patch('meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])->name('meetings.cancel');
        Route::put('meetings/{meeting}/minutes', [MeetingController::class, 'updateMinutes'])->name('meetings.minutes.update');
        Route::patch('meetings/{meeting}/minutes/publish', [MeetingController::class, 'publishMinutes'])->name('meetings.minutes.publish');
        Route::post('meetings/{meeting}/timer/start', [MeetingController::class, 'startTimer'])->name('meetings.timer.start');
        Route::patch('meetings/{meeting}/timer/stop', [MeetingController::class, 'stopTimer'])->name('meetings.timer.stop');
        Route::resource('meetings', MeetingController::class)->except(['create', 'edit']);

        Route::resource('meetings.attendees', MeetingAttendeeController::class)->except(['create', 'index', 'show', 'edit']);

        // Registered before the resource so the literal "move"/"toggle"
        // segments aren't swallowed by the {item} route-model-binding.
        Route::patch('meetings/{meeting}/agenda-items/{item}/move', [MeetingAgendaItemController::class, 'move'])->name('meetings.agenda-items.move');
        Route::patch('meetings/{meeting}/agenda-items/{item}/toggle', [MeetingAgendaItemController::class, 'toggle'])->name('meetings.agenda-items.toggle');
        // Named "item" (not the default "agenda_item"), to match the
        // controller's parameter name and the move/toggle routes above.
        Route::resource('meetings.agenda-items', MeetingAgendaItemController::class)
            ->except(['create', 'index', 'show', 'edit'])
            ->parameters(['agenda-items' => 'item']);
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
