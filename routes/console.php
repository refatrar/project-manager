<?php

use App\Console\Commands\OMS\GenerateDailyTodoLists;
use App\Console\Commands\OMS\ReconcileProjectData;
use App\Console\Commands\OMS\SnapshotProjectProgress;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command(ReconcileProjectData::class)->dailyAt('00:00');
Schedule::command(SnapshotProjectProgress::class)->dailyAt('00:15');
Schedule::command(GenerateDailyTodoLists::class)->dailyAt('00:30');
