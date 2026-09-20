<?php

use App\Http\Controllers\DashboardController;
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
        });
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
