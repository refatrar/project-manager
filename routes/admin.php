<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TeamRoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthController::class, 'create'])->name('login');
        Route::post('login', [AuthController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/teams', [UserController::class, 'assign'])->name('users.teams.store');
        Route::delete('users/{user}/teams/{team}', [UserController::class, 'remove'])->name('users.teams.destroy');

        Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
        Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
        Route::patch('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::post('teams/{team}/leader', [TeamController::class, 'assignLeader'])->name('teams.assign-leader');
        Route::delete('teams/{team}/leader', [TeamController::class, 'removeLeader'])->name('teams.remove-leader');

        Route::get('work-schedules', [WorkScheduleController::class, 'index'])->name('work-schedules.index');
        Route::post('work-schedules', [WorkScheduleController::class, 'store'])->name('work-schedules.store');

        Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
        Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

        Route::get('team-roles', [TeamRoleController::class, 'index'])->name('team-roles.index');
        Route::post('team-roles', [TeamRoleController::class, 'store'])->name('team-roles.store');
        Route::patch('team-roles/{role}', [TeamRoleController::class, 'update'])->name('team-roles.update');
        Route::delete('team-roles/{role}', [TeamRoleController::class, 'destroy'])->name('team-roles.destroy');

        Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
        Route::post('admins', [AdminController::class, 'store'])->name('admins.store');
    });
});
