<?php

namespace App\Providers;

use App\Models\OMS\Milestone;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectMember;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\Sprint;
use App\Models\OMS\Task;
use App\Observers\OMS\MilestoneObserver;
use App\Observers\OMS\ProjectMemberObserver;
use App\Observers\OMS\ProjectModuleObserver;
use App\Observers\OMS\ProjectObserver;
use App\Observers\OMS\SprintObserver;
use App\Observers\OMS\TaskObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerActivityObservers();
    }

    /**
     * Populate the project activity feed (FR-4.4) from create/delete
     * lifecycle events. Status changes, assignments and dependency links
     * are recorded directly by their own actions instead, since a generic
     * "updated" hook can't produce an accurate description from a dirty
     * attribute diff alone.
     */
    protected function registerActivityObservers(): void
    {
        Project::observe(ProjectObserver::class);
        ProjectModule::observe(ProjectModuleObserver::class);
        ProjectMember::observe(ProjectMemberObserver::class);
        Task::observe(TaskObserver::class);
        Milestone::observe(MilestoneObserver::class);
        Sprint::observe(SprintObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
