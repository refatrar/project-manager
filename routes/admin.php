<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TeamController;
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

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
        Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::patch('permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

        Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
        Route::post('admins', [AdminController::class, 'store'])->name('admins.store');
    });
});
