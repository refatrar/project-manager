<?php

namespace Database\Seeders;

use App\Actions\OMS\AddTaskDependency;
use App\Actions\OMS\CalculateProjectProgress;
use App\Actions\OMS\CreateProject;
use App\Actions\OMS\DetermineProjectHealth;
use App\Enums\Priority;
use App\Enums\ProjectHealth;
use App\Enums\ProjectModuleStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskDependencyType;
use App\Enums\TaskStatus;
use App\Models\OMS\Project;
use App\Models\OMS\ProjectModule;
use App\Models\OMS\Task;
use App\Models\OMS\TaskDependency;
use App\Models\Setup\Label;
use App\Models\Setup\Scope;
use App\Models\Setup\TaskType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Import of the Mothers@Work (M@W) Digital Monitoring System master task
 * and requirement register (`Mother-At-Work_TASKS.md`, generated
 * 2026-09-23) into a dedicated "Mothers@Work" project (code `MAW`):
 * 22 `ProjectModule`s and 85 `Task`s, one row per task in the
 * source register.
 *
 * Traceability: task number N is source TASK-N (MAW-17 is TASK-017). Each
 * task's description starts with its source task ID, requirement IDs
 * (CR/COM/REV-JUN/REV-JUL/REV-SEP/REVIEW/BUG), source documents, and its
 * original status, priority and type exactly as written. After that comes
 * the full source entry, then the traceability matrix rows, conflicts,
 * open questions and implementation phase that refer to the task. Source
 * categories are also `Label`s. The app has no requirement or source-
 * document table, so labels and descriptions are the existing mechanism.
 *
 * Status mapping (the app has no "needs verification" or "conflict"
 * status, so the original string stays in the description, and the
 * `Needs Review` / `Conflict` labels mark them):
 *   Implemented                                   -> done
 *   Implemented — Needs Verification              -> ready_for_qa
 *   Partially Implemented (any qualifier)         -> in_progress
 *   Not Implemented (blocked / blocked on client /
 *     partly blocked)                             -> blocked
 *   Not Implemented (± Needs Verification)        -> todo
 *   Needs Verification (primary: not yet checked) -> todo
 *   Bug (a known, unfixed defect)                 -> todo
 *   Conflict (awaiting a client decision)         -> blocked
 *
 * Dependencies: every resolvable `Dependencies:` reference becomes a
 * `TaskDependency` owned by the referencing task. It is `blocked_by` where
 * the source says "blocker" or "Blocks", or appears in TASK-085's
 * client-blocker table; otherwise it is `relates_to`. Each link is added
 * through `AddTaskDependency`, so cycle checks and activity logging apply.
 * Some source references were stale (renumbered, or TASK-086 to TASK-096,
 * which don't exist). Those are corrected or left unlinked, with the
 * reason in the task's description.
 *
 * Not wired into `DatabaseSeeder`, because this is a real client dataset,
 * not demo data (same reasoning as `AlpRtmImportSeeder`). It needs an
 * existing team, and the base `TaskType`/`Scope` seed data from
 * `TaskTypeSeeder`/`ScopeSeeder`.
 *
 * Idempotent and safe to re-run. The project, labels, modules and tasks
 * are found before anything is created: the project by code `MAW`, labels
 * by name, modules by name, tasks by number. On a re-run, the fields the
 * source owns (title, description, module, source labels) are refreshed.
 * Workflow fields that people may have changed since (status, priority,
 * type, assignees) are left alone.
 *
 * Run with: php artisan db:seed --class=MothersAtWorkImportSeeder
 */
class MothersAtWorkImportSeeder extends Seeder
{
    private const PROJECT_CODE = 'MAW';

    /**
     * @var array<string, int>
     */
    private array $stats = [];

    /**
     * Seed the database.
     */
    public function run(): void
    {
        $team = Team::query()->first();
        if ($team === null) {
            $this->command->warn('MothersAtWorkImportSeeder: no team exists yet - run the base seeders first. Skipping.');

            return;
        }

        $creator = User::query()->where('email', 'hasib@kaz-software.com')->first();
        if ($creator === null) {
            $this->command->warn('MothersAtWorkImportSeeder: no user exists to attribute the import to. Skipping.');

            return;
        }

        $taskTypeIds = TaskType::query()->whereIn('name', ['Feature', 'Bug', 'Enhancement', 'Research', 'Documentation', 'Maintenance'])->pluck('id', 'name');
        if ($taskTypeIds->count() < 6) {
            throw new RuntimeException('MothersAtWorkImportSeeder: run TaskTypeSeeder first - one or more of the 6 required task types is missing.');
        }

        $scopeIds = Scope::query()->pluck('id', 'name');

        DB::transaction(function () use ($team, $creator, $taskTypeIds, $scopeIds): void {
            $project = $this->findOrCreateProject($team, $creator);
            $labelIds = $this->findOrCreateLabels($team, $creator);

            $taskIds = [];
            foreach ($this->modules() as $modulePlan) {
                $module = $this->findOrCreateModule($project, $modulePlan, $creator, $scopeIds);

                foreach ($modulePlan['tasks'] as $taskPlan) {
                    $task = $this->createOrUpdateTask($project, $module, $taskPlan, $creator, $taskTypeIds, $labelIds);
                    if ($task !== null) {
                        $taskIds[$taskPlan['source_task_id']] = $task;
                    }
                }
            }

            $this->reserveTaskNumbers($project);
            $this->createDependencies($taskIds, $creator);
            $this->rollUpProgress($project);
        });

        $this->command->info('MothersAtWorkImportSeeder: Mothers@Work project imported (22 modules, 85 tasks, 104 dependencies).');
        $this->command->table(
            ['Record', 'Count'],
            collect($this->stats)->map(fn (int $count, string $key): array => [$key, $count])->values()->all(),
        );
    }

    private function count(string $key): void
    {
        $this->stats[$key] = ($this->stats[$key] ?? 0) + 1;
    }

    private function findOrCreateProject(Team $team, User $creator): Project
    {
        $project = Project::query()->where('team_id', $team->id)->where('code', self::PROJECT_CODE)->first();
        if ($project !== null) {
            $this->count('project reused');

            return $project;
        }

        if (Project::onlyTrashed()->where('team_id', $team->id)->where('code', self::PROJECT_CODE)->exists()) {
            throw new RuntimeException('MothersAtWorkImportSeeder: a deleted project with code MAW exists on this team - restore or force-delete it first.');
        }

        $project = app(CreateProject::class)->handle($team, $creator, [
            'code' => self::PROJECT_CODE,
            'name' => 'Mothers@Work',
            'description' => "The “Mother at Work” Learning Management and Monitoring System (LMS) is a digital platform designed to support ready-made garment (RMG) factories in Bangladesh in implementing and monitoring compliance with seven essential maternity protection standards for pregnant and post-partum women workers.
The main goal of this application is to ensure that factories provide a safe, fair, and supportive working environment for women during pregnancy and after childbirth.",
            'status' => ProjectStatus::Active,
            'priority' => Priority::High,
            'health' => ProjectHealth::OnTrack,
            'client_name' => 'UNICEF',
        ]);
        $this->count('project created');

        return $project->refresh();
    }

    /**
     * @return array<string, int>
     */
    private function findOrCreateLabels(Team $team, User $creator): array
    {
        $ids = [];
        foreach ($this->labels() as $name => [$color, $description]) {
            $label = Label::query()->where('team_id', $team->id)->where('name', $name)->first();
            if ($label === null) {
                $label = new Label(['name' => $name, 'color' => $color, 'description' => $description]);
                $label->team_id = $team->id;
                $label->created_by = $creator->id;
                $label->save();
                $this->count('labels created');
            } else {
                $this->count('labels reused');
            }
            $ids[$name] = $label->id;
        }

        return $ids;
    }

    /**
     * @param  array{module_num: int, name: string, description: string, status: string, priority: string, scopes: array<int, string>, tasks: array<int, mixed>}  $modulePlan
     * @param  Collection<string, int>  $scopeIds
     */
    private function findOrCreateModule(Project $project, array $modulePlan, User $creator, $scopeIds): ProjectModule
    {
        $module = ProjectModule::query()->where('project_id', $project->id)->where('name', $modulePlan['name'])->first();
        if ($module === null) {
            $module = new ProjectModule([
                'name' => $modulePlan['name'],
                'description' => $modulePlan['description'],
                'status' => ProjectModuleStatus::from($modulePlan['status']),
                'priority' => Priority::from($modulePlan['priority']),
                'position' => $modulePlan['module_num'],
            ]);
            $module->project_id = $project->id;
            $module->created_by = $creator->id;
            $module->save();
            $this->count('modules created');
        } else {
            if ($module->description !== $modulePlan['description']) {
                $module->description = $modulePlan['description'];
                $module->updated_by = $creator->id;
                $module->save();
                $this->count('modules updated');
            } else {
                $this->count('modules reused');
            }
        }

        $attach = collect($modulePlan['scopes'])->map(fn (string $name): ?int => $scopeIds[$name] ?? null)->filter()->values()->all();
        if (count($attach) < count($modulePlan['scopes'])) {
            $this->count('module scopes missing');
        }
        $module->deliveryScopes()->syncWithoutDetaching($attach);

        return $module;
    }

    /**
     * @param  array{source_task_id: string, number: int, title: string, description: string, source_status: string, status: string, priority: string, task_type: string, labels: array<int, string>}  $taskPlan
     * @param  Collection<string, int>  $taskTypeIds
     * @param  array<string, int>  $labelIds
     */
    private function createOrUpdateTask(Project $project, ProjectModule $module, array $taskPlan, User $creator, $taskTypeIds, array $labelIds): ?Task
    {
        $marker = '**Source Task ID:** '.$taskPlan['source_task_id'].' ';

        $task = Task::withTrashed()->where('project_id', $project->id)->where('number', $taskPlan['number'])->first();

        if ($task !== null && ! str_starts_with((string) $task->description, $marker)) {
            throw new RuntimeException("MothersAtWorkImportSeeder: MAW-{$taskPlan['number']} exists but is not the imported {$taskPlan['source_task_id']} - refusing to overwrite it.");
        }

        if ($task !== null && $task->trashed()) {
            $this->command->warn("MothersAtWorkImportSeeder: {$taskPlan['source_task_id']} (MAW-{$taskPlan['number']}) was deleted in the app - left deleted.");
            $this->count('tasks skipped (deleted in app)');

            return null;
        }

        if ($task === null) {
            $status = TaskStatus::from($taskPlan['status']);

            $task = new Task([
                'title' => $taskPlan['title'],
                'description' => $taskPlan['description'],
                'status' => $status,
                'priority' => Priority::from($taskPlan['priority']),
                'position' => (int) $project->tasks()->where('status', $status)->max('position') + 1,
            ]);
            $task->project_id = $project->id;
            $task->project_module_id = $module->id;
            $task->task_type_id = $taskTypeIds[$taskPlan['task_type']];
            $task->number = $taskPlan['number'];
            $task->created_by = $creator->id;
            $task->save();
            $this->count('tasks created');
        } else {
            $task->title = $taskPlan['title'];
            $task->description = $taskPlan['description'];
            $task->project_module_id = $module->id;
            if ($task->isDirty()) {
                $task->updated_by = $creator->id;
                $task->save();
                $this->count('tasks updated');
            } else {
                $this->count('tasks unchanged');
            }
        }

        $task->labels()->syncWithoutDetaching(
            array_values(array_map(static fn (string $name): int => $labelIds[$name], $taskPlan['labels'])),
        );

        return $task;
    }

    /**
     * Task numbers mirror the source task IDs, so the project's counter must
     * start after the highest one for tasks created later in the app.
     */
    private function reserveTaskNumbers(Project $project): void
    {
        $next = (int) Task::withTrashed()->where('project_id', $project->id)->max('number') + 1;
        if ($project->next_task_number < $next) {
            $project->next_task_number = $next;
            $project->save();
        }
    }

    /**
     * @param  array<string, Task>  $tasks
     */
    private function createDependencies(array $tasks, User $creator): void
    {
        $addDependency = app(AddTaskDependency::class);

        foreach ($this->dependencies() as [$from, $to, $type]) {
            if (! isset($tasks[$from], $tasks[$to])) {
                $this->count('dependencies skipped (task missing)');

                continue;
            }

            $exists = TaskDependency::query()
                ->where(fn ($query) => $query
                    ->where(fn ($q) => $q->where('task_id', $tasks[$from]->id)->where('related_task_id', $tasks[$to]->id))
                    ->orWhere(fn ($q) => $q->where('task_id', $tasks[$to]->id)->where('related_task_id', $tasks[$from]->id)))
                ->exists();
            if ($exists) {
                $this->count('dependencies reused');

                continue;
            }

            try {
                $addDependency->handle($tasks[$from], $tasks[$to], TaskDependencyType::from($type), $creator);
                $this->count('dependencies created');
            } catch (RuntimeException $exception) {
                $this->command->warn("MothersAtWorkImportSeeder: {$from} -> {$to} ({$type}) not linked: {$exception->getMessage()}");
                $this->count('dependencies rejected');
            }
        }
    }

    /**
     * Derive progress and health the same way `oms:reconcile` does, rather
     * than hard-coding them.
     */
    private function rollUpProgress(Project $project): void
    {
        $calculateProgress = app(CalculateProjectProgress::class);

        $progress = $calculateProgress->handle($project);
        $project->progress_percentage = $progress->progressPercentage;
        $project->health = app(DetermineProjectHealth::class)->handle($project, $progress);
        $project->save();

        $project->modules()->get(['id'])->each(function (ProjectModule $module) use ($calculateProgress): void {
            $module->progress_percentage = $calculateProgress->forModule($module);
            $module->save();
        });
    }

    /**
     * Team labels for the source categories. Names shared with
     * `AlpRtmImportSeeder` (and `Needs Review` from `LabelSeeder`) are reused.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function labels(): array
    {
        return [
            'Source: Client Requirement (ToR)' => ['#0891B2', 'Client requirement traced to the Terms of Reference (CR-nnn).'],
            'Source: SRS Commitment' => ['#4338CA', 'Our commitment traced to the SRS (COM-nnn).'],
            'Source: Inception Commitment' => ['#7C3AED', 'Our commitment traced to the Inception Report (COM-nnn).'],
            'Source: Review 29 Jun' => ['#B45309', 'Change item from the 29 June review meeting minutes (REV-JUN-nnn).'],
            'Source: Review 13 Jul' => ['#C2410C', 'Change item from the 13 July review meeting minutes (REV-JUL-nnn).'],
            'Source: Review 17 Sep' => ['#DC2626', 'Change item from the 17 September review meeting minutes (REV-SEP-nnn).'],
            'Source: Code Review' => ['#BE123C', 'Technical, architectural or security review finding (REVIEW-nnn).'],
            'Source: Code Defect' => ['#9F1239', 'Defect found in the current code (BUG-nnn).'],
            'New Requirement' => ['#15803D', 'Not traceable to the TOR, SRS or Inception Report.'],
            'Commitment Extension' => ['#0F766E', 'Committed in the SRS/Inception Report but not in the TOR.'],
            'Security' => ['#7F1D1D', 'Security-relevant work.'],
            'Needs Review' => ['#CA8A04', 'Needs verification against a running instance.'],
            'Conflict' => ['#6B21A8', 'Contradictory requirements awaiting a client decision.'],
        ];
    }

    /**
     * Links as [owning task, related task, type], in source order.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function dependencies(): array
    {
        return [
            ['TASK-001', 'TASK-030', 'relates_to'], // TASK-001 "Dependencies: TASK-025 (public LMS)" (resolved to TASK-030)
            ['TASK-001', 'TASK-002', 'blocked_by'], // TASK-002 "Dependencies: Blocks TASK-001"
            ['TASK-003', 'TASK-004', 'relates_to'], // TASK-003 "Dependencies: TASK-004 (MFA)"
            ['TASK-005', 'TASK-069', 'relates_to'], // TASK-005 "Dependencies: TASK-057 (sample user database, REV-JUN)" (resolved to TASK-069)
            ['TASK-007', 'TASK-008', 'relates_to'], // TASK-007 "Dependencies: TASK-008"
            ['TASK-007', 'TASK-050', 'relates_to'], // TASK-007 "Dependencies: TASK-050"
            ['TASK-009', 'TASK-010', 'relates_to'], // TASK-009 "Dependencies: TASK-010"
            ['TASK-009', 'TASK-062', 'relates_to'], // TASK-009 "Dependencies: TASK-076 (user manual)" (resolved to TASK-062)
            ['TASK-009', 'TASK-027', 'relates_to'], // TASK-009 "Dependencies: TASK-033 (report naming)" (resolved to TASK-027)
            ['TASK-012', 'TASK-046', 'relates_to'], // TASK-012 "Dependencies: TASK-046 (dashboard tiles)"
            ['TASK-012', 'TASK-058', 'relates_to'], // TASK-012 "Dependencies: TASK-062 (webhooks)" (resolved to TASK-058)
            ['TASK-015', 'TASK-026', 'relates_to'], // TASK-015 "Dependencies: TASK-033 (report set)" (resolved to TASK-026)
            ['TASK-016', 'TASK-017', 'relates_to'], // TASK-016 "Dependencies: TASK-017"
            ['TASK-016', 'TASK-026', 'relates_to'], // TASK-016 "Dependencies: TASK-033 (7 reports)" (resolved to TASK-026)
            ['TASK-018', 'TASK-070', 'relates_to'], // TASK-018 "Dependencies: TASK-070 (localization)"
            ['TASK-020', 'TASK-021', 'relates_to'], // TASK-020 "Dependencies: TASK-021"
            ['TASK-020', 'TASK-026', 'relates_to'], // TASK-020 "Dependencies: TASK-033" (resolved to TASK-026)
            ['TASK-021', 'TASK-026', 'relates_to'], // TASK-021 "Dependencies: TASK-033" (resolved to TASK-026)
            ['TASK-021', 'TASK-046', 'relates_to'], // TASK-021 "Dependencies: TASK-046"
            ['TASK-022', 'TASK-020', 'relates_to'], // TASK-022 "Dependencies: TASK-020"
            ['TASK-022', 'TASK-021', 'relates_to'], // TASK-022 "Dependencies: TASK-021"
            ['TASK-022', 'TASK-026', 'relates_to'], // TASK-022 "Dependencies: TASK-033" (resolved to TASK-026)
            ['TASK-023', 'TASK-024', 'relates_to'], // TASK-023 "Dependencies: TASK-024"
            ['TASK-023', 'TASK-026', 'relates_to'], // TASK-023 "Dependencies: TASK-033" (resolved to TASK-026)
            ['TASK-024', 'TASK-021', 'relates_to'], // TASK-024 "Dependencies: TASK-021"
            ['TASK-024', 'TASK-046', 'relates_to'], // TASK-024 "Dependencies: TASK-046"
            ['TASK-026', 'TASK-025', 'blocked_by'], // TASK-025 "Dependencies: Blocks TASK-026"
            ['TASK-016', 'TASK-025', 'blocked_by'], // TASK-025 "Dependencies: Blocks TASK-016"
            ['TASK-022', 'TASK-025', 'blocked_by'], // TASK-025 "Dependencies: Blocks TASK-022"
            ['TASK-026', 'TASK-027', 'relates_to'], // TASK-026 "Dependencies: TASK-027"
            ['TASK-028', 'TASK-026', 'relates_to'], // TASK-028 "Dependencies: TASK-026"
            ['TASK-029', 'TASK-028', 'relates_to'], // TASK-029 "Dependencies: TASK-028"
            ['TASK-030', 'TASK-031', 'relates_to'], // TASK-030 "Dependencies: TASK-031"
            ['TASK-030', 'TASK-032', 'relates_to'], // TASK-030 "Dependencies: TASK-032"
            ['TASK-031', 'TASK-032', 'relates_to'], // TASK-031 "Dependencies: TASK-032"
            ['TASK-032', 'TASK-035', 'relates_to'], // TASK-032 "Dependencies: TASK-035"
            ['TASK-033', 'TASK-009', 'relates_to'], // TASK-033 "Dependencies: TASK-009 (same terminology sweep)"
            ['TASK-034', 'TASK-032', 'relates_to'], // TASK-034 "Dependencies: TASK-032"
            ['TASK-034', 'TASK-035', 'relates_to'], // TASK-034 "Dependencies: TASK-035"
            ['TASK-034', 'TASK-036', 'relates_to'], // TASK-034 "Dependencies: TASK-036"
            ['TASK-035', 'TASK-036', 'relates_to'], // TASK-035 "Dependencies: TASK-036"
            ['TASK-038', 'TASK-039', 'relates_to'], // TASK-038 "Dependencies: TASK-039"
            ['TASK-038', 'TASK-046', 'relates_to'], // TASK-038 "Dependencies: TASK-046"
            ['TASK-041', 'TASK-070', 'relates_to'], // TASK-041 "Dependencies: TASK-070"
            ['TASK-042', 'TASK-043', 'relates_to'], // TASK-042 "Dependencies: TASK-043"
            ['TASK-043', 'TASK-044', 'relates_to'], // TASK-043 "Dependencies: TASK-044"
            ['TASK-044', 'TASK-085', 'relates_to'], // TASK-044 "Dependencies: TASK-085"
            ['TASK-045', 'TASK-070', 'relates_to'], // TASK-045 "Dependencies: TASK-070"
            ['TASK-045', 'TASK-085', 'relates_to'], // TASK-045 "Dependencies: TASK-085"
            ['TASK-046', 'TASK-049', 'relates_to'], // TASK-046 "Dependencies: TASK-049"
            ['TASK-046', 'TASK-007', 'relates_to'], // TASK-046 "Dependencies: TASK-007"
            ['TASK-047', 'TASK-024', 'relates_to'], // TASK-047 "Dependencies: TASK-024"
            ['TASK-047', 'TASK-046', 'relates_to'], // TASK-047 "Dependencies: TASK-046"
            ['TASK-048', 'TASK-024', 'relates_to'], // TASK-048 "Dependencies: TASK-024"
            ['TASK-048', 'TASK-019', 'relates_to'], // TASK-048 "Dependencies: TASK-019"
            ['TASK-050', 'TASK-025', 'blocked_by'], // TASK-050 "Dependencies: TASK-025 (blocker)"
            ['TASK-050', 'TASK-026', 'relates_to'], // TASK-050 "Dependencies: TASK-026"
            ['TASK-051', 'TASK-014', 'relates_to'], // TASK-051 "Dependencies: TASK-014"
            ['TASK-052', 'TASK-053', 'relates_to'], // TASK-052 "Dependencies: TASK-053"
            ['TASK-052', 'TASK-054', 'relates_to'], // TASK-052 "Dependencies: TASK-054"
            ['TASK-053', 'TASK-054', 'relates_to'], // TASK-053 "Dependencies: TASK-054"
            ['TASK-056', 'TASK-057', 'relates_to'], // TASK-056 "Dependencies: TASK-057"
            ['TASK-056', 'TASK-058', 'relates_to'], // TASK-056 "Dependencies: TASK-058"
            ['TASK-058', 'TASK-024', 'relates_to'], // TASK-058 "Dependencies: TASK-024"
            ['TASK-059', 'TASK-053', 'relates_to'], // TASK-059 "Dependencies: TASK-053"
            ['TASK-059', 'TASK-055', 'relates_to'], // TASK-059 "Dependencies: TASK-055"
            ['TASK-060', 'TASK-061', 'relates_to'], // TASK-060 "Dependencies: TASK-061"
            ['TASK-061', 'TASK-062', 'relates_to'], // TASK-061 "Dependencies: TASK-062"
            ['TASK-062', 'TASK-063', 'relates_to'], // TASK-062 "Dependencies: TASK-063"
            ['TASK-009', 'TASK-063', 'relates_to'], // TASK-063 "Dependencies: TASK-009" (stored on TASK-009: the other direction would close a relates_to cycle)
            ['TASK-064', 'TASK-062', 'relates_to'], // TASK-064 "Dependencies: TASK-062"
            ['TASK-065', 'TASK-066', 'relates_to'], // TASK-065 "Dependencies: TASK-066"
            ['TASK-065', 'TASK-009', 'relates_to'], // TASK-065 "Dependencies: TASK-009"
            ['TASK-067', 'TASK-041', 'relates_to'], // TASK-067 "Dependencies: TASK-041"
            ['TASK-067', 'TASK-073', 'relates_to'], // TASK-067 "Dependencies: TASK-073"
            ['TASK-068', 'TASK-053', 'relates_to'], // TASK-068 "Dependencies: TASK-053"
            ['TASK-074', 'TASK-064', 'relates_to'], // TASK-074 "Dependencies: TASK-064"
            ['TASK-075', 'TASK-044', 'relates_to'], // TASK-075 "Dependencies: TASK-044"
            ['TASK-075', 'TASK-030', 'relates_to'], // TASK-075 "Dependencies: TASK-030"
            ['TASK-076', 'TASK-006', 'relates_to'], // TASK-076 "Dependencies: TASK-006"
            ['TASK-077', 'TASK-065', 'relates_to'], // TASK-077 "Dependencies: TASK-065"
            ['TASK-078', 'TASK-034', 'relates_to'], // TASK-078 "Dependencies: TASK-034"
            ['TASK-078', 'TASK-035', 'relates_to'], // TASK-078 "Dependencies: TASK-035"
            ['TASK-078', 'TASK-055', 'relates_to'], // TASK-078 "Dependencies: TASK-055"
            ['TASK-079', 'TASK-006', 'relates_to'], // TASK-079 "Dependencies: TASK-006"
            ['TASK-079', 'TASK-080', 'relates_to'], // TASK-079 "Dependencies: TASK-080"
            ['TASK-081', 'TASK-078', 'relates_to'], // TASK-081 "Dependencies: TASK-078"
            ['TASK-082', 'TASK-081', 'relates_to'], // TASK-082 "Dependencies: TASK-081"
            ['TASK-084', 'TASK-070', 'relates_to'], // TASK-084 "Dependencies: TASK-070"
            ['TASK-084', 'TASK-042', 'relates_to'], // TASK-084 "Dependencies: TASK-042"
            ['TASK-025', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 1 (UNICEF: Finalized report format per the Rule Book (agreed 13 Jul))
            ['TASK-026', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 1 (UNICEF: Finalized report format per the Rule Book (agreed 13 Jul))
            ['TASK-050', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 1 (UNICEF: Finalized report format per the Rule Book (agreed 13 Jul))
            ['TASK-016', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 3 (UNICEF: Legal reference definitions for footnotes + Std 7 citation)
            ['TASK-032', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 4 (UNICEF: Certificate design)
            ['TASK-041', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 5 (UNICEF: Landing page hero title (EN/BN))
            ['TASK-042', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 6 (UNICEF: Replacement name for the "Story" menu)
            ['TASK-043', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 7 (UNICEF: Final Story category list)
            ['TASK-067', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 8 (UNICEF: Updated content and achievement figures)
            ['TASK-031', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 9 (UNICEF: Content review sign-off before launch)
            ['TASK-008', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 10 (UNICEF: Supreme Admin nomination)
            ['TASK-056', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 11 (BGMEA / BKMEA: API specifications (SRS assumption, 2 weeks from start))
            ['TASK-057', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 12 (DIFE: Endpoint specification and credentials)
            ['TASK-030', 'TASK-085', 'blocked_by'], // TASK-085 client-blocker table row 13 (UNICEF: E-learning module content (SRS §2.6.1 assumption))
        ];
    }

    /**
     * The full parsed register, one entry per `# MODULE` of
     * `Mother-At-Work_TASKS.md`, in source order.
     *
     * @return array<int, array{module_num: int, name: string, description: string, status: string, priority: string, scopes: array<int, string>, tasks: array<int, array{source_task_id: string, number: int, title: string, description: string, source_status: string, status: string, priority: string, task_type: string, labels: array<int, string>}>}>
     */
    private function modules(): array
    {
        return [
            [
                'module_num' => 1,
                'name' => '01 — Authentication & User Management',
                'description' => <<<'MAW_MODULE_01'
**Source Module:** MODULE 1 — Authentication & User Management (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-001, TASK-002, TASK-003, TASK-004, TASK-005, TASK-006 (6)
**Requirement Sources:** REV-SEP-014 · COM-001 · CR-007 · BUG-001 · COM-003 · BUG-002 · REVIEW-001 · CR-005 · COM-004 · COM-005 · COM-006
**Original Status Breakdown:** Not Implemented: 2 · Bug (latent — only triggers once registration is enabled): 1 · Partially Implemented / Bug: 1 · Partially Implemented: 2

**Related Conflicts:**
- CONFLICT-03 — LMS open to all vs UNICEF approval of all content

**Tasks:**
- TASK-001 — Enable public self-registration for LMS access (Not Implemented, High)
- TASK-002 — BUG: `Fortify::registerView` points at a non-existent Vue page (Bug (latent — only triggers once registration is enabled), Medium)
- TASK-003 — Password complexity is enforced client-side only (Partially Implemented / Bug, Critical)
- TASK-004 — MFA is available but never mandatory for administrators (Partially Implemented, Critical)
- TASK-005 — Bulk user import via Excel/CSV template (Not Implemented, Medium)
- TASK-006 — Access-attempt logging (logins, IP, device, exports) (Partially Implemented, High)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 1 — Authentication & User Management. See that file in the repository root for the complete source analysis._
MAW_MODULE_01,
                'status' => 'in_progress',
                'priority' => 'critical',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-001',
                        'number' => 1,
                        'title' => 'Enable public self-registration for LMS access',
                        'description' => <<<'MAW_TASK_001'
**Source Task ID:** TASK-001 (`Mother-At-Work_TASKS.md`, MODULE 1 — Authentication & User Management)
**Source Requirement ID(s):** REV-SEP-014 · COM-001 · CR-007
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-02 SRS · DOC-01 TOR
**Original Status:** Not Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** To Do · High · Feature

- **Module:** Authentication & User Management
- **Type:** Implementation
- **Source:** REV-SEP-014 (primary) · COM-001 (SRS FR-UM-001/003) · CR-007
- **Classification:** Review / Change — reactivates a commitment that was never delivered
- **Priority:** High
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"Need to make the LMS Open for All - publicly accessable - through self
  registration."* SRS FR-UM-001 commits to registering users with organization, contact, address and
  worker count; FR-UM-003 commits to a Pending Approval → approve/reject workflow with email
  notification.
- **Current Implementation:** Registration is **disabled**. `config/fortify.php` `features` contains
  only `resetPasswords()`, `emailVerification()` and `twoFactorAuthentication()` —
  `Features::registration()` is absent, so Fortify never registers `GET/POST /register`.
  `FortifyServiceProvider` declares `Fortify::registerView(fn() => Inertia::render('auth/Register'))`
  and passes `'canRegister' => Features::enabled(Features::registration())` (always `false`) to the
  login page. All four guards are seeded/admin-created only.
- **Gap:** No public registration route, no registration form component, no approval queue for
  self-registered learners, no decision on which guard/role a self-registered learner receives.
- **Evidence:**
  - `config/fortify.php:146-153` — features array without `Features::registration()`
  - `app/Providers/FortifyServiceProvider.php:146` — `canRegister` flag
  - `app/Providers/FortifyServiceProvider.php:163` — `registerView` → `auth/Register`
  - `resources/js/pages/auth/` — contains Login, ForgotPassword, ResetPassword, ConfirmPassword,
    TwoFactorChallenge, VerifyEmail, VerifyOtp — **no `Register.vue`**
  - `routes/web.php` — no registration route
- **Dependencies:** TASK-002 (broken register view), TASK-025 (public LMS), TASK-003 (approval flow)
- **Acceptance Criteria:**
  - [ ] Self-registration reachable without authentication and linked from the LMS entry point
  - [ ] Captured fields satisfy SRS FR-UM-001, with FR-UM-002 validation (unique email, BD phone format)
  - [ ] New self-registrations land in a Pending Approval state and cannot access restricted data
  - [ ] Approve/reject notifies the applicant by email, with a reason on rejection
  - [ ] UNICEF confirms which role a self-registered learner receives

#### Source Traceability

- Matrix COM-001: User registration with approval workflow (SRS FR-UM-001/003) — Not Implemented — `Features::registration()` absent
- Matrix REV-SEP-014: Public LMS + self registration (Sep 17) — Not Implemented — Registration disabled
- CONFLICT-03: LMS open to all vs UNICEF approval of all content — clarification: Confirm the approval workflow (TASK-031) must ship *before* public access (TASK-030).
- Open question Q-09: What role and permission set does a self-registered public learner receive?
- Recommended sequence: Phase 4 — Public LMS (ordered — approval before opening)
- Dependency `TASK-025 (public LMS)` linked to TASK-030: "(public LMS)" is TASK-030; TASK-025 is the reporting blocker
- Dependency `TASK-003 (approval flow)` not linked: "(approval flow)": TASK-003 is password complexity and no register task is a self-registration approval flow
MAW_TASK_001,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 17 Sep', 'Source: SRS Commitment', 'Source: Client Requirement (ToR)'],
                    ],
                    [
                        'source_task_id' => 'TASK-002',
                        'number' => 2,
                        'title' => 'BUG: `Fortify::registerView` points at a non-existent Vue page',
                        'description' => <<<'MAW_TASK_002'
**Source Task ID:** TASK-002 (`Mother-At-Work_TASKS.md`, MODULE 1 — Authentication & User Management)
**Source Requirement ID(s):** BUG-001 · REV-SEP-014
**Source Document(s):** Repository review (BUG) · DOC-06 Meeting Minutes 17 Sep
**Original Status:** Bug (latent — only triggers once registration is enabled) · **Original Priority:** Medium · **Original Type:** Bug
**Imported As:** To Do · Medium · Bug

- **Module:** Authentication & User Management
- **Type:** Bug
- **Source:** BUG-001 (found during review of REV-SEP-014)
- **Classification:** Bug / Defect
- **Priority:** Medium
- **Status:** **Bug** (latent — only triggers once registration is enabled)
- **Requirement:** The registration view callback must resolve to a real Inertia page.
- **Current Implementation:** `Fortify::registerView(fn() => Inertia::render('auth/Register'))`
  renders `./pages/auth/Register.vue`, which does not exist. Inertia's `resolvePageComponent` throws
  on an unmatched glob, so the page would 500 the moment `Features::registration()` is enabled.
- **Gap:** Missing `resources/js/pages/auth/Register.vue`.
- **Evidence:**
  - `app/Providers/FortifyServiceProvider.php:163`
  - `resources/js/app.ts:31-36` — `resolvePageComponent('./pages/${name}.vue', import.meta.glob(...))`
  - `resources/js/pages/auth/` directory listing — no `Register.vue`
- **Dependencies:** Blocks TASK-001
- **Acceptance Criteria:**
  - [ ] `auth/Register.vue` exists and renders the SRS FR-UM-001 field set
  - [ ] Enabling `Features::registration()` produces a working page, not a 500

#### Source Traceability

- Recommended sequence: Phase 4 — Public LMS (ordered — approval before opening)
MAW_TASK_002,
                        'source_status' => 'Bug (latent — only triggers once registration is enabled)',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Defect', 'Source: Review 17 Sep'],
                    ],
                    [
                        'source_task_id' => 'TASK-003',
                        'number' => 3,
                        'title' => 'Password complexity is enforced client-side only',
                        'description' => <<<'MAW_TASK_003'
**Source Task ID:** TASK-003 (`Mother-At-Work_TASKS.md`, MODULE 1 — Authentication & User Management)
**Source Requirement ID(s):** COM-003 · BUG-002 · REVIEW-001
**Source Document(s):** DOC-02 SRS · Repository review (BUG) · Repository review (REVIEW)
**Original Status:** Partially Implemented / Bug · **Original Priority:** Critical · **Original Type:** Bug / Security
**Imported As:** In Progress · Critical · Bug

- **Module:** Authentication & User Management
- **Type:** Bug / Security
- **Source:** COM-003 (SRS FR-UM-006) · BUG-002 · REVIEW-001
- **Classification:** Our Commitment — partially delivered, with a security gap
- **Priority:** **Critical**
- **Status:** **Partially Implemented** / **Bug**
- **Requirement:** SRS FR-UM-006: minimum 8 characters, at least one uppercase, one lowercase, one
  number, one special character; strength indicator; password expiry after 90 days (configurable).
- **Current Implementation:** The Vue composable enforces the full rule set in the browser
  (uppercase, lowercase, number, special character labels present). The backend validates with a bare
  `Password::default()`, and **no `Password::defaults()` customisation exists in any service
  provider** — so Laravel applies only `min:8`. A direct API/form POST bypassing the SPA accepts
  `password` (8 lowercase letters). No password-expiry field, migration or middleware exists.
- **Gap:** (a) server-side complexity rules absent; (b) 90-day expiry entirely absent.
- **Evidence:**
  - `app/Actions/Fortify/PasswordValidationRules.php:16` — `['required','string', Password::default(), 'confirmed']`
  - `app/Providers/` — grep for `Password::defaults` returns no match in `AppServiceProvider`,
    `AuthServiceProvider`, `FortifyServiceProvider`
  - `resources/js/composables/usePasswordValidation.ts:25,37` — uppercase and special-character rules
  - No migration in `database/migrations/` adds a password-age/expiry column
- **Dependencies:** TASK-004 (MFA), TASK-092 (security hardening phase)
- **Acceptance Criteria:**
  - [ ] `Password::defaults()` configured with `->mixedCase()->numbers()->symbols()->min(8)`
  - [ ] Server rejects a weak password submitted directly to the endpoint, not just in the SPA
  - [ ] Client and server rule sets are demonstrably identical
  - [ ] Password expiry implemented and configurable, or formally descoped in writing by UNICEF

#### Source Traceability

- Matrix COM-003: Password policy + 90-day expiry (SRS FR-UM-006) — Partially Implemented — Client-side only; `Password::default()`
- Recommended sequence: Phase 1 — Security & Critical Defects
- Dependency `TASK-092 (security hardening phase)` not linked: TASK-092 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_003,
                        'source_status' => 'Partially Implemented / Bug',
                        'status' => 'in_progress',
                        'priority' => 'critical',
                        'task_type' => 'Bug',
                        'labels' => ['Source: SRS Commitment', 'Source: Code Defect', 'Source: Code Review', 'Security'],
                    ],
                    [
                        'source_task_id' => 'TASK-004',
                        'number' => 4,
                        'title' => 'MFA is available but never mandatory for administrators',
                        'description' => <<<'MAW_TASK_004'
**Source Task ID:** TASK-004 (`Mother-At-Work_TASKS.md`, MODULE 1 — Authentication & User Management)
**Source Requirement ID(s):** CR-005 · COM-004
**Source Document(s):** DOC-01 TOR · DOC-02 SRS
**Original Status:** Partially Implemented · **Original Priority:** Critical · **Original Type:** Implementation / Security
**Imported As:** In Progress · Critical · Feature

- **Module:** Authentication & User Management
- **Type:** Implementation / Security
- **Source:** CR-005 (TOR §3b) · COM-004 (SRS FR-UM-007, NFR-SEC-002)
- **Classification:** Client Requirement + Our Commitment
- **Priority:** **Critical**
- **Status:** **Partially Implemented**
- **Requirement:** TOR §3(b) requires multi-factor authentication as part of DPIA-aligned data
  security. SRS FR-UM-007 and NFR-SEC-002: MFA **optional for regular users, mandatory for
  administrators and coordinators**.
- **Current Implementation:** The capability exists — Fortify `twoFactorAuthentication` with
  `'confirm' => true`, a TOTP flow (`TwoFactorChallenge.vue`, `TwoFactorSetupModal.vue`,
  `TwoFactorRecoveryCodes.vue`, `useTwoFactorAuth.ts`), a separate OTP layer
  (`OtpService`, `HandleOtpRedirect`, `SendOtpEmail`/`SendOtpSms` jobs, `otp_codes` table) and
  per-guard capability declarations in `config/kaz.php` (`admin`, `web`, `association` → `two_factor
  => true`; `stakeholder` → `false`). **However:** `config/kaz.php` gates the whole feature behind
  `TWO_FACTOR_ENABLED` (default `false`) and `OTP_ENABLED` (default `false`), and **no middleware
  enforces enrolment** — no route group requires a confirmed second factor before administrative
  access.
- **Gap:** Enforcement. An administrator can hold full access with 2FA never enrolled.
- **Evidence:**
  - `config/fortify.php:149-153` — `Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])`
  - `config/kaz.php` — `two_factor.enabled` → `env('TWO_FACTOR_ENABLED', false)`; `otp.enabled` → `env('OTP_ENABLED', false)`; `guards.*.two_factor`
  - `app/Http/Middleware/` — 7 middleware, none enforcing two-factor enrolment
  - `routes/dashboard.php:56` — dashboard gate is `auth:web,admin,association,stakeholder` + `HandleOtpRedirect` only
  - `tests/Feature/Settings/TwoFactorAuthenticationTest.php`, `tests/Feature/Auth/TwoFactorChallengeTest.php` — cover the flow, not the mandate
- **Dependencies:** TASK-003, TASK-092
- **Acceptance Criteria:**
  - [ ] `TWO_FACTOR_ENABLED=true` in staging and production
  - [ ] Middleware forces Super Admin / Admin / Association coordinators to complete enrolment before reaching the dashboard
  - [ ] Regular users may opt in, per FR-UM-007
  - [ ] A feature test proves an un-enrolled admin is redirected to enrolment

#### Source Traceability

- Matrix CR-005: DPIA, encryption, RBAC, MFA, backup (TOR §3b) — Partially Implemented — RBAC + backup present; MFA unenforced; no DPIA
- Matrix COM-004: MFA mandatory for administrators (SRS FR-UM-007) — Partially Implemented — Fortify 2FA present; unenforced
- Recommended sequence: Phase 1 — Security & Critical Defects
- Dependency `TASK-092` not linked: TASK-092 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_004,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'critical',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Security'],
                    ],
                    [
                        'source_task_id' => 'TASK-005',
                        'number' => 5,
                        'title' => 'Bulk user import via Excel/CSV template',
                        'description' => <<<'MAW_TASK_005'
**Source Task ID:** TASK-005 (`Mother-At-Work_TASKS.md`, MODULE 1 — Authentication & User Management)
**Source Requirement ID(s):** COM-005
**Source Document(s):** DOC-02 SRS
**Original Status:** Not Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** To Do · Medium · Feature

- **Module:** Authentication & User Management
- **Type:** Implementation
- **Source:** COM-005 (SRS FR-UM-004)
- **Classification:** **Our Commitment — COMMITMENT EXTENSION** (no equivalent TOR clause)
- **Priority:** Medium
- **Status:** **Not Implemented**
- **Requirement:** SRS FR-UM-004: *"The system SHALL support bulk user import via Excel/CSV template."*
- **Current Implementation:** Export is comprehensively built (8 export classes, `ExportService`,
  `ExportableHeaders` trait, per-report `can:*_export` permissions). The inverse does not exist —
  there is no import class, no upload-and-map UI, no validation/dry-run path. `maatwebsite/excel`
  is installed and would support it.
- **Gap:** Entire import feature.
- **Evidence:**
  - `app/Exports/` — 8 export classes, no `app/Imports/` directory
  - Repository-wide search for `ToModel`, `WithHeadingRow`, `Importable`, `Excel::import` returns no
    application matches
  - `composer.json` — `maatwebsite/excel ^3.1` present
- **Dependencies:** TASK-057 (sample user database, REV-JUN)
- **Acceptance Criteria:**
  - [ ] Downloadable template per user type (admin, association, factory, stakeholder)
  - [ ] Upload validates rows and reports per-row errors without partial commits
  - [ ] Duplicate email/phone rejected per FR-UM-002
  - [ ] Import is audit-logged

#### Source Traceability

- Matrix COM-005: Bulk user import (SRS FR-UM-004) — Not Implemented — No `app/Imports/`
- Recommended sequence: Phase 6 — Outstanding Commitments
- Dependency `TASK-057 (sample user database, REV-JUN)` linked to TASK-069: "(sample user database, REV-JUN)" is TASK-069; TASK-057 is DIFE data provision
MAW_TASK_005,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: SRS Commitment', 'Commitment Extension'],
                    ],
                    [
                        'source_task_id' => 'TASK-006',
                        'number' => 6,
                        'title' => 'Access-attempt logging (logins, IP, device, exports)',
                        'description' => <<<'MAW_TASK_006'
**Source Task ID:** TASK-006 (`Mother-At-Work_TASKS.md`, MODULE 1 — Authentication & User Management)
**Source Requirement ID(s):** COM-006 · CR-005
**Source Document(s):** DOC-02 SRS · DOC-01 TOR
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** In Progress · High · Feature

- **Module:** Authentication & User Management / Security
- **Type:** Implementation
- **Source:** COM-006 (SRS FR-UM-011) · CR-005
- **Classification:** Our Commitment
- **Priority:** High
- **Status:** **Partially Implemented**
- **Requirement:** SRS FR-UM-011: log successful **and failed** login attempts, IP address and device
  information, user actions (view/create/edit/delete), **export activities**, and role/permission
  changes.
- **Current Implementation:** `owen-it/laravel-auditing` is wired in (`audits` table, `UsesAuditing`
  trait, `UrlResolver`, dashboard audit browser at `/dashboard/audit` behind `can:audit_access`).
  This captures model CRUD. It does **not** capture failed logins, successful logins as such, device
  fingerprints, or export events. `ua-parser-js` is a frontend dependency only.
- **Gap:** Failed-login capture, login/logout events, device information, export-activity logging.
- **Evidence:**
  - `database/migrations/2025_12_03_131928_create_audits_table.php`
  - `app/Traits/UsesAuditing.php`, `app/AuditResolvers/UrlResolver.php`, `app/Services/AuditService.php`
  - `routes/dashboard.php` — audit prefix with `can:audit_access`
  - `app/Models/Audit.php`
  - No listener on `Illuminate\Auth\Events\Failed` / `Login` / `Logout` anywhere in `app/`
- **Dependencies:** TASK-091 (audit retention)
- **Acceptance Criteria:**
  - [ ] Failed and successful authentication events recorded with IP and user agent
  - [ ] Every report export writes an audit entry naming actor, report and filters
  - [ ] Role/permission changes appear in the audit browser
  - [ ] Audit log is filterable and exportable (SRS FR-AD-004)

#### Source Traceability

- Matrix COM-006: Access-attempt logging (SRS FR-UM-011) — Partially Implemented — Model auditing only; no login/export events
- Recommended sequence: Phase 6 — Outstanding Commitments
- Dependency `TASK-091 (audit retention)` not linked: TASK-091 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_006,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: SRS Commitment', 'Source: Client Requirement (ToR)', 'Security'],
                    ],
                ],
            ],
            [
                'module_num' => 2,
                'name' => '02 — Roles & Permissions',
                'description' => <<<'MAW_MODULE_02'
**Source Module:** MODULE 2 — Roles & Permissions (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-007, TASK-008 (2)
**Requirement Sources:** COM-007 · CR-002 · REV-SEP-020
**Original Status Breakdown:** Partially Implemented — Needs Verification: 1 · Not Implemented (blocked on client): 1

**Tasks:**
- TASK-007 — Align role set with the six committed user classes (Partially Implemented — Needs Verification, Medium)
- TASK-008 — Decide and assign the Supreme Admin (Not Implemented (blocked on client), Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 2 — Roles & Permissions. See that file in the repository root for the complete source analysis._
MAW_MODULE_02,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scopes' => ['Admin Panel'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-007',
                        'number' => 7,
                        'title' => 'Align role set with the six committed user classes',
                        'description' => <<<'MAW_TASK_007'
**Source Task ID:** TASK-007 (`Mother-At-Work_TASKS.md`, MODULE 2 — Roles & Permissions)
**Source Requirement ID(s):** COM-007 · CR-002
**Source Document(s):** DOC-02 SRS · DOC-01 TOR
**Original Status:** Partially Implemented — Needs Verification · **Original Priority:** Medium · **Original Type:** Implementation / Review
**Imported As:** In Progress · Medium · Feature

- **Module:** Roles & Permissions
- **Type:** Implementation / Review
- **Source:** COM-007 (SRS FR-UM-009, §2.3 User Classes) · CR-002
- **Classification:** Our Commitment
- **Priority:** Medium
- **Status:** **Partially Implemented** — **Needs Verification**
- **Requirement:** SRS FR-UM-009 commits to six roles with defined boundaries: System Administrator,
  UNICEF Coordinator, BGMEA/BKMEA Coordinator, Government/Third-Party Officer (read-only aggregate),
  Factory Manager, **Field Advisor/Auditor** (assigned factories only; may collect data; *cannot
  modify factory scorecards*).
- **Current Implementation:** Six roles exist but map only approximately:
  `Super Admin`, `Admin`, `Association`, `Industry`, `Manager`, `Guest`
  (`RoleService` constants). `Association` ≈ BGMEA/BKMEA Coordinator, `Guest` ≈ Government officer,
  `Industry`/`Manager` ≈ Factory Manager. There is **no distinct Field Advisor/Auditor role**, and no
  role carries the "cannot modify scorecards" restriction. Association-scoped data isolation *is*
  implemented via `AssociationTypeScope` and `FiltersByAssociationType`, satisfying the SRS
  "cannot access competitor association data" rule.
- **Gap:** Missing Field Advisor/Auditor role and its scorecard-write restriction; UNICEF Coordinator
  is not distinguished from generic `Admin`.
- **Evidence:**
  - `app/Services/RoleService.php:22-46` — the six role constants
  - `app/Support/AssociationTypeScope.php`, `app/Traits/FiltersByAssociationType.php`
  - `tests/Unit/Support/AssociationTypeScopeTest.php`
  - `app/Enums/Permissions.php`, `database/seeders/RoleHasPermissionsTableSeeder.php`
  - 15 policies in `app/Policies/`
- **Dependencies:** TASK-008, TASK-050
- **Acceptance Criteria:**
  - [ ] UNICEF confirms whether the six built roles are accepted as the delivered mapping
  - [ ] If not: Field Advisor/Auditor role added with assigned-factory scope and no scorecard write
  - [ ] Role-to-user-class mapping documented in the user manual roles matrix

#### Source Traceability

- Matrix COM-007: RBAC with six roles (SRS FR-UM-009) — Partially Implemented — 6 roles; no Field Advisor
MAW_TASK_007,
                        'source_status' => 'Partially Implemented — Needs Verification',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: SRS Commitment', 'Source: Client Requirement (ToR)', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-008',
                        'number' => 8,
                        'title' => 'Decide and assign the Supreme Admin',
                        'description' => <<<'MAW_TASK_008'
**Source Task ID:** TASK-008 (`Mother-At-Work_TASKS.md`, MODULE 2 — Roles & Permissions)
**Source Requirement ID(s):** REV-SEP-020
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented (blocked on client) · **Original Priority:** Medium · **Original Type:** Process / Configuration
**Imported As:** Blocked · Medium · Research

- **Module:** Roles & Permissions
- **Type:** Process / Configuration
- **Source:** REV-SEP-020
- **Classification:** Review / Change — **client decision, not a development task**
- **Priority:** Medium
- **Status:** **Not Implemented** (blocked on client)
- **Requirement:** Sep 17: *"Customer will decide who will be the Supreme Admin."*
- **Current Implementation:** The capability already exists — a `Super Admin` role
  (`RoleService::SUPER_ADMIN = 'Super Admin'`) is seeded and treated as an unrestricted principal
  across services. What is missing is the client's decision about **which person or organisation**
  holds it.
- **Gap:** No named holder; no documented handover procedure for the credential.
- **Evidence:**
  - `app/Services/RoleService.php:22` — `const SUPER_ADMIN = 'Super Admin'`
  - `app/Services/AssociationTaskService.php:37`, `app/Services/SupportService.php:23,83`,
    `app/Services/NoticeService.php:306` — Super Admin bypasses scoping
  - `app/Traits/Enum/PermissionAttributes.php:107,146,151` — `$isSuperAdmin` grants the full tree
  - `database/seeders/RolesTableSeeder.php`, `UserSeeder.php`
  - `config/user-manual.php:88` — `'super_admin_roles' => [RoleService::SUPER_ADMIN]`
- **Dependencies:** TASK-096 (handover)
- **Acceptance Criteria:**
  - [ ] UNICEF names the Supreme Admin holder in writing
  - [ ] Account provisioned with MFA mandatory (TASK-004)
  - [ ] Credential handover and rotation procedure documented

#### Source Traceability

- Matrix REV-SEP-020: Supreme Admin decision (Sep 17) — Not Implemented — Role exists; holder undecided
- Recommended sequence: Phase 2 — Escalate Blockers (parallel with Phase 1; no development)
- Dependency `TASK-096 (handover)` not linked: TASK-096 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_008,
                        'source_status' => 'Not Implemented (blocked on client)',
                        'status' => 'blocked',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Review 17 Sep'],
                    ],
                ],
            ],
            [
                'module_num' => 3,
                'name' => '03 — Factory (Industry) Management',
                'description' => <<<'MAW_MODULE_03'
**Source Module:** MODULE 3 — Factory (Industry) Management (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-009, TASK-010, TASK-011, TASK-012 (4)
**Requirement Sources:** REV-SEP-004 · REV-JUN-004 · REVIEW-002 · REV-JUN-005 · REV-JUN-006 · REV-JUN-013 · COM-008 · COM-009 · REVIEW-003
**Original Status Breakdown:** Partially Implemented: 2 · Conflict: 1 · Implemented: 1

**Related Conflicts:**
- CONFLICT-01 — "Industry" (June) vs "Factory" (September)

**Tasks:**
- TASK-009 — Complete the "Industry" → "Factory" terminology change (Partially Implemented, High)
- TASK-010 — CONFLICT: permission slugs renamed toward "industry" now contradict Sep 17 (Conflict, Low)
- TASK-011 — Factory master-data fields (Membership ID, BIN, workforce, onboarding) (Implemented, High)
- TASK-012 — Factory enrollment status is a boolean, not the committed lifecycle (Partially Implemented, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 3 — Factory (Industry) Management. See that file in the repository root for the complete source analysis._
MAW_MODULE_03,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-009',
                        'number' => 9,
                        'title' => 'Complete the "Industry" → "Factory" terminology change',
                        'description' => <<<'MAW_TASK_009'
**Source Task ID:** TASK-009 (`Mother-At-Work_TASKS.md`, MODULE 3 — Factory (Industry) Management)
**Source Requirement ID(s):** REV-SEP-004 · REV-JUN-004
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-04 Meeting Minutes 29 Jun
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** In Progress · High · Enhancement

- **Module:** Factory Management / UI
- **Type:** Enhancement
- **Source:** REV-SEP-004 · supersedes REV-JUN-004
- **Classification:** Review / Change — **supersedes an earlier review decision**
- **Priority:** High
- **Status:** **Partially Implemented**

#### Requirement Evolution

- **REV-JUN-004** (29 Jun) — *"Replace 'RMG' with 'Industry'."* Implemented: the domain was named
  `Industry` throughout (table `industries`, `App\Models\Profile\Industry`, `RoleService::INDUSTRY`),
  and a migration explicitly renamed the earlier factory-flavoured permissions to industry ones.
- **REV-SEP-004** (17 Sep) — *"We will not use Industry, instead we will use Factory."*
  **This reverses REV-JUN-004 at the presentation layer.**

- **Requirement (current):** User-facing language must read "Factory" everywhere.
- **Current Implementation:** The **UI labels are already changed** — the dashboard sidebar reads
  `Factory`, `Factory User`, `Factory Registration`, `Factory Score`,
  `Factory Monitoring Progress`. The landing page achievement labels also read
  `"Total Factory Covered"`. The **data and code layer still says Industry**: table `industries`,
  model `App\Models\Profile\Industry`, `industry_id` FK on `monitorings` and
  `dynamic_form_quarter_user_assignments`, `RoleService::INDUSTRY = 'Industry'`, permissions
  `industry_registration_access` / `industry_score_access`, routes `reports/industry-registration`
  and `reports/industry-score`, services `IndustryService`, `IndustryScoreService`,
  `IndustryRegistrationService`, exports `IndustryScoreExport`, `IndustryRegistrationExport`.
- **Gap:** Residual "Industry" strings in user-visible surfaces that are **not** the sidebar —
  report page headings, table column headers, export file headers and sheet names, validation
  messages, notification copy, the user manual, and the `Industry` role name shown in role pickers.
  Renaming the schema is **not** required to satisfy the request and would be high-risk.
- **Evidence:**
  - `resources/js/components/dashboard/NavMain.vue:217,224,288,295,302` — Factory labels in place
  - `lang/en/frontend.home.json` — `"factories_covered": "Total Factory Covered"`
  - `database/migrations/2025_11_26_075606_create_industries_table.php` — table `industries`
  - `database/migrations/2026_08_03_142719_rename_factory_report_permissions_to_industry.php` — the June-era rename, now contradicted
  - `app/Services/RoleService.php:36` — `const INDUSTRY = 'Industry'`
  - `app/Exports/IndustryScoreExport.php`, `app/Exports/IndustryRegistrationExport.php`
  - `routes/dashboard.php:145,153` — `industry-registration`, `industry-score` route names
- **Dependencies:** TASK-010, TASK-076 (user manual), TASK-033 (report naming)
- **Acceptance Criteria:**
  - [ ] No user-visible "Industry" or "RMG" string remains in EN or BN (pages, tables, exports, emails, notifications, validation messages)
  - [ ] Export column headings and worksheet titles read "Factory"
  - [ ] The `Industry` **role** display name is decided — renamed to "Factory" or explicitly kept
  - [ ] Internal identifiers (table, model, FK, route slugs) deliberately left unchanged, and that decision recorded
  - [ ] User manual regenerated with the new terminology

#### Source Traceability

- Matrix REV-JUN-004: Replace RMG with Industry (June 29) — Superseded — Reversed by REV-SEP-004
- Matrix REV-SEP-004: Industry → Factory (Sep 17) — Partially Implemented — Sidebar renamed; code/exports not
- CONFLICT-01: "Industry" (June) vs "Factory" (September) — clarification: Does "Factory" apply to user-visible text only, or must internal identifiers follow? Recommendation: presentation layer only.
- Open question Q-01: Does "Factory" replace "Industry" in user-visible text only, or must schema, routes and permissions follow?
- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
- Dependency `TASK-076 (user manual)` linked to TASK-062: "(user manual)" is TASK-062; TASK-076 is the data approval workflow
- Dependency `TASK-033 (report naming)` linked to TASK-027: "(report naming)" is TASK-027; TASK-033 is the Course Title rename
MAW_TASK_009,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'Source: Review 29 Jun'],
                    ],
                    [
                        'source_task_id' => 'TASK-010',
                        'number' => 10,
                        'title' => 'CONFLICT: permission slugs renamed toward "industry" now contradict Sep 17',
                        'description' => <<<'MAW_TASK_010'
**Source Task ID:** TASK-010 (`Mother-At-Work_TASKS.md`, MODULE 3 — Factory (Industry) Management)
**Source Requirement ID(s):** REVIEW-002 · REV-JUN-004 · REV-SEP-004
**Source Document(s):** Repository review (REVIEW) · DOC-04 Meeting Minutes 29 Jun · DOC-06 Meeting Minutes 17 Sep
**Original Status:** Conflict · **Original Priority:** Low · **Original Type:** Review
**Imported As:** Blocked · Low · Maintenance

- **Module:** Factory Management
- **Type:** Review
- **Source:** REVIEW-002 · REV-JUN-004 vs REV-SEP-004
- **Classification:** Review — conflict artifact
- **Priority:** Low
- **Status:** **Conflict**
- **Requirement:** Terminology must be internally consistent.
- **Current Implementation:** A dedicated migration renamed permissions *from* factory-named *to*
  industry-named in August, three months after June's instruction and six weeks before September
  reversed it. The permission strings are now the opposite of the current client vocabulary.
- **Gap:** Decide whether permission slugs follow the UI vocabulary. Renaming them again touches the
  permission seeder, every `can:` route guard, policies and the user-manual permission mapper —
  meaningful risk for zero user-visible benefit.
- **Evidence:**
  - `database/migrations/2026_08_03_142719_rename_factory_report_permissions_to_industry.php`
  - `routes/dashboard.php:145,153` — `can:industry_registration_access`, `can:industry_score_access`
  - `config/user-manual.php:89-110` — permission mapper keyed on the same slugs
- **Dependencies:** TASK-009
- **Acceptance Criteria:**
  - [ ] Written decision: permission slugs stay as internal identifiers (recommended) or are renamed
  - [ ] If renamed, permission seeder, route guards, policies and manual mapper all updated together, with a data migration for existing role assignments

#### Source Traceability

- CONFLICT-01: "Industry" (June) vs "Factory" (September) — clarification: Does "Factory" apply to user-visible text only, or must internal identifiers follow? Recommendation: presentation layer only.
- Open question Q-01: Does "Factory" replace "Industry" in user-visible text only, or must schema, routes and permissions follow?
MAW_TASK_010,
                        'source_status' => 'Conflict',
                        'status' => 'blocked',
                        'priority' => 'low',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Source: Review 29 Jun', 'Source: Review 17 Sep', 'Conflict'],
                    ],
                    [
                        'source_task_id' => 'TASK-011',
                        'number' => 11,
                        'title' => 'Factory master-data fields (Membership ID, BIN, workforce, onboarding)',
                        'description' => <<<'MAW_TASK_011'
**Source Task ID:** TASK-011 (`Mother-At-Work_TASKS.md`, MODULE 3 — Factory (Industry) Management)
**Source Requirement ID(s):** REV-JUN-005 · REV-JUN-006 · REV-JUN-013 · COM-008
**Source Document(s):** DOC-04 Meeting Minutes 29 Jun · DOC-02 SRS
**Original Status:** Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** Done · High · Feature

- **Module:** Factory Management
- **Type:** Implementation
- **Source:** REV-JUN-005, REV-JUN-006, REV-JUN-013 · COM-008 (SRS FR-UM-001, Appendix C Organization Data)
- **Classification:** Review / Change + Our Commitment
- **Priority:** High
- **Status:** **Implemented**
- **Requirement:** June 29: rename Registration Number → **Membership ID**; add **BIN Number**; extend
  the Industry module with **Date, Total Mother, Leaves, Breastfeeding Space**. SRS Appendix C
  additionally specifies name/name_bn, registration_no, email, phone, address, district, division,
  lat/lon, worker counts and status.
- **Current Implementation:** All requested fields exist and are constrained:
  `membership_id` (`char(60)`, unique per `association_type`), `bin_no` (`varchar(60)`, globally
  unique), plus `onboarding_date`, `total_male_workers`, `total_female_workers`, `total_mothers`,
  `leaves`, `breastfeeding_space`. Bilingual `name`/`bn_name` and `contact_person`/`bn_contact_person`,
  `latitude`/`longitude`, division/district FKs, `is_active`, soft deletes and timestamps are present.
  A follow-up migration safely split a legacy combined `total_male_female_worker` column into
  separate male/female counts with data preserved.
- **Gap:** None for the June scope. SRS `status` ENUM (`pending/active/suspended/certified`) is
  reduced to a boolean `is_active` — see TASK-012.
- **Evidence:**
  - `database/migrations/2025_11_26_075606_create_industries_table.php` — `bin_no` unique, `membership_id` with composite unique `['association_type','membership_id']`
  - `database/migrations/2026_07_06_200000_add_workforce_fields_to_industries_table.php` — onboarding_date, total_mothers, leaves, breastfeeding_space
  - `database/migrations/2026_07_10_120000_replace_total_male_female_worker_on_industries_table.php` — guarded split with data copy
  - `database/migrations/2025_12_08_103518_add_division_district_column_to_rmgs_table.php`
  - `app/Models/Profile/Industry.php`, `app/Services/IndustryService.php`
  - `tests/Feature/Dashboard/Reports/IndustryRegistrationControllerTest.php`
- **Acceptance Criteria:**
  - [x] Membership ID replaces Registration Number and is unique within an association type
  - [x] BIN number captured and globally unique
  - [x] Onboarding date, total mothers, leaves and breastfeeding space captured
  - [ ] Field labels read "Factory …" after TASK-009

#### Source Traceability

- Matrix REV-JUN-005: Registration No → Membership ID (June 29) — Implemented — `membership_id` column
- Matrix REV-JUN-006: Add BIN Number (June 29) — Implemented — `bin_no` unique column
- Matrix REV-JUN-015: Industry module fields (June 29) — Implemented — workforce migration
MAW_TASK_011,
                        'source_status' => 'Implemented',
                        'status' => 'done',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 29 Jun', 'Source: SRS Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-012',
                        'number' => 12,
                        'title' => 'Factory enrollment status is a boolean, not the committed lifecycle',
                        'description' => <<<'MAW_TASK_012'
**Source Task ID:** TASK-012 (`Mother-At-Work_TASKS.md`, MODULE 3 — Factory (Industry) Management)
**Source Requirement ID(s):** COM-009 · REVIEW-003
**Source Document(s):** DOC-02 SRS · Repository review (REVIEW)
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Review / Implementation
**Imported As:** In Progress · Medium · Enhancement

- **Module:** Factory Management
- **Type:** Review / Implementation
- **Source:** COM-009 (SRS Appendix C, FR-INT-003) · REVIEW-003
- **Classification:** Our Commitment
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** SRS Appendix C defines `status ENUM(pending, active, suspended, certified)` on the
  organization entity; FR-INT-003 commits to webhook notifications on *"Factory status changes
  (enrolled, certified, suspended)"*; FR-DA-001 commits to a National Dashboard tile showing
  *"Total factories enrolled, active, certified"*.
- **Current Implementation:** `industries.is_active` is a boolean. There is no `certified` or
  `suspended` state anywhere, so the committed dashboard tile and the webhook payload have no data
  source. Note the project *does* have a rich `Status` enum and an `Approvable` trait used for forms
  and monitoring — the pattern exists but was not applied to factories.
- **Gap:** Enrollment lifecycle states and their transitions.
- **Evidence:**
  - `database/migrations/2025_11_26_075606_create_industries_table.php` — `$table->boolean('is_active')->default(true)`
  - `app/Enums/Status.php`, `app/Traits/Approvable.php` — the unused-here pattern
  - `app/Models/Approval.php`, `database/migrations/2025_12_05_085427_create_approvals_table.php`
- **Dependencies:** TASK-046 (dashboard tiles), TASK-062 (webhooks)
- **Acceptance Criteria:**
  - [ ] UNICEF confirms the required lifecycle states and what "certified" means operationally
  - [ ] Status modelled as an enum with audited transitions
  - [ ] Dashboard "enrolled / active / certified" counts read real data

#### Source Traceability

- Recommended sequence: Phase 6 — Outstanding Commitments
- Dependency `TASK-062 (webhooks)` linked to TASK-058: "(webhooks)" is TASK-058; TASK-062 is the user manual
MAW_TASK_012,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: SRS Commitment', 'Source: Code Review'],
                    ],
                ],
            ],
            [
                'module_num' => 4,
                'name' => '04 — Monitoring & Assessment',
                'description' => <<<'MAW_MODULE_04'
**Source Module:** MODULE 4 — Monitoring & Assessment (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-013, TASK-014, TASK-015, TASK-016, TASK-017, TASK-018 (6)
**Requirement Sources:** CR-003 · COM-010 · COM-011 · REV-SEP-025 · REV-JUN-012 · REV-SEP-009 · REVIEW-004 · REV-SEP-026
**Original Status Breakdown:** Implemented: 2 · Partially Implemented — Needs Verification: 1 · Partially Implemented: 1 · Needs Verification: 2

**Tasks:**
- TASK-013 — Seven-standard monitoring checklist (Implemented, Critical)
- TASK-014 — Factory Attendee (Point of Contact) capture during monitoring (Implemented, High)
- TASK-015 — ECCD checklist (Partially Implemented — Needs Verification, Medium)
- TASK-016 — Legal reference numbers in reports, with footnote definitions (Partially Implemented, High)
- TASK-017 — REVIEW: legal reference stored in the `placeholder` column (Needs Verification, Low)
- TASK-018 — Monitoring preview language default (Needs Verification, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 4 — Monitoring & Assessment. See that file in the repository root for the complete source analysis._
MAW_MODULE_04,
                'status' => 'in_progress',
                'priority' => 'critical',
                'scopes' => ['Web Application', 'Mobile Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-013',
                        'number' => 13,
                        'title' => 'Seven-standard monitoring checklist',
                        'description' => <<<'MAW_TASK_013'
**Source Task ID:** TASK-013 (`Mother-At-Work_TASKS.md`, MODULE 4 — Monitoring & Assessment)
**Source Requirement ID(s):** CR-003 · COM-010 · COM-011
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Implemented · **Original Priority:** Critical · **Original Type:** Implementation
**Imported As:** Done · Critical · Feature

- **Module:** Monitoring & Assessment
- **Type:** Implementation
- **Source:** CR-003 (TOR §1, §3a) · COM-010 (SRS FR-DC-001) · COM-011 (Inception Module 3)
- **Classification:** **Client Requirement + Our Commitment** (COMBINED)
- **Priority:** Critical
- **Status:** **Implemented**
- **Requirement:** Digital forms covering all seven M@W minimum standards: Breastfeeding Spaces;
  Breastfeeding Breaks; Childcare Provision; Paid Maternity Leave; Cash and Medical Benefits;
  Employment Protection and Non-Discrimination; Safe Work Provision.
- **Current Implementation:** All seven standards are seeded as grouped sections of the assessment
  form *"Monitoring Implementation of Maternity Rights in the Workplace"*, each a `GROUP_INPUT`
  containing bilingual (EN/BN) yes/no questions with marks. Submissions are captured as
  `Monitoring` + `MonitoringItem` records with GPS (`lat`/`lon`), a date, a `type` (`FormType`),
  an approval `status`, quarter linkage and soft deletes.
- **Gap:** None for form coverage. Standard 7's legal reference is an empty stub — see TASK-016.
- **Evidence:**
  - `database/seeders/DynamicFormInputsTableSeeder.php:67,87,104,120,134,155,167` — the seven `Std. N` groups
  - `database/migrations/2025_11_26_121132_create_monitorings_table.php`, `..._create_monitoring_items_table.php`
  - `database/migrations/2026_07_17_000000_add_lat_lon_to_monitorings_table.php`
  - `app/Models/Monitoring/Monitoring.php:43-62` — fillable and casts
  - `app/Services/MonitoringService.php`, `app/Actions/Monitoring/`
  - `tests/Feature/Dashboard/Monitoring/MonitoringControllerTest.php`, `tests/Feature/Monitoring/FormBuilderActionsTest.php`
- **Acceptance Criteria:**
  - [x] All seven standards present with bilingual questions
  - [x] Submissions carry GPS, date and approval status
  - [x] Items scored via per-question marks

#### Source Traceability

- Matrix CR-003: Digitalize M@W resources and M&E tools (TOR §1, §3a) — Implemented — 7-standard checklist seeder; `monitorings`
- Matrix COM-010: Seven-standard digital forms (SRS FR-DC-001) — Implemented — Seeder groups `std-1`…`std-7`
MAW_TASK_013,
                        'source_status' => 'Implemented',
                        'status' => 'done',
                        'priority' => 'critical',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-014',
                        'number' => 14,
                        'title' => 'Factory Attendee (Point of Contact) capture during monitoring',
                        'description' => <<<'MAW_TASK_014'
**Source Task ID:** TASK-014 (`Mother-At-Work_TASKS.md`, MODULE 4 — Monitoring & Assessment)
**Source Requirement ID(s):** REV-SEP-025
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Implemented · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** Done · High · Enhancement

- **Module:** Monitoring & Assessment
- **Type:** Enhancement
- **Source:** REV-SEP-025
- **Classification:** **Review / Change — NEW REQUIREMENT** (no TOR/SRS/Inception antecedent)
- **Priority:** High
- **Status:** **Implemented**
- **Requirement:** Sep 17: *"On the Mobile App, while the BGMEA/BKMEA inspector goes to inspect a
  Factory, during his monitoring data collection he also needs to provide the PoC (Point of Contact)
  information like Name, Designation of that Factory - 'Factory Attendee'."*
- **Current Implementation:** Delivered end to end on 22 Sep. `factory_attendee_name` and
  `designation` were added to `monitorings`, and are wired through the model, both store and update
  actions, both form requests, the dashboard and API resources, the API controller contract, the
  monitoring list/show/edit Vue pages, the TypeScript types, search, and the sample-data seeder.
- **Gap:** None found. Confirm the Flutter client sends the two fields.
- **Evidence:**
  - `database/migrations/2026_09_22_113900_add_factory_attendee_fields_to_monitorings_table.php`
  - `app/Models/Monitoring/Monitoring.php:51` (fillable), `:165` (export header)
  - `app/Actions/Monitoring/StoreMonitoringAction.php:37`, `UpdateMonitoringAction.php:30,70`
  - `app/Http/Requests/Monitoring/MonitoringRequest.php:69-70,145-146`, `UpdateMonitoringRequest.php:29-30`
  - `app/Http/Resources/API/V1/MonitoringResource.php:32`, `app/Http/Resources/Monitoring/MonitoringResource.php:21`
  - `app/Http/Controllers/Api/Monitoring/MonitoringController.php:183` — documented in the API contract
  - `app/Services/MonitoringService.php:59` (searchable), `:175,:301`
  - `resources/js/pages/dashboard/monitoring/{Index,Show,Edit}.vue`, `resources/js/types/monitoring.ts:50`
- **Dependencies:** TASK-065 (mobile app release)
- **Acceptance Criteria:**
  - [x] Name and designation persisted against the monitoring visit
  - [x] Exposed on the mobile API and rendered in the dashboard
  - [ ] Flutter client updated to collect and submit both fields
  - [ ] UNICEF confirms whether the fields should be mandatory rather than nullable

#### Source Traceability

- Matrix REV-SEP-025: Factory Attendee (PoC) (Sep 17) — Implemented — Migration + full wiring, 22 Sep
- Dependency `TASK-065 (mobile app release)` not linked: "(mobile app release)": no such task in the register; TASK-065 is the colour palette
MAW_TASK_014,
                        'source_status' => 'Implemented',
                        'status' => 'done',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'New Requirement'],
                    ],
                    [
                        'source_task_id' => 'TASK-015',
                        'number' => 15,
                        'title' => 'ECCD checklist',
                        'description' => <<<'MAW_TASK_015'
**Source Task ID:** TASK-015 (`Mother-At-Work_TASKS.md`, MODULE 4 — Monitoring & Assessment)
**Source Requirement ID(s):** REV-JUN-012
**Source Document(s):** DOC-04 Meeting Minutes 29 Jun
**Original Status:** Partially Implemented — Needs Verification · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** In Progress · Medium · Feature

- **Module:** Monitoring & Assessment
- **Type:** Implementation
- **Source:** REV-JUN-012
- **Classification:** Review / Change
- **Priority:** Medium
- **Status:** **Partially Implemented** — **Needs Verification**
- **Requirement:** June 29: *"Implement ECCD Checklist."*
- **Current Implementation:** ECCD content exists, but **embedded inside Standard 3 (Workplace based
  Child-care provision)** rather than as a distinct checklist. Four seeded questions cover ECCD-trained
  care providers (0–36 m module, play-based learning, parenting guide), ECCD facilities for under-24-month
  children, play-based learning sessions, and ECCD awareness sessions with working parents.
- **Gap:** Unclear whether the client expected a standalone ECCD checklist/form with its own scoring
  and reporting, or exactly this embedding. No separate ECCD form, score or report exists.
- **Evidence:**
  - `database/seeders/DynamicFormInputsTableSeeder.php:111-114` — the four ECCD questions inside the `std-3` group
  - `database/seeders/DynamicFormInputsTableSeeder.php:104` — parent group `Std. 3 Workplace based Child-care provision`
  - No `dynamic_forms` row of a distinct ECCD type; repository-wide search finds ECCD only in this seeder
- **Dependencies:** TASK-033 (report set)
- **Acceptance Criteria:**
  - [ ] UNICEF confirms whether embedding in Standard 3 satisfies the request
  - [ ] If a standalone checklist is required: separate form, scoring and report output
  - [ ] ECCD indicators surfaced in at least one report

#### Source Traceability

- Matrix REV-JUN-012: ECCD checklist (June 29) — Partially Implemented — Embedded in Std 3
- Open question Q-07: Does embedding ECCD questions in Standard 3 satisfy the ECCD checklist request, or is a standalone checklist required?
- Dependency `TASK-033 (report set)` linked to TASK-026: "(report set)" is TASK-026; TASK-033 is the Course Title rename
MAW_TASK_015,
                        'source_status' => 'Partially Implemented — Needs Verification',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 29 Jun', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-016',
                        'number' => 16,
                        'title' => 'Legal reference numbers in reports, with footnote definitions',
                        'description' => <<<'MAW_TASK_016'
**Source Task ID:** TASK-016 (`Mother-At-Work_TASKS.md`, MODULE 4 — Monitoring & Assessment)
**Source Requirement ID(s):** REV-SEP-009
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** In Progress · High · Enhancement

- **Module:** Monitoring & Assessment / Reporting
- **Type:** Enhancement
- **Source:** REV-SEP-009
- **Classification:** **Review / Change — NEW REQUIREMENT**
- **Priority:** High
- **Status:** **Partially Implemented**
- **Requirement:** Sep 17: *"Need to Add the Legal Reference Number from the Monitoring Check list -
  like Std 5 has BLA 45(1,2,3), 46(1,2). Also need to add definitions of those Legal Reference as the
  footnote for that Report."*
- **Current Implementation:** The legal references **already exist on the checklist** — six of the
  seven standards carry a citation, stored bilingually. Standard 5 reads exactly as the client
  described: `Legal Reference: BLA 45(1,2,3) 46(1,2), BLR: 38, C111, C 183, R191`. Two gaps:
  **(a)** Standard 7's value is the bare stub `'Legal Reference'` with no citation;
  **(b)** no report renders these references, and **no definitions/glossary of the citations exists
  anywhere** in the codebase.
- **Gap:** Standard 7 citation data; surfacing references on report output; a definitions source for
  the footnotes (BLA, BLR, C183, C111, R191 …), which UNICEF must supply.
- **Evidence:**
  - `database/seeders/DynamicFormInputsTableSeeder.php:69,89,106,122,136,157` — the six populated citations
  - `database/seeders/DynamicFormInputsTableSeeder.php:169` — `legalEn: 'Legal Reference'` (Std 7, empty)
  - `database/seeders/DynamicFormInputsTableSeeder.php:213-230` — `group()` stores the citation in the **`placeholder`** column (see TASK-017)
  - `app/Exports/` — no export emits a legal reference; no footnote mechanism in any export or report view
- **Dependencies:** TASK-017, TASK-033 (7 reports), TASK-085 (UNICEF content)
- **Acceptance Criteria:**
  - [ ] Standard 7 citation supplied by UNICEF and seeded
  - [ ] Each standard's legal reference rendered on its report section
  - [ ] Definition footnotes rendered beneath the report, bilingual
  - [ ] References and definitions included in PDF and Excel exports

#### Source Traceability

- Matrix REV-SEP-009: Legal reference + footnotes (Sep 17) — Partially Implemented — In checklist, not in reports; Std 7 empty
- Recommended sequence: Phase 5 — Reporting (starts on receipt of the format)
- Dependency `TASK-033 (7 reports)` linked to TASK-026: "(7 reports)" is TASK-026; TASK-033 is the Course Title rename
MAW_TASK_016,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'New Requirement'],
                    ],
                    [
                        'source_task_id' => 'TASK-017',
                        'number' => 17,
                        'title' => 'REVIEW: legal reference stored in the `placeholder` column',
                        'description' => <<<'MAW_TASK_017'
**Source Task ID:** TASK-017 (`Mother-At-Work_TASKS.md`, MODULE 4 — Monitoring & Assessment)
**Source Requirement ID(s):** REVIEW-004
**Source Document(s):** Repository review (REVIEW)
**Original Status:** Needs Verification · **Original Priority:** Low · **Original Type:** Review
**Imported As:** To Do · Low · Maintenance

- **Module:** Monitoring & Assessment
- **Type:** Review
- **Source:** REVIEW-004
- **Classification:** Code / Data-model review
- **Priority:** Low
- **Status:** **Needs Verification**
- **Requirement:** Data should live in a field that means what it holds.
- **Current Implementation:** The checklist's legal citation is written into
  `dynamic_form_inputs.placeholder` — a column whose purpose elsewhere is genuine input placeholder
  text (yes/no questions use it for *"Select Yes or No"*). A group's legal reference is therefore
  indistinguishable, at the schema level, from UI hint text, which makes TASK-016 harder: a report
  cannot select "the legal reference" without hardcoding the assumption that group placeholders are
  citations.
- **Gap:** No dedicated `legal_reference` column, so the data cannot be queried or validated as such.
- **Evidence:**
  - `database/seeders/DynamicFormInputsTableSeeder.php:222-229` — `'placeholder' => $this->bilingual($legalEn, $legalBn)`
  - `database/seeders/DynamicFormInputsTableSeeder.php:243` — the same column used for *"Select Yes or No"* on questions
  - `database/migrations/2025_11_26_110309_create_dynamic_form_inputs_table.php`
  - Related: `database/migrations/2026_07_29_175910_add_standard_key_to_dynamic_form_inputs_table.php` — file named `add_standard_key` but the column it adds is `report_tag` (misleading migration name)
- **Dependencies:** TASK-016
- **Acceptance Criteria:**
  - [ ] Decision recorded: add a dedicated nullable `legal_reference` column, or formally accept the placeholder convention and document it
  - [ ] If added, existing seeded data migrated and the seeder updated
  - [ ] Migration filename/column mismatch noted in developer documentation
MAW_TASK_017,
                        'source_status' => 'Needs Verification',
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-018',
                        'number' => 18,
                        'title' => 'Monitoring preview language default',
                        'description' => <<<'MAW_TASK_018'
**Source Task ID:** TASK-018 (`Mother-At-Work_TASKS.md`, MODULE 4 — Monitoring & Assessment)
**Source Requirement ID(s):** REV-SEP-026
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Needs Verification · **Original Priority:** Medium · **Original Type:** Review / Clarification
**Imported As:** To Do · Medium · Research

- **Module:** Monitoring & Assessment / Localization
- **Type:** Review / Clarification
- **Source:** REV-SEP-026
- **Classification:** Review / Change — question raised by client
- **Priority:** Medium
- **Status:** **Needs Verification**
- **Requirement:** Sep 17 (posed as a question): *"Monitoring Preview Showing in Bangla, is it default
  in Bangla?"*
- **Current Implementation:** The application locale is driven by `SetLocale` middleware and a session
  value, with `APP_LOCALE=en` as the configured default and a `POST /locale` switcher restricted to
  `en`/`bn`. All checklist questions are seeded bilingually, and the display formatter resolves answers
  per locale. The client's observation suggests the preview surface is resolving to Bengali
  irrespective of the chosen locale — most plausibly by reading the Bengali half of the bilingual
  field rather than the active locale.
- **Gap:** Needs reproduction against a running instance to determine whether this is a defect or
  expected behaviour for a Bengali-locale session.
- **Evidence:**
  - `app/Http/Middleware/SetLocale.php`; `bootstrap/app.php:35-41` — middleware order
  - `routes/web.php` — `POST /locale` validates `in:en,bn`
  - `.env.example` — `APP_LOCALE=en`, `APP_FALLBACK_LOCALE=en`
  - `app/Support/Monitoring/MonitoringAnswerDisplayFormatter.php`, `tests/Unit/Support/Monitoring/MonitoringAnswerDisplayFormatterTest.php`
  - `app/Support/Form/BilingualFieldParser.php` (per `tests/Unit/Support/Form/BilingualFieldParserTest.php`)
- **Dependencies:** TASK-070 (localization)
- **Acceptance Criteria:**
  - [ ] Behaviour reproduced and classified as defect or intended
  - [ ] Monitoring preview follows the active locale in both EN and BN sessions
  - [ ] Regression test covering preview language selection

#### Source Traceability

- Matrix REV-SEP-026: Monitoring preview language (Sep 17) — Needs Verification — Requires reproduction
MAW_TASK_018,
                        'source_status' => 'Needs Verification',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Review 17 Sep', 'Needs Review'],
                    ],
                ],
            ],
            [
                'module_num' => 5,
                'name' => '05 — Quarterly Assessment & Timeline',
                'description' => <<<'MAW_MODULE_05'
**Source Module:** MODULE 5 — Quarterly Assessment & Timeline (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-019, TASK-020, TASK-021, TASK-022 (4)
**Requirement Sources:** REV-JUN-009 · REV-JUN-010 · COM-012 · REV-JUN-003 · REV-SEP-011 · REV-SEP-012 · REV-SEP-013 · COM-013 · CR-006 · REV-SEP-015 · COM-014
**Original Status Breakdown:** Implemented: 1 · Partially Implemented: 2 · Not Implemented: 1

**Related Conflicts:**
- CONFLICT-06 — Monthly self-monitoring cadence vs quarterly implementation

**Tasks:**
- TASK-019 — Assessment timeline and quarterly configuration (Implemented, High)
- TASK-020 — Assessment comparison across quarters (Partially Implemented, High)
- TASK-021 — Graphical summary (charts and trends) per factory (Partially Implemented, High)
- TASK-022 — Cumulative all-factory comparison report with drill-down (Not Implemented, High)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 5 — Quarterly Assessment & Timeline. See that file in the repository root for the complete source analysis._
MAW_MODULE_05,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-019',
                        'number' => 19,
                        'title' => 'Assessment timeline and quarterly configuration',
                        'description' => <<<'MAW_TASK_019'
**Source Task ID:** TASK-019 (`Mother-At-Work_TASKS.md`, MODULE 5 — Quarterly Assessment & Timeline)
**Source Requirement ID(s):** REV-JUN-009 · REV-JUN-010 · COM-012
**Source Document(s):** DOC-04 Meeting Minutes 29 Jun · DOC-02 SRS
**Original Status:** Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** Done · High · Feature

- **Module:** Quarterly Assessment
- **Type:** Implementation
- **Source:** REV-JUN-009, REV-JUN-010 · COM-012 (SRS FR-ME-008)
- **Classification:** Review / Change + Our Commitment
- **Priority:** High
- **Status:** **Implemented**
- **Requirement:** June 29: *"Add assessment timeline"* and *"Configure quarterly assessments."*
  SRS FR-ME-008 commits to continuous monitoring with periodic data-update requirements.
- **Current Implementation:** `dynamic_form_quarters` provides named quarters with `date_from`,
  `date_to`, `sort` and a later-added `deadline`, scoped per dynamic form and per association type.
  `dynamic_form_quarter_user_assignments` assigns an association user to a specific factory for a
  specific quarter, with cascade constraints on all four foreign keys. Monitorings carry `quarter_id`,
  letting submissions be attributed to a reporting period.
- **Gap:** None for the June scope.
- **Evidence:**
  - `database/migrations/2026_07_01_000001_create_dynamic_form_quarters_table.php`
  - `database/migrations/2026_07_02_160500_add_deadline_and_rmg_to_quarters_and_assignments.php` — `deadline` + assignment table
  - `database/migrations/2026_07_07_120000_add_quarter_id_to_monitorings_table.php`
  - `database/migrations/2026_08_14_162800_add_association_type_to_dynamic_form_quarters_table.php`
  - `app/Models/Form/DynamicFormQuarter.php`, `DynamicFormQuarterUserAssignment.php`
  - `tests/Feature/DashboardQuarterlyIndustryAssessmentsTest.php`, `tests/Feature/Dashboard/Setup/AssessmentSetupControllerTest.php`
- **Acceptance Criteria:**
  - [x] Quarters configurable with date range and deadline
  - [x] Per-quarter assignment of assessor to factory
  - [x] Monitorings attributed to a quarter

#### Source Traceability

- Matrix COM-012: Quarterly assessment + timeline (SRS FR-ME-008) — Implemented — `dynamic_form_quarters` + assignments
- Matrix REV-JUN-009: Assessment timeline (June 29) — Implemented — `date_from`/`date_to`/`deadline`
- Matrix REV-JUN-010: Quarterly assessments (June 29) — Implemented — `dynamic_form_quarters`
- CONFLICT-06: Monthly self-monitoring cadence vs quarterly implementation — clarification: Confirm quarterly supersedes monthly for all monitoring modes, including employer self-monitoring.
MAW_TASK_019,
                        'source_status' => 'Implemented',
                        'status' => 'done',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 29 Jun', 'Source: SRS Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-020',
                        'number' => 20,
                        'title' => 'Assessment comparison across quarters',
                        'description' => <<<'MAW_TASK_020'
**Source Task ID:** TASK-020 (`Mother-At-Work_TASKS.md`, MODULE 5 — Quarterly Assessment & Timeline)
**Source Requirement ID(s):** REV-JUN-003 · REV-SEP-011 · REV-SEP-012
**Source Document(s):** DOC-04 Meeting Minutes 29 Jun · DOC-06 Meeting Minutes 17 Sep
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** In Progress · High · Feature

- **Module:** Quarterly Assessment / Reporting
- **Type:** Implementation
- **Source:** REV-JUN-003 · REV-SEP-011 (menu rename) · REV-SEP-012 (difference column)
- **Classification:** Review / Change — originated June, refined September
- **Priority:** High
- **Status:** **Partially Implemented**

#### Requirement Evolution

- **REV-JUN-003** (29 Jun) — *"Introduce Assessment Comparison."* → delivered.
- **REV-SEP-011** (17 Sep) — *"Need to change the menu 'Compare Assessment' to another more suitable
  menu name."* → delivered; the menu now reads **"Assessment Comparison"**.
- **REV-SEP-012** (17 Sep) — *"while showing two quarters comparison of a company is there any way add
  another column showing the difference of those selected Quarter"* → **outstanding**.

- **Requirement (current):** Quarter-over-quarter comparison for a factory, plus an explicit
  **difference column** between the two selected quarters.
- **Current Implementation:** A full comparison report exists — filters for association type,
  factory and multiple quarters; a 916-line service; question grouping; frozen label column; one
  dynamic column per selected quarter; Excel export; permission-gated and association-scoped. **No
  difference/delta column is computed or rendered** — the service contains no diff/delta/variance
  logic and the table renders only `comparisonColumns`, one per quarter.
- **Gap:** Delta computation and its column; the rule for computing a delta on non-numeric (yes/no)
  answers is undefined.
- **Evidence:**
  - `app/Http/Controllers/Dashboard/Reports/CompareAssessmentController.php` — index + export
  - `app/Services/Reports/CompareAssessmentService.php` (916 lines) — no `diff`/`delta`/`variance` symbol present
  - `resources/js/pages/dashboard/reports/compare-assessment/Index.vue:536-546` — `Column field="label"` then one `Column` per quarter
  - `resources/js/components/dashboard/NavMain.vue:316` — menu label `'Assessment Comparison'`
  - `app/Exports/CompareAssessmentExport.php`
  - `tests/Feature/Dashboard/Reports/CompareAssessmentControllerTest.php`
- **Dependencies:** TASK-021, TASK-033
- **Acceptance Criteria:**
  - [x] Menu renamed to "Assessment Comparison"
  - [ ] Difference column appears when exactly two quarters are selected
  - [ ] Delta semantics agreed for yes/no answers (e.g. improved / unchanged / declined) and for scores
  - [ ] Delta included in the Excel export
  - [ ] Behaviour defined when more or fewer than two quarters are selected

#### Source Traceability

- Matrix REV-JUN-003: Assessment comparison (June 29) — Implemented — `CompareAssessmentController` + service
- Matrix REV-SEP-011: Rename Compare Assessment (Sep 17) — Implemented — `NavMain.vue:316`
- Matrix REV-SEP-012: Quarter difference column (Sep 17) — Not Implemented — No delta logic
- Open question Q-06: How should a quarter-over-quarter "difference" be expressed for yes/no answers?
- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
- Dependency `TASK-033` linked to TASK-026: reporting-module reference to TASK-033 means the 7 reports (TASK-026), as in TASK-015/016
MAW_TASK_020,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 29 Jun', 'Source: Review 17 Sep'],
                    ],
                    [
                        'source_task_id' => 'TASK-021',
                        'number' => 21,
                        'title' => 'Graphical summary (charts and trends) per factory',
                        'description' => <<<'MAW_TASK_021'
**Source Task ID:** TASK-021 (`Mother-At-Work_TASKS.md`, MODULE 5 — Quarterly Assessment & Timeline)
**Source Requirement ID(s):** REV-SEP-013 · COM-013 · CR-006
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-02 SRS · DOC-01 TOR
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** In Progress · High · Enhancement

- **Module:** Quarterly Assessment / Reporting
- **Type:** Enhancement
- **Source:** REV-SEP-013 · COM-013 (SRS FR-ME-001 trend graph, FR-DA-003) · CR-006 (TOR §3c)
- **Classification:** **Review / Change reinforcing an existing commitment** (COMBINED)
- **Priority:** High
- **Status:** **Partially Implemented**
- **Requirement:** Sep 17: *"Also need Graphical Visualization/Analysis like Charts, Trends as Summary
  Report for that Factory."* SRS FR-ME-001 commits to a 12-month trend graph on the Report Card;
  FR-DA-003 to interactive charts with drill-down; TOR §3(c) to drill-down monitoring of longitudinal
  trends and factory-specific performance.
- **Current Implementation:** Charting infrastructure is strong — Highcharts 12, a configurable
  `dashboard_chart_configs` model with per-form chart CRUD, chart type/group-by/statistic enums, a
  dynamic-charts API endpoint, and dashboard analytics endpoints including monthly average score and
  quarterly assessment series. What is missing is a **factory-level graphical summary report**: the
  charts serve the aggregate dashboard, not a per-factory trend/summary view reachable from the
  factory or comparison screens.
- **Gap:** Per-factory trend and summary visualisation as a report artifact, with export.
- **Evidence:**
  - `database/migrations/2026_07_09_000001_create_dashboard_chart_configs_table.php`, `..._add_created_by_...`
  - `app/Models/Dashboard/DashboardChartConfig.php`; `app/Enums/DashboardChartType.php`, `DashboardChartGroupBy.php`, `DashboardChartStatistic.php`
  - `routes/dashboard.php:225-233` — per-form chart CRUD; `:65` — `dynamic-charts`
  - `routes/api.php:58-60` — `monthly-average-score`, `quarterly-industry-assessments`
  - `resources/js/components/charts/`; `package.json` — `highcharts ^12.4.0`
  - `tests/Feature/Dashboard/DashboardChartConfigTest.php`, `tests/Feature/DashboardMonthlyAverageScoreTest.php`
  - `resources/js/pages/dashboard/reports/` — six report folders, none a factory graphical summary
- **Dependencies:** TASK-020, TASK-033, TASK-046
- **Acceptance Criteria:**
  - [ ] Factory summary view showing score trend across quarters
  - [ ] Per-standard breakdown chart for the selected factory
  - [ ] Reachable from the factory record and from Assessment Comparison
  - [ ] Exportable to PDF, per SRS FR-DA-006

#### Source Traceability

- Matrix CR-006: Interactive visualization with drill-down (TOR §3c) — Partially Implemented — Highcharts + chart configs; no drill-down
- Matrix REV-SEP-013: Charts/trends factory summary (Sep 17) — Partially Implemented — Charts exist; no factory summary
- Recommended sequence: Phase 5 — Reporting (starts on receipt of the format)
- Dependency `TASK-033` linked to TASK-026: reporting-module reference to TASK-033 means the 7 reports (TASK-026), as in TASK-015/016
MAW_TASK_021,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'Source: SRS Commitment', 'Source: Client Requirement (ToR)'],
                    ],
                    [
                        'source_task_id' => 'TASK-022',
                        'number' => 22,
                        'title' => 'Cumulative all-factory comparison report with drill-down',
                        'description' => <<<'MAW_TASK_022'
**Source Task ID:** TASK-022 (`Mother-At-Work_TASKS.md`, MODULE 5 — Quarterly Assessment & Timeline)
**Source Requirement ID(s):** REV-SEP-015 · COM-014
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-02 SRS
**Original Status:** Not Implemented · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** To Do · High · Enhancement

- **Module:** Reporting
- **Type:** Enhancement
- **Source:** REV-SEP-015 · COM-014 (SRS FR-DA-003 drill-down, FR-DA-005 regional/national reports)
- **Classification:** Review / Change reinforcing a commitment
- **Priority:** High
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"Need a Comparison Report on All Factories - a cumulative report for all
  Factories for all check list, then when select or filter a factor it will show more detailed report
  for that Factory."* This is the drill-down national → factory path committed in SRS FR-DA-003.
- **Current Implementation:** Assessment Comparison compares **quarters**, optionally across factories
  within one association type, but there is no cumulative all-factory view across the full checklist,
  and no drill-through from an aggregate row into a single factory's detail. `IndustryScoreService`
  produces a score listing but not a checklist-wide cumulative comparison.
- **Gap:** The cumulative report and its drill-down interaction.
- **Evidence:**
  - `app/Http/Controllers/Dashboard/Reports/CompareAssessmentController.php:36-40` — industry options only resolved when exactly one association type is selected
  - `app/Services/Reports/IndustryScoreService.php`; `app/Http/Controllers/Dashboard/Reports/IndustryScoreController.php`
  - `resources/js/pages/dashboard/reports/` — no all-factory cumulative report page
- **Dependencies:** TASK-020, TASK-021, TASK-033
- **Acceptance Criteria:**
  - [ ] Cumulative report covering every factory against every checklist standard
  - [ ] Filter/select a factory to drill into its detailed report
  - [ ] Association scoping honoured (BGMEA cannot see BKMEA factories)
  - [ ] Exportable

#### Source Traceability

- Matrix REV-SEP-015: All-factory cumulative report (Sep 17) — Not Implemented — No drill-down report
- Recommended sequence: Phase 5 — Reporting (starts on receipt of the format)
- Dependency `TASK-033` linked to TASK-026: reporting-module reference to TASK-033 means the 7 reports (TASK-026), as in TASK-015/016
MAW_TASK_022,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'Source: SRS Commitment'],
                    ],
                ],
            ],
            [
                'module_num' => 6,
                'name' => '06 — Scoring, Weightage & Rating',
                'description' => <<<'MAW_MODULE_06'
**Source Module:** MODULE 6 — Scoring, Weightage & Rating (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-023, TASK-024 (2)
**Requirement Sources:** REV-SEP-006 · COM-015 · CR-002 · CR-012 · COM-016
**Original Status Breakdown:** Partially Implemented: 2

**Related Conflicts:**
- CONFLICT-06 — Monthly self-monitoring cadence vs quarterly implementation
- CONFLICT-07 — Report Card commitments vs delivered scoring

**Tasks:**
- TASK-023 — Admin-configurable weightage parameters (Partially Implemented, High)
- TASK-024 — Report Card: rankings, tiers, badges and peer comparison (Partially Implemented, High)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 6 — Scoring, Weightage & Rating. See that file in the repository root for the complete source analysis._
MAW_MODULE_06,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Admin Panel'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-023',
                        'number' => 23,
                        'title' => 'Admin-configurable weightage parameters',
                        'description' => <<<'MAW_TASK_023'
**Source Task ID:** TASK-023 (`Mother-At-Work_TASKS.md`, MODULE 6 — Scoring, Weightage & Rating)
**Source Requirement ID(s):** REV-SEP-006 · COM-015 · CR-002
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-02 SRS · DOC-01 TOR
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** In Progress · High · Enhancement

- **Module:** Scoring & Weightage
- **Type:** Enhancement
- **Source:** REV-SEP-006 · COM-015 (SRS FR-ME-002, FR-DA-002) · CR-002 (TOR §3a customizable scorecard)
- **Classification:** **Review / Change reinforcing an existing commitment** (COMBINED)
- **Priority:** High
- **Status:** **Partially Implemented**
- **Requirement:** Sep 17: *"Need to customize or change the Weightage Parameters from Admin Portal."*
  SRS FR-ME-002 commits to a *"Weighted scoring algorithm (configurable weights per standard)"*;
  FR-DA-002 to a scorecard builder with *"Weighting of indicators"*; TOR §3(a) to a customizable
  SCORE-CARD.
- **Current Implementation:** Two of the three configurable layers exist.
  **(a) Rating bands are admin-configurable** — `Global Settings → Rating Weightage` lets an admin
  define `{min, label}` bands with validation for uniqueness and 0–100 range, sorted descending.
  **(b) Per-question marks are editable** through the form builder (`dynamic_form_inputs.marks`) and
  `monitoring_items.manual_mark` allows a per-item override.
  **(c) Per-standard weighting is absent** — there is no weight attribute on the seven standard groups
  and no weighted roll-up; the overall score derives from question marks alone.
- **Gap:** Weight per standard (the literal FR-ME-002 commitment), and a scorecard builder UI exposing
  target values, thresholds, visualization type and colour scheme (FR-DA-002).
- **Evidence:**
  - `app/Http/Requests/Settings/RatingSettingsRequest.php` — bands validation and `bands()` normaliser
  - `app/Http/Controllers/Dashboard/Application/SettingController.php:110` — rating update
  - `routes/dashboard.php` — `application-settings/rating` index + store behind `can:general_setting_access` / `can:general_setting_create`
  - `resources/js/components/dashboard/NavMain.vue:452` — `'Rating Weightage'` menu entry
  - `database/migrations/2026_04_06_094413_add_marks_to_dynamic_form_inputs.php`
  - `database/migrations/2026_04_07_145451_add_manual_mark_to_monitoring_items_table.php`
  - `tests/Feature/Settings/RatingSettingsTest.php`
  - No weight column on group inputs; `app/Settings/GeneralSettings.php` has no per-standard weights
- **Dependencies:** TASK-024, TASK-033
- **Acceptance Criteria:**
  - [ ] UNICEF confirms whether "weightage parameters" means rating bands (built), per-question marks (built), or per-standard weights (missing)
  - [ ] If per-standard weights: configurable from the admin portal, validated to sum sensibly, versioned
  - [ ] Changing a weight recalculates scores deterministically and is audit-logged
  - [ ] Historical scores are not silently rewritten by a weight change, or the behaviour is agreed

#### Source Traceability

- Matrix CR-002: Role-based dashboards + customizable scorecards (TOR §3a) — Partially Implemented — `dashboard_chart_configs`; no per-role dashboards
- Matrix COM-015: Configurable weights per standard (SRS FR-ME-002) — Partially Implemented — Rating bands + question marks; no standard weights
- Matrix REV-SEP-006: Configurable weightage (Sep 17) — Partially Implemented — Rating bands only
- Open question Q-05: Does "weightage parameters" mean rating bands (built), per-question marks (built), or per-standard weights (missing)?
- Recommended sequence: Phase 6 — Outstanding Commitments
- Dependency `TASK-033` linked to TASK-026: reporting-module reference to TASK-033 means the 7 reports (TASK-026), as in TASK-015/016
MAW_TASK_023,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'Source: SRS Commitment', 'Source: Client Requirement (ToR)'],
                    ],
                    [
                        'source_task_id' => 'TASK-024',
                        'number' => 24,
                        'title' => 'Report Card: rankings, tiers, badges and peer comparison',
                        'description' => <<<'MAW_TASK_024'
**Source Task ID:** TASK-024 (`Mother-At-Work_TASKS.md`, MODULE 6 — Scoring, Weightage & Rating)
**Source Requirement ID(s):** CR-012 · COM-016
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** In Progress · High · Feature

- **Module:** Scoring & Weightage / M&E
- **Type:** Implementation
- **Source:** CR-012 (TOR §1(i) Report Card) · COM-016 (SRS FR-ME-001, FR-ME-003) · COM (Inception Module 4)
- **Classification:** **Client Requirement + Our Commitment** (COMBINED)
- **Priority:** High
- **Status:** **Partially Implemented**
- **Requirement:** TOR §1(i) names the M@W Report Card as one of three monitoring modes. SRS FR-ME-001
  commits to overall score, per-standard scores, **national ranking and percentile**, **association
  ranking**, **regional/district ranking**, a **12-month trend graph** and **peer comparison with
  similar-sized factories**. FR-ME-003 commits to a **5-star performance tier**, colour coding,
  **badges** for excellence and **"Most Improved"** recognition. Inception Module 4 commits to a
  *"Report Card digital template with auto-population"*. SRS Appendix C specifies a `scorecard` table
  with `national_rank`, `partner_rank`, `performance_tier` and seven per-standard score columns.
- **Current Implementation:** Scoring and rating exist (`IndustryScoreService`, configurable rating
  bands, per-standard question marks, monthly-average and quarterly-assessment analytics). The
  **committed Report Card artifact does not**: there is no `scorecards` table, no persisted
  per-standard score columns, no national/association/district ranking computation, no percentile, no
  5-star tier, no badges, no "Most Improved", and no peer comparison by factory size.
- **Gap:** The Report Card as a named deliverable, and the ranking/tier/badge layer beneath it.
- **Evidence:**
  - `database/migrations/` — 87 migrations; **no scorecard/report-card table** (SRS Appendix C Table 6 unimplemented)
  - `app/Services/Reports/IndustryScoreService.php` — score listing, no ranking or percentile
  - `app/Http/Requests/Settings/RatingSettingsRequest.php` — bands give a label, not a 5-star tier
  - `routes/api.php:58-60` — aggregate analytics only
  - Repository-wide search: no `rank`, `percentile`, `badge` or `tier` domain logic
- **Dependencies:** TASK-023, TASK-021, TASK-046
- **Acceptance Criteria:**
  - [ ] UNICEF confirms which FR-ME-001/003 elements remain in scope for this contract
  - [ ] Report Card view per factory, auto-populated from submitted monitoring
  - [ ] Rankings computed and refreshed on score change
  - [ ] Tier and colour coding rendered consistently with the configured rating bands
  - [ ] Exportable to PDF

#### Source Traceability

- Matrix CR-012: M@W Report Card (TOR §1(i)) — Not Implemented — No scorecard table or ranking logic
- Matrix CR-015: Performance ranking, automated progress tracking (TOR Purpose) — Not Implemented — No ranking computation
- Matrix COM-016: Report Card w/ rankings, tiers, badges (SRS FR-ME-001/003) — Not Implemented — No scorecard entity
- CONFLICT-06: Monthly self-monitoring cadence vs quarterly implementation — clarification: Confirm quarterly supersedes monthly for all monitoring modes, including employer self-monitoring.
- CONFLICT-07: Report Card commitments vs delivered scoring — clarification: Which FR-ME-001/003 elements remain in scope, given the September focus shifted toward checklist-based reports?
- Open question Q-10: Which FR-ME-001/003 Report Card elements (rankings, percentiles, tiers, badges, peer comparison) remain in scope?
- Recommended sequence: Phase 6 — Outstanding Commitments
MAW_TASK_024,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                ],
            ],
            [
                'module_num' => 7,
                'name' => '07 — Reporting',
                'description' => <<<'MAW_MODULE_07'
**Source Module:** MODULE 7 — Reporting (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-025, TASK-026, TASK-027, TASK-028, TASK-029 (5)
**Requirement Sources:** REV-JUL-001 · REV-JUN-011 · REV-JUN-016 · REV-JUN-017 · REV-SEP-008 · COM-017 · CR-002 · REV-SEP-007 · REVIEW-005 · REVIEW-006
**Original Status Breakdown:** Not Implemented (blocked): 1 · Not Implemented: 1 · Partially Implemented: 1 · Needs Verification: 2

**Related Conflicts:**
- CONFLICT-08 — July 13 blocking dependency unresolved past its deadline

**Tasks:**
- TASK-025 — REV-JUL: reporting module blocked pending UNICEF's finalized format (Not Implemented (blocked), Critical)
- TASK-026 — Seven standard reports derived from the Monitoring Checklist (Not Implemented, Critical)
- TASK-027 — Standardise report names (Partially Implemented, Medium)
- TASK-028 — REVIEW: report exports load full result sets into memory (Needs Verification, Medium)
- TASK-029 — REVIEW: report services vary in eager loading, risking N+1 (Needs Verification, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 7 — Reporting. See that file in the repository root for the complete source analysis._
MAW_MODULE_07,
                'status' => 'in_progress',
                'priority' => 'critical',
                'scopes' => ['Reporting'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-025',
                        'number' => 25,
                        'title' => 'REV-JUL: reporting module blocked pending UNICEF\'s finalized format',
                        'description' => <<<'MAW_TASK_025'
**Source Task ID:** TASK-025 (`Mother-At-Work_TASKS.md`, MODULE 7 — Reporting)
**Source Requirement ID(s):** REV-JUL-001 · REV-JUN-011 · REV-JUN-016 · REV-JUN-017
**Source Document(s):** DOC-05 Meeting Minutes 13 Jul · DOC-04 Meeting Minutes 29 Jun
**Original Status:** Not Implemented (blocked) · **Original Priority:** Critical · **Original Type:** Process / Dependency
**Imported As:** Blocked · Critical · Research

- **Module:** Reporting
- **Type:** Process / Dependency
- **Source:** REV-JUL-001 · REV-JUN-011 · REV-JUN-016, REV-JUN-017
- **Classification:** Review / Change — **external blocker**
- **Priority:** Critical
- **Status:** **Not Implemented** (blocked)

#### Requirement Evolution

- **REV-JUN-011** (29 Jun) — *"Develop a Reporting Module."*
- **REV-JUN-016/017** (29 Jun) — BGMEA & BKMEA to supply the report format by **2 Jul 2026**; UNICEF
  to supply the report format; KAZ to implement by **17 Jul 2026**.
- **REV-JUL-001** (13 Jul) — Sequence formally agreed: BGMEA/BKMEA submit compiled reports **to
  UNICEF**; **UNICEF** reformats column data per the **Rule Book** and shares the finalized template;
  *"Official development work on the reporting module will only commence upon receipt of the finalized
  format from UNICEF."* KAZ on standby, preparing the development environment.
- **REV-SEP-007/008** (17 Sep) — Standard report names; **at least 7 reports based on the Monitoring
  Check List**; legal references as footnotes. See TASK-026 and TASK-016.

- **Requirement:** Build the reporting module to UNICEF's finalized, Rule-Book-conformant format.
- **Current Implementation:** KAZ's standby commitment is visibly met — substantial reporting
  infrastructure is in place ahead of the format: six fixed reports, a configurable report-template
  engine with three template types, an expression parser with guardrails, per-report export
  permissions and Excel/CSV/PDF export plumbing. The **format-specific reports themselves cannot be
  built** until UNICEF delivers the template.
- **Gap:** The finalized format. As of this analysis the July 13 action items remain open, and the
  September meeting added requirements (7 checklist-based reports, legal-reference footnotes,
  standard naming) without recording receipt of the format.
- **Evidence:**
  - `app/Models/Report/ReportTemplate.php`; `database/migrations/2026_07_31_161756_create_report_templates_table.php`
  - `app/Enums/ReportTemplateType.php` — `kpi_card`, `matrix_table`, `summary_dashboard`
  - `app/Services/ReportEngineService.php`; `app/Support/Report/ReportExpressionParser.php`
  - `app/Http/Controllers/Dashboard/Report/ReportBuilderController.php`; `routes/dashboard.php:243-246`
  - `tests/Unit/Services/ReportEngineServiceTest.php`, `ReportEngineGuardrailsTest.php`, `tests/Unit/Support/Report/ReportExpressionParserTest.php`, `tests/Feature/Reports/ReportTemplateSeederTest.php`
  - `database/seeders/ReportTemplateSeeder.php`
- **Dependencies:** Blocks TASK-026, TASK-016, TASK-022, TASK-066
- **Acceptance Criteria:**
  - [ ] UNICEF delivers the finalized report format/template, in writing
  - [ ] Receipt confirmed by email by all parties (per the July 13 next steps)
  - [ ] Revised delivery timeline agreed, since 17 Jul 2026 has long passed
  - [ ] Reports implemented to the delivered format

#### Source Traceability

- Matrix REV-JUN-011: Reporting module (June 29) — Not Implemented — Blocked on format
- Matrix REV-JUL-001: Reporting blocked on UNICEF format (July 13) — Blocked — Infrastructure ready; no format
- CONFLICT-08: July 13 blocking dependency unresolved past its deadline — clarification: Has UNICEF delivered the format? If not, the schedule and any contractual milestone tied to reporting need renegotiation.
- Recommended sequence: Phase 2 — Escalate Blockers (parallel with Phase 1; no development)
- Dependency `TASK-066` not linked: TASK-066 is the app-name check, which the report format cannot block; the mobile reports (TASK-050) already declare TASK-025 as their blocker
MAW_TASK_025,
                        'source_status' => 'Not Implemented (blocked)',
                        'status' => 'blocked',
                        'priority' => 'critical',
                        'task_type' => 'Research',
                        'labels' => ['Source: Review 13 Jul', 'Source: Review 29 Jun'],
                    ],
                    [
                        'source_task_id' => 'TASK-026',
                        'number' => 26,
                        'title' => 'Seven standard reports derived from the Monitoring Checklist',
                        'description' => <<<'MAW_TASK_026'
**Source Task ID:** TASK-026 (`Mother-At-Work_TASKS.md`, MODULE 7 — Reporting)
**Source Requirement ID(s):** REV-SEP-008 · COM-017 · CR-002
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-02 SRS · DOC-01 TOR
**Original Status:** Not Implemented · **Original Priority:** Critical · **Original Type:** Implementation
**Imported As:** To Do · Critical · Feature

- **Module:** Reporting
- **Type:** Implementation
- **Source:** REV-SEP-008 · COM-017 (SRS FR-DA-005) · CR-002
- **Classification:** **Review / Change — NEW REQUIREMENT** (specific count and basis are new)
- **Priority:** **Critical**
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"We should create at least 7 reports baesd on the Monitoring Check List"*,
  with standard report naming (REV-SEP-007) and legal-reference footnotes (REV-SEP-009).
- **Current Implementation:** Six reports exist, but **none is organised around the seven standards**.
  They are: Assessment Comparison, Material Learning, User Directory, Factory Monitoring Progress
  (formerly Geographical Coverage), Factory Registration, Factory Score — plus a custom report engine
  driven by `report_templates`. The report tagging vocabulary reinforces the gap: `FormReportTag` has
  four demographic tags (`working_pregnant_women`, `working_mother`, `male_worker`, `female_worker`)
  and **no standard-level tag**, so reports cannot currently be sliced by standard without new
  tagging.
- **Gap:** Seven standard-aligned reports; a standard-level grouping key; standardised naming;
  footnoted legal references. Blocked on TASK-025.
- **Evidence:**
  - `routes/dashboard.php:112-171` — the six report routes with their `can:*_access` / `can:*_export` guards
  - `app/Http/Controllers/Dashboard/Reports/` — six controllers
  - `app/Services/Reports/` — six services + `Concerns/ProvidesReportFilterOptions.php`
  - `app/Enums/FormReportTag.php:16-19` — four demographic cases only
  - `database/seeders/DynamicFormInputsTableSeeder.php:67-169` — the seven `Std. N` groups that the reports would key on
  - `app/Models/Report/ReportTemplate.php` — the engine that could host them
- **Dependencies:** TASK-025 (blocker), TASK-016, TASK-023, TASK-027
- **Acceptance Criteria:**
  - [ ] Seven reports, one per M@W standard, built on the checklist structure
  - [ ] Each carries its legal reference and footnote definitions (TASK-016)
  - [ ] Report names follow the standard vocabulary agreed with UNICEF (TASK-027)
  - [ ] Each exportable to Excel and PDF, association-scoped and permission-gated
  - [ ] Cumulative and per-factory views available (TASK-022)

#### Source Traceability

- Matrix REV-SEP-008: 7 checklist-based reports (Sep 17) — Not Implemented — 6 unrelated reports exist
- CONFLICT-08: July 13 blocking dependency unresolved past its deadline — clarification: Has UNICEF delivered the format? If not, the schedule and any contractual milestone tied to reporting need renegotiation.
- Recommended sequence: Phase 5 — Reporting (starts on receipt of the format)
MAW_TASK_026,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'critical',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 17 Sep', 'Source: SRS Commitment', 'Source: Client Requirement (ToR)', 'New Requirement'],
                    ],
                    [
                        'source_task_id' => 'TASK-027',
                        'number' => 27,
                        'title' => 'Standardise report names',
                        'description' => <<<'MAW_TASK_027'
**Source Task ID:** TASK-027 (`Mother-At-Work_TASKS.md`, MODULE 7 — Reporting)
**Source Requirement ID(s):** REV-SEP-007
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Enhancement
**Imported As:** In Progress · Medium · Enhancement

- **Module:** Reporting / UI
- **Type:** Enhancement
- **Source:** REV-SEP-007
- **Classification:** Review / Change
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** Sep 17: *"We should use more standard words for Report Name."*
- **Current Implementation:** Two renames are already live in the sidebar —
  **"Geographical Coverage" → "Factory Monitoring Progress"** (REV-SEP-005) and
  **"Compare Assessment" → "Assessment Comparison"** (REV-SEP-011). The underlying route names,
  permissions, controller/service class names and export filenames still use the old vocabulary
  (`geographical-coverage`, `compare-assessment`), and no agreed naming standard is documented.
- **Gap:** A documented naming convention, applied to remaining report titles, page headings and
  export filenames.
- **Evidence:**
  - `resources/js/components/dashboard/NavMain.vue:288` — `'Factory Monitoring Progress'`
  - `resources/js/components/dashboard/NavMain.vue:316` — `'Assessment Comparison'`
  - `routes/dashboard.php:137-143` — routes still named `geographical-coverage.*`
  - `app/Http/Controllers/Dashboard/Reports/GeographicalCoverageController.php`, `app/Services/Reports/GeographicalCoverageService.php`, `app/Exports/GeographicalCoverageExport.php`
  - `resources/js/pages/dashboard/reports/geographical-coverage/Index.vue`
- **Dependencies:** TASK-009, TASK-026
- **Acceptance Criteria:**
  - [x] "Geographical Coverage" renamed to a monitoring-progress label
  - [x] "Compare Assessment" renamed
  - [ ] Naming convention agreed with UNICEF and documented
  - [ ] Page headings and export filenames match the menu labels
  - [ ] Internal route/permission slugs either aligned or explicitly retained as identifiers

#### Source Traceability

- Matrix REV-SEP-005: Geographical Coverage → Monitoring Progress (Sep 17) — Implemented — `NavMain.vue:288`
- Matrix REV-SEP-007: Standard report names (Sep 17) — Partially Implemented — Two renamed
- Recommended sequence: Phase 5 — Reporting (starts on receipt of the format)
MAW_TASK_027,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep'],
                    ],
                    [
                        'source_task_id' => 'TASK-028',
                        'number' => 28,
                        'title' => 'REVIEW: report exports load full result sets into memory',
                        'description' => <<<'MAW_TASK_028'
**Source Task ID:** TASK-028 (`Mother-At-Work_TASKS.md`, MODULE 7 — Reporting)
**Source Requirement ID(s):** REVIEW-005
**Source Document(s):** Repository review (REVIEW) · DOC-02 SRS
**Original Status:** Needs Verification · **Original Priority:** Medium · **Original Type:** Review
**Imported As:** To Do · Medium · Maintenance

- **Module:** Reporting / Performance
- **Type:** Review
- **Source:** REVIEW-005 · COM (SRS NFR-PERF-002, business constraint "scale to 750+")
- **Classification:** Technical review
- **Priority:** Medium
- **Status:** **Needs Verification**
- **Requirement:** SRS §2.5.3 requires supporting the existing 373 factories and scaling to 750+;
  NFR-PERF-002 requires concurrent users without degradation.
- **Current Implementation:** Every export class implements `FromCollection` together with
  `ShouldAutoSize`, and none implements `ShouldQueue` or `FromQuery`. `FromCollection` materialises
  the entire result set in memory, and `ShouldAutoSize` forces a full column-width pass over every
  row. Exports run synchronously inside the web request. At 373 factories × 7 standards × multiple
  quarters this is already sizeable, and the SRS scaling target doubles it.
- **Gap:** No chunking, no queued export, no row-count ceiling or user warning.
- **Evidence:**
  - `app/Exports/IndustryScoreExport.php:18` — `implements FromCollection, ShouldAutoSize, ...`
  - Same pattern in `GeographicalCoverageExport`, `IndustryRegistrationExport`, `MaterialLearningReportExport`, `CustomReportExport`, `CompareAssessmentExport`, `UserDirectoryExport`, `SurveyResponseExport`
  - No `ShouldQueue` / `FromQuery` / `chunk(` / `cursor(` in `app/Exports/`
  - Export routes are plain synchronous GETs, e.g. `routes/dashboard.php:117-124`
- **Dependencies:** TASK-026, TASK-094
- **Acceptance Criteria:**
  - [ ] Largest realistic export measured for peak memory and duration
  - [ ] If over budget: convert to `FromQuery` with chunking, or queue with notify-on-ready
  - [ ] `ShouldAutoSize` reconsidered for large sheets
  - [ ] Documented row ceiling beyond which export is queued

#### Source Traceability

- Recommended sequence: Phase 5 — Reporting (starts on receipt of the format)
- Dependency `TASK-094` not linked: TASK-094 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_028,
                        'source_status' => 'Needs Verification',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Source: SRS Commitment', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-029',
                        'number' => 29,
                        'title' => 'REVIEW: report services vary in eager loading, risking N+1',
                        'description' => <<<'MAW_TASK_029'
**Source Task ID:** TASK-029 (`Mother-At-Work_TASKS.md`, MODULE 7 — Reporting)
**Source Requirement ID(s):** REVIEW-006
**Source Document(s):** Repository review (REVIEW)
**Original Status:** Needs Verification · **Original Priority:** Medium · **Original Type:** Review
**Imported As:** To Do · Medium · Maintenance

- **Module:** Reporting / Performance
- **Type:** Review
- **Source:** REVIEW-006
- **Classification:** Technical review
- **Priority:** Medium
- **Status:** **Needs Verification**
- **Requirement:** Reports should not issue per-row queries.
- **Current Implementation:** Eager loading is applied inconsistently across the six report services:
  `CompareAssessmentService` uses `with(` three times and `UserDirectoryService` twice, while
  `GeographicalCoverageService`, `IndustryRegistrationService` and `IndustryScoreService` contain no
  `with(` call at all. Those three traverse factory → division/district → monitoring relationships in
  their output, which is the classic N+1 shape. This is a signal, not a proof — some may select
  directly via joins.
- **Gap:** Confirmation under realistic data volume, and eager loading where relationships are walked.
- **Evidence:**
  - `app/Services/Reports/CompareAssessmentService.php` — 3 × `with(`
  - `app/Services/Reports/UserDirectoryService.php` — 2 × `with(`
  - `app/Services/Reports/GeographicalCoverageService.php`, `IndustryRegistrationService.php`, `IndustryScoreService.php` — 0 × `with(`
  - `fruitcake/laravel-debugbar` available in dev for query counting
- **Dependencies:** TASK-028
- **Acceptance Criteria:**
  - [ ] Query counts captured for each report against production-like data
  - [ ] Relationship traversals eager-loaded
  - [ ] Query-count assertions added for the heaviest reports

#### Source Traceability

- Recommended sequence: Phase 5 — Reporting (starts on receipt of the format)
MAW_TASK_029,
                        'source_status' => 'Needs Verification',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Needs Review'],
                    ],
                ],
            ],
            [
                'module_num' => 8,
                'name' => '08 — LMS / E-Learning',
                'description' => <<<'MAW_MODULE_08'
**Source Module:** MODULE 8 — LMS / E-Learning (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-030, TASK-031, TASK-032, TASK-033 (4)
**Requirement Sources:** REV-SEP-014 · CR-007 · COM-018 · REV-SEP-016 · REV-SEP-017 · REV-SEP-018
**Original Status Breakdown:** Partially Implemented: 2 · Not Implemented: 2

**Related Conflicts:**
- CONFLICT-03 — LMS open to all vs UNICEF approval of all content

**Tasks:**
- TASK-030 — Make the LMS publicly accessible (Partially Implemented, High)
- TASK-031 — UNICEF approval workflow for LMS course/training creation (Not Implemented, High)
- TASK-032 — Certificate design supplied by UNICEF (Partially Implemented, Medium)
- TASK-033 — Rename material "Name" to "Course Title" (Not Implemented, Low)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 8 — LMS / E-Learning. See that file in the repository root for the complete source analysis._
MAW_MODULE_08,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-030',
                        'number' => 30,
                        'title' => 'Make the LMS publicly accessible',
                        'description' => <<<'MAW_TASK_030'
**Source Task ID:** TASK-030 (`Mother-At-Work_TASKS.md`, MODULE 8 — LMS / E-Learning)
**Source Requirement ID(s):** REV-SEP-014 · CR-007 · COM-018
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-01 TOR · DOC-02 SRS
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** In Progress · High · Enhancement

- **Module:** LMS
- **Type:** Enhancement
- **Source:** REV-SEP-014 · CR-007 (TOR §3d, §3e) · COM-018 (SRS FR-EL-004 self-enrollment)
- **Classification:** **Review / Change reinforcing a commitment** (COMBINED)
- **Priority:** High
- **Status:** **Partially Implemented**
- **Requirement:** Sep 17: *"Need to make the 'LMS' Open for All - publicly accessable - through self
  registration."* SRS FR-EL-004 commits to *"Self-enrollment for open courses"*; TOR §3(e) commits to
  a digital hub for broader resource sharing.
- **Current Implementation:** Half-open today. The **course catalogue is public** —
  `GET /tutorials` has no auth middleware. **Course consumption is gated** — `GET /tutorial/{material}`
  and `GET /tutorial/{material}/scorm-launch` sit behind `auth:web,admin,association,stakeholder` plus
  `HandleOtpRedirect`. Since self-registration is disabled (TASK-001), a member of the public can
  browse titles but can never obtain an account to open one.
- **Gap:** Self-registration (TASK-001), plus a decision on whether course content becomes fully
  anonymous or merely registration-gated, and how progress/quiz results attach to a self-registered
  learner.
- **Evidence:**
  - `routes/web.php` — `Route::get('/tutorials', [CourseController::class, 'index'])` outside any auth group
  - `routes/web.php` — `Route::middleware(['auth:web,admin,association,stakeholder', HandleOtpRedirect::class])` wrapping `courses.show` and `courses.scorm-launch`
  - `app/Http/Controllers/Frontend/CourseController.php`
  - `app/Models/LMS/MaterialUser.php`, `database/migrations/2026_01_26_133931_create_material_users_table.php` — enrolment pivot exists
  - `app/Models/LMS/VideoProgress.php`; `routes/dashboard.php:268-271` — progress endpoints
- **Dependencies:** TASK-001, TASK-031, TASK-032
- **Acceptance Criteria:**
  - [ ] A member of the public can self-register and complete a course end to end
  - [ ] Decision recorded on anonymous vs registered access to course content
  - [ ] Progress, quiz attempts and certificates attach correctly to self-registered learners
  - [ ] SCORM launch works for the public learner role

#### Source Traceability

- Matrix CR-007: Training, e-learning, in-app guidance (TOR §3d) — Partially Implemented — LMS + SCORM + tooltips; no searchable help
- Matrix REV-SEP-014: Public LMS + self registration (Sep 17) — Not Implemented — Registration disabled
- CONFLICT-03: LMS open to all vs UNICEF approval of all content — clarification: Confirm the approval workflow (TASK-031) must ship *before* public access (TASK-030).
- Open question Q-09: What role and permission set does a self-registered public learner receive?
- Recommended sequence: Phase 4 — Public LMS (ordered — approval before opening)
MAW_TASK_030,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'Source: Client Requirement (ToR)', 'Source: SRS Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-031',
                        'number' => 31,
                        'title' => 'UNICEF approval workflow for LMS course/training creation',
                        'description' => <<<'MAW_TASK_031'
**Source Task ID:** TASK-031 (`Mother-At-Work_TASKS.md`, MODULE 8 — LMS / E-Learning)
**Source Requirement ID(s):** REV-SEP-016
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** To Do · High · Feature

- **Module:** LMS
- **Type:** Implementation
- **Source:** REV-SEP-016
- **Classification:** **Review / Change — NEW REQUIREMENT**
- **Priority:** High
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"Need to include Approval Process for LMS Course or Training Creation -
  Approval from UNICEF."* Related: Sep 17 opener — *"UNICEF will review all contents before Launching."*
- **Current Implementation:** A general-purpose approval mechanism exists and is already applied to
  other entities — the `Approvable` trait, an `approvals` table with polymorphic `approvable`/
  `moduleable`, a `Status` enum, and review/approve/decline endpoints for dynamic forms. Community
  feed posts likewise have an approval list. **Materials do not use it**: `Material` carries
  `is_active` and a `youtube_upload_status`/import status, but no approval state, and
  `CreateMaterialLMS` publishes directly inside its transaction with no pending step.
- **Gap:** Apply the existing approval pattern to `Material`; define who approves on UNICEF's behalf;
  block publication until approved.
- **Evidence:**
  - `app/Traits/Approvable.php`; `app/Models/Approval.php`; `database/migrations/2025_12_05_085427_create_approvals_table.php`
  - `app/Http/Controllers/Dashboard/Form/FormBuilderController.php:335,345,355` — `review`, `approve`, `decline` on dynamic forms
  - `routes/dashboard.php:191-192` — feed post approval
  - `app/Actions/LMS/Material/CreateMaterialLMS.php:28-48` — creates and publishes; no approval step
  - `app/Models/LMS/Material.php`; `database/migrations/2025_11_26_130827_create_materials_table.php`
  - `app/Policies/MaterialPolicy.php` — no approve ability
  - `tests/Feature/Dashboard/FormBuilderApprovalTest.php` — the pattern, proven elsewhere
- **Dependencies:** TASK-032, TASK-085
- **Acceptance Criteria:**
  - [ ] New/edited courses enter a pending state and are not publicly visible
  - [ ] A UNICEF-designated role approves or rejects with a comment
  - [ ] Approval decisions audit-logged and notified
  - [ ] Existing published materials migrated to an approved state

#### Source Traceability

- Matrix REV-SEP-016: LMS approval from UNICEF (Sep 17) — Not Implemented — Materials publish directly
- CONFLICT-03: LMS open to all vs UNICEF approval of all content — clarification: Confirm the approval workflow (TASK-031) must ship *before* public access (TASK-030).
- Recommended sequence: Phase 4 — Public LMS (ordered — approval before opening)
MAW_TASK_031,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 17 Sep', 'New Requirement'],
                    ],
                    [
                        'source_task_id' => 'TASK-032',
                        'number' => 32,
                        'title' => 'Certificate design supplied by UNICEF',
                        'description' => <<<'MAW_TASK_032'
**Source Task ID:** TASK-032 (`Mother-At-Work_TASKS.md`, MODULE 8 — LMS / E-Learning)
**Source Requirement ID(s):** REV-SEP-017
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Enhancement
**Imported As:** In Progress · Medium · Enhancement

- **Module:** LMS / Quiz
- **Type:** Enhancement
- **Source:** REV-SEP-017
- **Classification:** Review / Change — **blocked on client deliverable**
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** Sep 17: *"Certificate Design from UNICEF."*
- **Current Implementation:** A working certificate already exists — a landscape "Certificate of
  Achievement" dialog rendered from quiz attempts, using a framed background image, with a
  certificates tab in the learner course navigation. The **design** is a KAZ placeholder, not UNICEF's.
- **Gap:** UNICEF's approved artwork, wording, signatory and any seal/logo requirements.
- **Evidence:**
  - `resources/js/pages/dashboard/lms/quiz-attempts/CertificateDialog.vue:79-102` — certificate body, background `'/assets/certificate-borders-picture-frame.png'`, title *"Certificate of Achievement"*
  - `resources/js/pages/dashboard/lms/quiz-attempts/Index.vue:21,95-97` — dialog wiring
  - `resources/js/pages/landing/Course/partials/CourseTabNav.vue:6` — `'certificates'` tab key
- **Dependencies:** TASK-035, TASK-085
- **Acceptance Criteria:**
  - [ ] UNICEF supplies approved certificate artwork and wording
  - [ ] Template implemented, bilingual, with learner name, course title, date and identifier
  - [ ] Downloadable as PDF by the learner
  - [ ] Issued only on passing the agreed pass mark (TASK-034)

#### Source Traceability

- Matrix REV-SEP-017: Certificate design from UNICEF (Sep 17) — Partially Implemented — Placeholder certificate exists
- Recommended sequence: Phase 4 — Public LMS (ordered — approval before opening)
MAW_TASK_032,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep'],
                    ],
                    [
                        'source_task_id' => 'TASK-033',
                        'number' => 33,
                        'title' => 'Rename material "Name" to "Course Title"',
                        'description' => <<<'MAW_TASK_033'
**Source Task ID:** TASK-033 (`Mother-At-Work_TASKS.md`, MODULE 8 — LMS / E-Learning)
**Source Requirement ID(s):** REV-SEP-018
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented · **Original Priority:** Low · **Original Type:** Enhancement
**Imported As:** To Do · Low · Enhancement

- **Module:** LMS / UI
- **Type:** Enhancement
- **Source:** REV-SEP-018
- **Classification:** Review / Change
- **Priority:** Low
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"Change the 'Name' on the Eid Material to Course Title."*
- **Current Implementation:** The material entity uses `name` / `bn_name`, and the create/update
  actions and dashboard LMS screens label the field "Name". No "Course Title" label exists on the
  material form.
- **Gap:** Label change on the material form, listing column, exports and validation messages
  (display only — the `name` column need not be renamed).
- **Evidence:**
  - `app/Actions/LMS/Material/CreateMaterialLMS.php:31-33` — `'name' => $input['name']`, `'bn_name'`
  - `database/migrations/2025_11_26_130827_create_materials_table.php`
  - `resources/js/pages/dashboard/lms/` — material create/edit/index screens
  - `app/Models/LMS/Material.php`
- **Dependencies:** TASK-009 (same terminology sweep)
- **Acceptance Criteria:**
  - [ ] Material form, listing and exports read "Course Title" in EN and BN
  - [ ] Validation messages use the new label
  - [ ] Underlying column left as `name`, decision recorded

#### Source Traceability

- Matrix REV-SEP-018: Material Name → Course Title (Sep 17) — Not Implemented — Label unchanged
- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
MAW_TASK_033,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep'],
                    ],
                ],
            ],
            [
                'module_num' => 9,
                'name' => '09 — Quiz & Assessment of Learning',
                'description' => <<<'MAW_MODULE_09'
**Source Module:** MODULE 9 — Quiz & Assessment of Learning (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-034, TASK-035, TASK-036, TASK-037 (4)
**Requirement Sources:** REV-SEP-019 · REVIEW-007 · REV-JUN-001
**Original Status Breakdown:** Not Implemented: 2 · Needs Verification: 1 · Implemented — Needs Verification: 1

**Tasks:**
- TASK-034 — Set quiz pass mark to 85% (Not Implemented, High)
- TASK-035 — Hide obtained marks and attempt count from learners (Not Implemented, High)
- TASK-036 — REVIEW: pass-mark default duplicated across six locations (Needs Verification, Low)
- TASK-037 — Quiz attendance export (Implemented — Needs Verification, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 9 — Quiz & Assessment of Learning. See that file in the repository root for the complete source analysis._
MAW_MODULE_09,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-034',
                        'number' => 34,
                        'title' => 'Set quiz pass mark to 85%',
                        'description' => <<<'MAW_TASK_034'
**Source Task ID:** TASK-034 (`Mother-At-Work_TASKS.md`, MODULE 9 — Quiz & Assessment of Learning)
**Source Requirement ID(s):** REV-SEP-019
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented · **Original Priority:** High · **Original Type:** Enhancement / Configuration
**Imported As:** To Do · High · Enhancement

- **Module:** Quiz
- **Type:** Enhancement / Configuration
- **Source:** REV-SEP-019
- **Classification:** **Review / Change — NEW REQUIREMENT**
- **Priority:** High
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"Pass Mark 85%."*
- **Current Implementation:** The pass mark is configurable per quiz but **defaults to 70** in four
  independent places — the migration default, the sync action's fallback, the controller's edit
  fallback and two frontend fallbacks. The pass/fail comparison itself is correct
  (`$isPassed = $score >= $passingScore`). Validation permits 0–100, so 85 is settable today per quiz;
  what is missing is the system-wide default and a migration of existing quizzes.
- **Gap:** Default changed to 85; existing quiz rows updated; the duplicated `70` fallbacks reconciled
  (see TASK-036).
- **Evidence:**
  - `database/migrations/2026_03_13_135240_create_material_quizzes_table.php` — `$table->unsignedTinyInteger('passing_score')->default(70)`
  - `app/Actions/Form/SyncQuizSettingsAction.php:29` — `$request->input('passing_score', 70)`
  - `app/Http/Controllers/Dashboard/Form/FormBuilderController.php:148` — `$quiz?->passing_score ?? 70`
  - `app/Http/Resources/Dashboard/LMS/QuizAttemptResource.php:32` — `?? 70`
  - `resources/js/pages/dashboard/setup/dynamic-form/Create.vue:90`, `components/FormBuilderSettingsForm.vue:41` — `70`
  - `resources/js/pages/landing/Course/partials/Quiz.vue:322,381` — `?? 70` shown to learners
  - `app/Http/Controllers/Api/Quiz/QuizController.php:230` — `$isPassed = $score >= $passingScore`
  - `app/Http/Requests/Form/FormBuilderRequest.php:128` — `min:0, max:100`
- **Dependencies:** TASK-032, TASK-035, TASK-036
- **Acceptance Criteria:**
  - [ ] Default pass mark is 85 everywhere a default is applied
  - [ ] Existing quizzes migrated to 85, or exceptions explicitly agreed
  - [ ] Confirm with UNICEF whether 85% is fixed system-wide or remains per-quiz configurable
  - [ ] Learner-facing instruction text reflects 85%

#### Source Traceability

- Matrix REV-SEP-019: Pass mark 85%, hide marks (Sep 17) — Not Implemented — Default 70; scores shown
- Open question Q-03: Is the pass mark 85% fixed system-wide, or a per-quiz default that admins may change?
- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
MAW_TASK_034,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'New Requirement'],
                    ],
                    [
                        'source_task_id' => 'TASK-035',
                        'number' => 35,
                        'title' => 'Hide obtained marks and attempt count from learners',
                        'description' => <<<'MAW_TASK_035'
**Source Task ID:** TASK-035 (`Mother-At-Work_TASKS.md`, MODULE 9 — Quiz & Assessment of Learning)
**Source Requirement ID(s):** REV-SEP-019
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** To Do · High · Enhancement

- **Module:** Quiz
- **Type:** Enhancement
- **Source:** REV-SEP-019 (second clause)
- **Classification:** **Review / Change — NEW REQUIREMENT**
- **Priority:** High
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"No need to Show obtained Marks or number of attempted."*
- **Current Implementation:** The learner quiz result explicitly shows both. The result summary is
  rendered with `correct`, `total` and `passing` values, and the quiz meta panel displays the passing
  score as a percentage. Attempts are tracked (`max_attempts` on the quiz, one `quiz_attempts` row per
  attempt with `score` and `is_passed`) and attempt data flows to the learner-facing component.
- **Gap:** Suppress the score and attempt count on the learner surface while retaining both for
  administrators (the dashboard quiz-attempts report and certificate issuance depend on them).
- **Evidence:**
  - `resources/js/pages/landing/Course/partials/Quiz.vue:565` — `result_summary` with `correct`, `total`, `passing`
  - `resources/js/pages/landing/Course/partials/Quiz.vue:322` — passing score rendered as a percentage
  - `resources/js/pages/landing/Course/partials/Quiz.vue:31,58` — `passingScore` in both prop interfaces
  - `app/Models/LMS/QuizAttempt.php:16-24` — `score`, `is_passed`, `answers` persisted
  - `app/Http/Controllers/Api/Quiz/QuizController.php:114,133,230` — score returned to the client
  - `app/Http/Controllers/Dashboard/LMS/QuizAttemptsController.php` — admin view (must keep scores)
- **Dependencies:** TASK-034, TASK-036
- **Acceptance Criteria:**
  - [ ] Learner sees only pass/fail, not a numeric score or correct/total
  - [ ] Attempt count hidden from the learner
  - [ ] Administrators retain full score and attempt visibility
  - [ ] The API stops returning learner-visible score detail, not merely hiding it in the UI
  - [ ] Confirm whether the pass threshold itself may still be shown

#### Source Traceability

- Matrix REV-SEP-019: Pass mark 85%, hide marks (Sep 17) — Not Implemented — Default 70; scores shown
- Open question Q-04: May the pass threshold still be displayed to learners once obtained marks are hidden?
- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
MAW_TASK_035,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'New Requirement'],
                    ],
                    [
                        'source_task_id' => 'TASK-036',
                        'number' => 36,
                        'title' => 'REVIEW: pass-mark default duplicated across six locations',
                        'description' => <<<'MAW_TASK_036'
**Source Task ID:** TASK-036 (`Mother-At-Work_TASKS.md`, MODULE 9 — Quiz & Assessment of Learning)
**Source Requirement ID(s):** REVIEW-007
**Source Document(s):** Repository review (REVIEW)
**Original Status:** Needs Verification · **Original Priority:** Low · **Original Type:** Review
**Imported As:** To Do · Low · Maintenance

- **Module:** Quiz
- **Type:** Review
- **Source:** REVIEW-007
- **Classification:** Technical review — maintainability
- **Priority:** Low
- **Status:** **Needs Verification**
- **Requirement:** A business constant should have one definition.
- **Current Implementation:** The literal `70` appears as an independent default in six places across
  migration, action, controller, API resource and two Vue components. TASK-034 must change all six
  consistently; missing one produces a silent inconsistency between what a learner is told and what
  the server enforces.
- **Gap:** No single source of truth (e.g. a config key or enum constant).
- **Evidence:** the six locations listed under TASK-034
- **Dependencies:** TASK-034
- **Acceptance Criteria:**
  - [ ] Default pass mark defined once (config or constant) and referenced everywhere
  - [ ] Frontend receives the default from the server rather than hardcoding it
  - [ ] A test asserts client and server defaults agree

#### Source Traceability

- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
MAW_TASK_036,
                        'source_status' => 'Needs Verification',
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-037',
                        'number' => 37,
                        'title' => 'Quiz attendance export',
                        'description' => <<<'MAW_TASK_037'
**Source Task ID:** TASK-037 (`Mother-At-Work_TASKS.md`, MODULE 9 — Quiz & Assessment of Learning)
**Source Requirement ID(s):** REV-JUN-001
**Source Document(s):** DOC-04 Meeting Minutes 29 Jun
**Original Status:** Implemented — Needs Verification · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** Ready for QA · Medium · Feature

- **Module:** Quiz / Reporting
- **Type:** Implementation
- **Source:** REV-JUN-001
- **Classification:** Review / Change
- **Priority:** Medium
- **Status:** **Implemented** — **Needs Verification**
- **Requirement:** June 29: *"Add Quiz Attendance Export."*
- **Current Implementation:** Quiz attempts are listed at `/dashboard/lms/quiz-attempts` behind
  `can:learner_progress_access`, and the controller supplies `exportFields` from
  `QuizAttempt::exportableFields()` — name, email, material, quiz title, score and status — through
  the shared `ExportableHeaders` / `ExportService` mechanism used by the other exports. A Material
  Learning Report also exists with its own export.
- **Gap:** "Attendance" is not defined in the minutes. The current export covers *attempts*; whether
  the client meant attempt records, or presence/participation per session, is unconfirmed. Note
  TASK-035 asks to hide scores from learners — the admin export legitimately keeps them.
- **Evidence:**
  - `app/Http/Controllers/Dashboard/LMS/QuizAttemptsController.php:61,73` — quiz fetch and `exportFields`
  - `app/Models/LMS/QuizAttempt.php` — `exportableFields()` returning name/email/material/quiz/score/status
  - `routes/dashboard.php:280-282` — `lms.quiz-attempts.index` with `can:learner_progress_access`
  - `app/Exports/MaterialLearningReportExport.php`; `app/Services/Reports/MaterialLearningReportService.php`
  - `app/Traits/ExportableHeaders.php`, `app/Services/Export/`
- **Acceptance Criteria:**
  - [x] Quiz attempt data is exportable with selectable fields
  - [ ] UNICEF confirms "attendance" means attempt records
  - [ ] Export respects association scoping

#### Source Traceability

- Matrix REV-JUN-001: Quiz attendance export (June 29) — Implemented — `QuizAttemptsController` + `exportableFields()`
MAW_TASK_037,
                        'source_status' => 'Implemented — Needs Verification',
                        'status' => 'ready_for_qa',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 29 Jun', 'Needs Review'],
                    ],
                ],
            ],
            [
                'module_num' => 10,
                'name' => '10 — Community Feed',
                'description' => <<<'MAW_MODULE_10'
**Source Module:** MODULE 10 — Community Feed (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-038, TASK-039 (2)
**Requirement Sources:** REV-SEP-021 · REV-JUN-014 · CR-009 · COM-019 · REVIEW-008
**Original Status Breakdown:** Partially Implemented: 2

**Related Conflicts:**
- CONFLICT-02 — Community feed: TOR requires it, September says hide it

**Tasks:**
- TASK-038 — Hide the Community Feed feature (Partially Implemented, High)
- TASK-039 — REVIEW: menu-level hiding leaves routes publicly reachable (Partially Implemented, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 10 — Community Feed. See that file in the repository root for the complete source analysis._
MAW_MODULE_10,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Web Application', 'Mobile Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-038',
                        'number' => 38,
                        'title' => 'Hide the Community Feed feature',
                        'description' => <<<'MAW_TASK_038'
**Source Task ID:** TASK-038 (`Mother-At-Work_TASKS.md`, MODULE 10 — Community Feed)
**Source Requirement ID(s):** REV-SEP-021 · REV-JUN-014 · CR-009 · COM-019
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-04 Meeting Minutes 29 Jun · DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** In Progress · High · Enhancement

- **Module:** Community Feed
- **Type:** Enhancement
- **Source:** REV-SEP-021 · supersedes REV-JUN-014 · conflicts with CR-009 / COM-019
- **Classification:** **Review / Change — SUPERSEDES earlier requirement and partially reverses a TOR item**
- **Priority:** High
- **Status:** **Partially Implemented**

#### Requirement Evolution

- **CR-009** (TOR §3e) — *"Incorporate a scrollable news feed to highlight events, updates, multimedia
  content … and showcase multi-stakeholder partnerships."*
- **COM-019** (SRS FR-RS-004; Inception Module 6) — news feed committed as part of the Resource
  Sharing Hub.
- **REV-JUN-014** (29 Jun) — *"Fix Community post listing."* → implemented (status/approval added).
- **REV-SEP-021** (17 Sep) — *"No need of Community feed and it's communication - for the time being
  No need of this feature, hide it."* → **current authority; explicitly temporary ("for the time being").**

- **Requirement (current):** Hide the community feed, reversibly.
- **Current Implementation:** Hidden **at the dashboard menu only**. The Community Feed collapsible
  block in the sidebar is commented out. Everything beneath it remains live and reachable: the
  dashboard resource routes and like/comment routes behind `can:community_feed_access`, the feed
  approval list, the mobile API feed endpoints (`posts`, likes, comments), and the dashboard
  `latest-community-feed` analytics endpoint. A user with a direct URL or an API token still has full
  access.
- **Gap:** Feature-level concealment. Because the request is explicitly temporary, removal would be
  wrong — a reversible flag is the right shape.
- **Evidence:**
  - `resources/js/components/dashboard/NavMain.vue:331-355` — the entire Community Feed menu block commented out
  - `routes/dashboard.php:316-321` — like/comment routes and `Route::resource('community-feed', PostController::class)` still registered
  - `routes/dashboard.php:187-192` — feed post approval list and approve route still registered
  - `routes/api.php:6-8,128-129,148` — mobile feed controllers and routes still registered
  - `routes/dashboard.php:67` / `routes/api.php:62` — `latest-community-feed` analytics endpoint still live
  - `app/Models/Feed/{Post,PostComment,PostLike}.php`; `app/Policies/FeedPolicy.php`
  - `tests/Feature/Feed/PostTest.php` — still passing against live routes
- **Dependencies:** TASK-039, TASK-046
- **Acceptance Criteria:**
  - [ ] A single feature flag hides the feed across dashboard, landing site and mobile API
  - [ ] Direct URL and API access blocked while the flag is off, not merely unlinked
  - [ ] Dashboard widgets referencing the feed hidden with it
  - [ ] Re-enabling requires only the flag — no code restoration
  - [ ] Confirm with UNICEF whether the mobile app must also hide it in the current release

#### Source Traceability

- Matrix CR-009: Digital hub, multimedia, scrollable feed (TOR §3e) — Partially Implemented — LMS + feed built, feed now hidden; no gallery
- Matrix REV-JUN-016: Fix community post listing (June 29) — Implemented — `add_status_to_posts_table` + approval
- Matrix REV-SEP-021: Hide community feed (Sep 17) — Partially Implemented — Menu only; routes live
- CONFLICT-02: Community feed: TOR requires it, September says hide it — clarification: Is the TOR news-feed requirement satisfied by another surface (e.g. Story/Notice), or deferred? Is the hide temporary — and if so, until when?
- Open question Q-02: Is hiding the community feed temporary? If so, until when — and does the TOR news-feed deliverable stand?
- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
MAW_TASK_038,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'Source: Review 29 Jun', 'Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-039',
                        'number' => 39,
                        'title' => 'REVIEW: menu-level hiding leaves routes publicly reachable',
                        'description' => <<<'MAW_TASK_039'
**Source Task ID:** TASK-039 (`Mother-At-Work_TASKS.md`, MODULE 10 — Community Feed)
**Source Requirement ID(s):** REVIEW-008
**Source Document(s):** Repository review (REVIEW)
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Review
**Imported As:** In Progress · Medium · Maintenance

- **Module:** Community Feed / Security
- **Type:** Review
- **Source:** REVIEW-008
- **Classification:** Technical review — security by obscurity
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** Hiding a feature should remove access, not just the link.
- **Current Implementation:** Commenting out a navigation entry leaves the authorisation surface
  untouched. Any principal still holding `community_feed_access` — and the permission is still seeded
  and assigned — can operate the feature by typing the URL. The same pattern would recur for any other
  "hide this" instruction.
- **Gap:** A general, testable feature-toggle mechanism.
- **Evidence:**
  - `resources/js/components/dashboard/NavMain.vue:331-355` (commented block) vs `routes/dashboard.php:316-321` (live routes)
  - `app/Enums/Permissions.php` and `database/seeders/PermissionSeeder.php` — `community_feed_*` permissions still seeded
  - Commented-out imports remain referenced in the dead block (`communityFeedIndex`, `feedPostsIndex`)
- **Dependencies:** TASK-038
- **Acceptance Criteria:**
  - [ ] Feature flags implemented as middleware or policy checks, not comments
  - [ ] Test asserts a disabled feature returns 403/404 on direct access
  - [ ] Commented-out navigation code removed once the flag exists

#### Source Traceability

- CONFLICT-02: Community feed: TOR requires it, September says hide it — clarification: Is the TOR news-feed requirement satisfied by another surface (e.g. Story/Notice), or deferred? Is the hide temporary — and if so, until when?
- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
MAW_TASK_039,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Security'],
                    ],
                ],
            ],
            [
                'module_num' => 11,
                'name' => '11 — Notice & Notification',
                'description' => <<<'MAW_MODULE_11'
**Source Module:** MODULE 11 — Notice & Notification (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-040 (1)
**Requirement Sources:** REV-JUN-015 · COM-020 · CR-007
**Original Status Breakdown:** Implemented: 1

**Tasks:**
- TASK-040 — Notice and notification system (Implemented, High)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 11 — Notice & Notification. See that file in the repository root for the complete source analysis._
MAW_MODULE_11,
                'status' => 'completed',
                'priority' => 'high',
                'scopes' => ['Web Application', 'Mobile Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-040',
                        'number' => 40,
                        'title' => 'Notice and notification system',
                        'description' => <<<'MAW_TASK_040'
**Source Task ID:** TASK-040 (`Mother-At-Work_TASKS.md`, MODULE 11 — Notice & Notification)
**Source Requirement ID(s):** REV-JUN-015 · COM-020 · CR-007
**Source Document(s):** DOC-04 Meeting Minutes 29 Jun · DOC-02 SRS · DOC-01 TOR
**Original Status:** Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** Done · High · Feature

- **Module:** Notice & Notification
- **Type:** Implementation
- **Source:** REV-JUN-015 · COM-020 (SRS FR-NC-001/002/003) · CR-007
- **Classification:** Review / Change + Our Commitment (COMBINED)
- **Priority:** High
- **Status:** **Implemented**
- **Requirement:** June 29: *"Develop Notice & Notification."* SRS FR-NC-001 commits to multi-channel
  notifications (in-app with bell badge, email, push); FR-NC-002 to notifying on approvals, score
  changes and ticket updates; FR-NC-003 to notification history with timestamp, channel and link.
- **Current Implementation:** Both are built. Notices have a model, service, controller, scheduling
  and a delete permission; scheduled notices dispatch every minute via
  `notices:send-scheduled`. Notifications use a custom `notifications` table extended with
  polymorphic `related` morphs, in-app notification classes, custom channels, FCM tokens on all four
  guard tables, and an unread count shared into every Inertia response for the bell badge. Recipient
  resolution is tested.
- **Gap:** SRS FR-NC-002's "score changes (significant improvement or decline)" trigger is not
  evidenced; see TASK-024, on which it depends.
- **Evidence:**
  - `database/migrations/2026_07_08_000001_create_notices_table.php`, `2026_07_16_142142_update_type_column_to_notices_table.php`
  - `database/migrations/2026_07_10_140000_create_notifications_table.php`, `2026_08_12_164201_add_related_morphs_to_notifications_table.php`
  - `database/migrations/2026_07_09_164655_add_fcm_token_to_auth_guard_tables.php`
  - `app/Models/Notice.php`, `app/Models/DatabaseNotification.php`, `app/Services/NoticeService.php`
  - `app/Notifications/{NoticeInAppNotification,AssociationTaskInAppNotification}.php`, `app/Notifications/Channels/`
  - `app/Jobs/{SendNoticeNotification,SendScheduledNotice,SendAssociationTaskNotification}.php`
  - `routes/console.php` — `notices:send-scheduled` every minute, `onOneServer`, `withoutOverlapping`
  - `app/Http/Middleware/HandleInertiaRequests.php` — `unreadNotificationsCount` shared prop
  - `routes/dashboard.php:69,100-109`; `routes/api.php` — notice API
  - `tests/Feature/Notification/InAppNotificationTest.php`, `NoticeRecipientResolutionTest.php`, `tests/Feature/Dashboard/NoticeControllerTest.php`
- **Acceptance Criteria:**
  - [x] Notices creatable, schedulable and deliverable
  - [x] In-app, email, SMS and push channels available
  - [x] Unread badge count exposed to the UI
  - [ ] Score-change notifications, once Report Card scoring lands (TASK-024)

#### Source Traceability

- Matrix COM-020: Multi-channel notifications (SRS FR-NC-001/003) — Implemented — `notifications` + FCM + channels
- Matrix REV-JUN-017: Notice & Notification (June 29) — Implemented — notices + notifications tables
MAW_TASK_040,
                        'source_status' => 'Implemented',
                        'status' => 'done',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 29 Jun', 'Source: SRS Commitment', 'Source: Client Requirement (ToR)'],
                    ],
                ],
            ],
            [
                'module_num' => 12,
                'name' => '12 — CMS & Landing Page',
                'description' => <<<'MAW_MODULE_12'
**Source Module:** MODULE 12 — CMS & Landing Page (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-041, TASK-042, TASK-043, TASK-044, TASK-045 (5)
**Requirement Sources:** REV-SEP-022 · REV-SEP-023 · REV-SEP-024 · REV-SEP-010 · COM-021 · CR-009 · REV-SEP-027
**Original Status Breakdown:** Not Implemented: 5

**Tasks:**
- TASK-041 — Change the landing page hero title (Not Implemented, Medium)
- TASK-042 — Rename and re-scope the "Story" menu (Not Implemented, Medium)
- TASK-043 — Content categories for Story items (Not Implemented, Medium)
- TASK-044 — Gallery feature on the landing page (Not Implemented, High)
- TASK-045 — Auto-translation for CMS content updates (Not Implemented, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 12 — CMS & Landing Page. See that file in the repository root for the complete source analysis._
MAW_MODULE_12,
                'status' => 'planning',
                'priority' => 'high',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-041',
                        'number' => 41,
                        'title' => 'Change the landing page hero title',
                        'description' => <<<'MAW_TASK_041'
**Source Task ID:** TASK-041 (`Mother-At-Work_TASKS.md`, MODULE 12 — CMS & Landing Page)
**Source Requirement ID(s):** REV-SEP-022
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented · **Original Priority:** Medium · **Original Type:** Enhancement
**Imported As:** To Do · Medium · Enhancement

- **Module:** CMS / Landing Page
- **Type:** Enhancement
- **Source:** REV-SEP-022
- **Classification:** Review / Change — **blocked on client copy**
- **Priority:** Medium
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"Need to Change the Landing Page Hero Title."*
- **Current Implementation:** The live hero copy is a single long sentence stored as
  `frontend.home.banner.empowering_mothers`: *"An Innovative and Impactful Initiative on Maternity
  Rights Protection of Working Women through Promoting and Supporting Maternal Nutrition,
  Breastfeeding & Nurturing Care Practices."* It is editable without a deploy — translations are
  served from the database and manageable through Global Settings → Translation.
- **Gap:** UNICEF has not supplied replacement copy (EN and BN).
- **Evidence:**
  - `lang/en/frontend.home.json` — `banner.empowering_mothers`
  - `database/seeders/data/translations/translations.json` — same key seeded for `en` and `bn`
  - `resources/js/pages/landingV3/Home/` — the live home page (served at `/`)
  - `routes/dashboard.php` — `application-settings/translation` resource
- **Dependencies:** TASK-085 (UNICEF content), TASK-070
- **Acceptance Criteria:**
  - [ ] UNICEF supplies the new hero title in English and Bengali
  - [ ] Updated via the Translation admin screen, no code change required
  - [ ] Verified on the live landing page in both locales

#### Source Traceability

- Matrix REV-SEP-022: Change hero title (Sep 17) — Not Implemented — Awaiting copy
MAW_TASK_041,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep'],
                    ],
                    [
                        'source_task_id' => 'TASK-042',
                        'number' => 42,
                        'title' => 'Rename and re-scope the "Story" menu',
                        'description' => <<<'MAW_TASK_042'
**Source Task ID:** TASK-042 (`Mother-At-Work_TASKS.md`, MODULE 12 — CMS & Landing Page)
**Source Requirement ID(s):** REV-SEP-023
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented · **Original Priority:** Medium · **Original Type:** Enhancement
**Imported As:** To Do · Medium · Enhancement

- **Module:** CMS / Landing Page
- **Type:** Enhancement
- **Source:** REV-SEP-023
- **Classification:** Review / Change
- **Priority:** Medium
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"need to think about the 'Story' menu"* and *"Home Page menu 'Story', it
  should be more relevant to this platform."*
- **Current Implementation:** "Story" is a first-class landing section — a navbar entry (desktop and
  mobile), a footer link, `/story` and `/story/{slug}` routes, a `Story` page with detail view, a home
  page Story component, and DB-backed labels (`frontend.navbar.story` → *"Story"* / *"আমাদের গল্প"*).
  Content is served from CMS site sections.
- **Gap:** A replacement name agreed with UNICEF. Requires a label change plus, ideally, a route slug
  decision.
- **Evidence:**
  - `routes/web.php` — `/story` and `/story/{slug}` on `FrontendController`
  - `resources/js/layouts/landing/Navbar.vue:297,386` — `frontend.navbar.story`
  - `resources/js/layouts/landing/Footer.vue` — story link
  - `resources/js/pages/landing/Story/{MainStory,StoryCard}.vue`, `resources/js/pages/landingV3/Home/components/Story.vue`
  - `lang/en/frontend.navbar.json`, `lang/bn/frontend.navbar.json` — `"story"` key
  - `lang/en/frontend.home.json` — `story.title` / `story.our_stories`
- **Dependencies:** TASK-043, TASK-085
- **Acceptance Criteria:**
  - [ ] New menu name agreed with UNICEF (EN and BN)
  - [ ] Navbar, footer, page heading and home section updated
  - [ ] Decision recorded on whether the `/story` URL changes (and redirects added if so)

#### Source Traceability

- Matrix REV-SEP-023: Rename Story menu (Sep 17) — Not Implemented — Awaiting name
MAW_TASK_042,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep'],
                    ],
                    [
                        'source_task_id' => 'TASK-043',
                        'number' => 43,
                        'title' => 'Content categories for Story items',
                        'description' => <<<'MAW_TASK_043'
**Source Task ID:** TASK-043 (`Mother-At-Work_TASKS.md`, MODULE 12 — CMS & Landing Page)
**Source Requirement ID(s):** REV-SEP-024
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** To Do · Medium · Feature

- **Module:** CMS / Landing Page
- **Type:** Implementation
- **Source:** REV-SEP-024
- **Classification:** **Review / Change — NEW REQUIREMENT**
- **Priority:** Medium
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"Need to add category for Contents for the 'Story' like Acheivment, Our
  Story, Case Study, Information, Events, etc."* This aligns with SRS FR-RS-004, which committed to
  news categories (Program Updates, Success Stories, Events).
- **Current Implementation:** Story content lives in `site_sections`, keyed by `page_type`,
  `page_slug` and `section_key`, with a JSON `meta_data` bag, an `order` and `is_active`. There is
  **no category column and no category taxonomy** for stories. A general `tags` table exists but is
  used for LMS materials, not site sections.
- **Gap:** Category model (or reuse of tags), admin assignment UI, and category filtering on the
  public story listing.
- **Evidence:**
  - `database/migrations/2025_12_10_082003_create_site_sections_table.php` — columns `page_type`, `page_slug`, `section_key`, `order`, `meta_data`, `is_active`; index on `['page_type','section_key','is_active']`; no category
  - `app/Models/CMS/SiteSection.php:22-33` — fillable and `meta_data` array cast
  - `database/migrations/2025_12_10_112819_create_site_section_translations_table.php`
  - `database/migrations/2025_12_18_144858_create_tags_table.php`; `app/Models/Setup/Tag.php` — used by materials
  - `app/Services/SiteSectionService.php`; `tests/Feature/CMS/SiteSectionServiceTest.php`
- **Dependencies:** TASK-042, TASK-044
- **Acceptance Criteria:**
  - [ ] Final category list confirmed by UNICEF
  - [ ] Categories assignable per story from the CMS
  - [ ] Public story listing filterable by category, bilingual
  - [ ] Existing stories assigned a category during migration

#### Source Traceability

- Matrix REV-SEP-024: Story categories (Sep 17) — Not Implemented — No category column
- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
MAW_TASK_043,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 17 Sep', 'New Requirement'],
                    ],
                    [
                        'source_task_id' => 'TASK-044',
                        'number' => 44,
                        'title' => 'Gallery feature on the landing page',
                        'description' => <<<'MAW_TASK_044'
**Source Task ID:** TASK-044 (`Mother-At-Work_TASKS.md`, MODULE 12 — CMS & Landing Page)
**Source Requirement ID(s):** REV-SEP-010 · COM-021 · CR-009
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-02 SRS · DOC-03 Inception Report · DOC-01 TOR
**Original Status:** Not Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** To Do · High · Feature

- **Module:** CMS / Landing Page
- **Type:** Implementation
- **Source:** REV-SEP-010 · COM-021 (SRS FR-RS-003 multimedia gallery; Inception Module 6) · CR-009 (TOR §3e)
- **Classification:** **Review / Change reinforcing an undelivered commitment** (COMBINED)
- **Priority:** High
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"Gallery Features needed to be added on the Landing Page."* SRS FR-RS-003
  commits to multimedia management with an image gallery and video library; Inception Module 6 lists
  "Multimedia gallery"; TOR §3(e) requires a hub sharing multimedia content.
- **Current Implementation:** **Nothing.** A repository-wide search for gallery finds no model, route,
  controller, component or CMS section key. Supporting pieces exist that would serve it — a media
  subsystem with conversions (`spatie/image`, `media` table, `HasMedia` trait, `config/media.php` size
  and MIME limits) and Swiper 11 already used for carousels — but no gallery is built on them.
- **Gap:** The entire feature: model/section type, admin management, public gallery page or section.
- **Evidence:**
  - Repository-wide search for `gallery`/`Gallery` across `app/`, `resources/`, `routes/`, `database/` returns **no matches**
  - `database/migrations/2025_12_05_131918_create_media_table.php`; `app/Models/Media.php`; `app/Traits/Media/HasMedia.php`; `config/media.php`
  - `package.json` — `swiper ^11.2.10`
  - `resources/js/pages/landingV3/Home/` — no gallery component
- **Dependencies:** TASK-043, TASK-085
- **Acceptance Criteria:**
  - [ ] Gallery manageable from the CMS (upload, caption, order, activate)
  - [ ] Rendered on the landing page, responsive, lazy-loaded
  - [ ] Images and video supported per FR-RS-003
  - [ ] Bilingual captions
  - [ ] Respects the media size and MIME limits already configured

#### Source Traceability

- Matrix CR-009: Digital hub, multimedia, scrollable feed (TOR §3e) — Partially Implemented — LMS + feed built, feed now hidden; no gallery
- Matrix COM-021: Multimedia gallery (SRS FR-RS-003) — Not Implemented — No gallery code anywhere
- Matrix REV-SEP-010: Gallery on landing page (Sep 17) — Not Implemented — No gallery code
- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
MAW_TASK_044,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 17 Sep', 'Source: SRS Commitment', 'Source: Inception Commitment', 'Source: Client Requirement (ToR)'],
                    ],
                    [
                        'source_task_id' => 'TASK-045',
                        'number' => 45,
                        'title' => 'Auto-translation for CMS content updates',
                        'description' => <<<'MAW_TASK_045'
**Source Task ID:** TASK-045 (`Mother-At-Work_TASKS.md`, MODULE 12 — CMS & Landing Page)
**Source Requirement ID(s):** REV-SEP-027
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** To Do · Medium · Feature

- **Module:** CMS / Localization
- **Type:** Implementation
- **Source:** REV-SEP-027
- **Classification:** **Review / Change — NEW REQUIREMENT**
- **Priority:** Medium
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"Auto Translation feature for CMS content updates."*
- **Current Implementation:** Translation is entirely **manual**. Bilingual content is stored as
  paired fields (`name`/`bn_name`, `contact_person`/`bn_contact_person`), site-section translations
  have their own table, and a Translation admin screen allows per-key editing. No machine-translation
  service, client or queue job exists — `google/apiclient` is installed but wired only to YouTube.
- **Gap:** Translation provider selection, API credentials, a job to translate on save, and a
  review/override step so machine output is not published unreviewed — significant given UNICEF's
  stated intent to review all content before launch.
- **Evidence:**
  - Repository-wide search for `googletranslate` / `auto.translat` / `translateText` returns no matches
  - `app/Services/LocalizationService.php` — reads DB + settings only
  - `app/Models/Translation.php`; `database/migrations/2026_04_13_131310_create_translations_table.php`
  - `app/Models/CMS/SiteSectionTranslation.php`; `database/migrations/2025_12_10_112819_...`
  - `app/Actions/CMS/Translation/`; `routes/dashboard.php` — `application-settings/translation`
  - `composer.json` — `google/apiclient ^2.19`; `config/services.php` — youtube and firebase only, no translation credentials
  - `tests/Feature/Settings/TranslationManagementTest.php`
- **Dependencies:** TASK-070, TASK-085
- **Acceptance Criteria:**
  - [ ] Translation provider chosen and cost approved by UNICEF
  - [ ] Saving EN CMS content queues a BN translation
  - [ ] Machine output flagged as unreviewed until a human approves it
  - [ ] Manual override always wins and is never overwritten by a later auto-translation
  - [ ] Failures degrade gracefully, leaving existing content intact

#### Source Traceability

- Matrix REV-SEP-027: Auto-translation for CMS (Sep 17) — Not Implemented — No translation service
MAW_TASK_045,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 17 Sep', 'New Requirement'],
                    ],
                ],
            ],
            [
                'module_num' => 13,
                'name' => '13 — Dashboard & Analytics',
                'description' => <<<'MAW_MODULE_13'
**Source Module:** MODULE 13 — Dashboard & Analytics (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-046, TASK-047 (2)
**Requirement Sources:** REV-JUN-013 · CR-002 · COM-022 · COM-023 · CR-006
**Original Status Breakdown:** Partially Implemented: 1 · Partially Implemented — Needs Verification: 1

**Related Conflicts:**
- CONFLICT-07 — Report Card commitments vs delivered scoring

**Tasks:**
- TASK-046 — Role-based dashboards and enhanced graphs (Partially Implemented, High)
- TASK-047 — Geographic visualization with factory markers (Partially Implemented — Needs Verification, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 13 — Dashboard & Analytics. See that file in the repository root for the complete source analysis._
MAW_MODULE_13,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Reporting'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-046',
                        'number' => 46,
                        'title' => 'Role-based dashboards and enhanced graphs',
                        'description' => <<<'MAW_TASK_046'
**Source Task ID:** TASK-046 (`Mother-At-Work_TASKS.md`, MODULE 13 — Dashboard & Analytics)
**Source Requirement ID(s):** REV-JUN-013 · CR-002 · COM-022
**Source Document(s):** DOC-04 Meeting Minutes 29 Jun · DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** In Progress · High · Feature

- **Module:** Dashboard & Analytics
- **Type:** Implementation
- **Source:** REV-JUN-013 · CR-002 (TOR §3a) · COM-022 (SRS FR-DA-001) · COM (Inception Module 5)
- **Classification:** **Client Requirement + Our Commitment + Review** (COMBINED)
- **Priority:** High
- **Status:** **Partially Implemented**
- **Requirement:** June 29: *"Enhance Dashboard graphs."* TOR §3(a) requires dynamic role-based
  dashboards. SRS FR-DA-001 commits to four distinct dashboards — National (UNICEF/Government),
  Partner (BGMEA/BKMEA), Factory (RMG managers) and Advisor (field staff) — each with a named tile set.
- **Current Implementation:** The graph enhancement is delivered: a configurable
  `dashboard_chart_configs` entity with per-form chart CRUD, chart type/group-by/statistic enums,
  Highcharts rendering, a dynamic-charts endpoint, and eleven analytics endpoints covering factory
  lists, geo lists, district statistics, monthly average score, women-workers coverage, quarterly
  assessments and quiz statistics, with a dedicated dashboard cache service and cache-flush hooks on
  model writes. **Role-based dashboard *variants* are not evidenced** — a single `Dashboard.vue`
  serves the dashboard, and data is scoped by association rather than composed into four distinct
  role dashboards. Several committed tiles have no data source: enrolled/active/**certified** counts
  (TASK-012), top/bottom performers and rankings (TASK-024), advisor visit history (TASK-049).
- **Gap:** Distinct per-role dashboard compositions; tiles blocked on Report Card scoring and factory
  lifecycle states.
- **Evidence:**
  - `database/migrations/2026_07_09_000001_create_dashboard_chart_configs_table.php`, `2026_07_29_131706_add_created_by_...`
  - `app/Models/Dashboard/DashboardChartConfig.php`; `app/Enums/DashboardChart{Type,GroupBy,Statistic}.php`
  - `routes/api.php:48-63` — eleven dashboard analytics endpoints
  - `app/Http/Controllers/Dashboard/DashboardApiController.php`; `app/Services/Dashboard/DashboardCacheService.php`
  - `app/Models/Monitoring/Monitoring.php:64-69`, `app/Models/LMS/QuizAttempt.php:33-38` — cache flush on write
  - `resources/js/pages/Dashboard.vue`; `resources/js/components/charts/`
  - `tests/Feature/DashboardTest.php`, `DashboardApiCacheTest.php`, `DashboardMonthlyAverageScoreTest.php`, `DashboardQuarterlyIndustryAssessmentsTest.php`, `tests/Feature/Dashboard/DashboardChartConfigTest.php`
- **Dependencies:** TASK-012, TASK-024, TASK-049, TASK-007
- **Acceptance Criteria:**
  - [x] Configurable charts with multiple types and groupings
  - [x] Analytics endpoints cached and invalidated on write
  - [ ] Each of the four committed role dashboards renders its specified tile set, or the reduction is agreed with UNICEF in writing
  - [ ] Drill-down national → division → district → factory (SRS FR-DA-003)

#### Source Traceability

- Matrix CR-002: Role-based dashboards + customizable scorecards (TOR §3a) — Partially Implemented — `dashboard_chart_configs`; no per-role dashboards
- Matrix COM-022: Four role-based dashboards (SRS FR-DA-001) — Partially Implemented — Single dashboard, association-scoped
- Matrix REV-JUN-013: Enhance dashboard graphs (June 29) — Implemented — `dashboard_chart_configs` + Highcharts
- CONFLICT-07: Report Card commitments vs delivered scoring — clarification: Which FR-ME-001/003 elements remain in scope, given the September focus shifted toward checklist-based reports?
- Recommended sequence: Phase 6 — Outstanding Commitments
MAW_TASK_046,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 29 Jun', 'Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-047',
                        'number' => 47,
                        'title' => 'Geographic visualization with factory markers',
                        'description' => <<<'MAW_TASK_047'
**Source Task ID:** TASK-047 (`Mother-At-Work_TASKS.md`, MODULE 13 — Dashboard & Analytics)
**Source Requirement ID(s):** COM-023 · CR-006
**Source Document(s):** DOC-02 SRS · DOC-03 Inception Report · DOC-01 TOR
**Original Status:** Partially Implemented — Needs Verification · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** In Progress · Medium · Feature

- **Module:** Dashboard & Analytics
- **Type:** Implementation
- **Source:** COM-023 (SRS FR-DA-004; Inception Module 4 "Integrated Map") · CR-006
- **Classification:** Our Commitment
- **Priority:** Medium
- **Status:** **Partially Implemented** — **Needs Verification**
- **Requirement:** SRS FR-DA-004 commits to a Bangladesh map with factory markers, colour-coded by
  performance tier, filterable, clickable for factory detail, with marker clustering. Inception
  Module 4 commits to an *"Integrated Map to display RMG's Location with Score and Status."*
- **Current Implementation:** The data layer is in place — `industries.latitude`/`longitude`,
  `monitorings.lat`/`lon`, division and district reference tables with seeders, and two geo endpoints
  (`industry-geo-list`, `district-industry-stats`). A Factory Monitoring Progress report exists with
  export. Whether the committed interactive map with clustering and tier colouring is rendered needs
  visual confirmation — no mapping library appears among the frontend dependencies, which suggests the
  geographic view may be tabular or chart-based rather than a true map.
- **Gap:** Confirm presence of the interactive map; colour-coding depends on performance tiers
  (TASK-024).
- **Evidence:**
  - `database/migrations/2025_11_26_075606_create_industries_table.php` — `latitude`, `longitude`
  - `database/migrations/2026_07_17_000000_add_lat_lon_to_monitorings_table.php`
  - `database/migrations/2025_11_27_134144_create_divisions_table.php`, `..._create_districts_table.php`; `DivisionSeeder`, `DistrictSeeder`
  - `routes/api.php:55-57` — `industry-geo-list`, `district-industry-stats`
  - `app/Services/Reports/GeographicalCoverageService.php`; `resources/js/pages/dashboard/reports/geographical-coverage/Index.vue`
  - `package.json` — no leaflet/mapbox/google-maps dependency
- **Dependencies:** TASK-024, TASK-046
- **Acceptance Criteria:**
  - [ ] Confirm whether an interactive map is rendered; if not, agree scope with UNICEF
  - [ ] Markers colour-coded by performance tier
  - [ ] Click-through to factory detail; clustering in dense areas
  - [ ] Filterable by compliance, size and association

#### Source Traceability

- Matrix CR-006: Interactive visualization with drill-down (TOR §3c) — Partially Implemented — Highcharts + chart configs; no drill-down
- Matrix COM-023: Geographic map with markers (SRS FR-DA-004) — Partially Implemented — Geo data + endpoints; map unconfirmed
- Recommended sequence: Phase 6 — Outstanding Commitments
MAW_TASK_047,
                        'source_status' => 'Partially Implemented — Needs Verification',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: SRS Commitment', 'Source: Inception Commitment', 'Source: Client Requirement (ToR)', 'Needs Review'],
                    ],
                ],
            ],
            [
                'module_num' => 14,
                'name' => '14 — Advisory Visits & Self-Monitoring',
                'description' => <<<'MAW_MODULE_14'
**Source Module:** MODULE 14 — Advisory Visits & Self-Monitoring (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-048, TASK-049 (2)
**Requirement Sources:** CR-014 · COM-024 · CR-013 · COM-025
**Original Status Breakdown:** Partially Implemented: 2

**Related Conflicts:**
- CONFLICT-06 — Monthly self-monitoring cadence vs quarterly implementation

**Tasks:**
- TASK-048 — Employer self-monitoring (Partially Implemented, High)
- TASK-049 — Advisory visit scheduling, checklists and outcomes (Partially Implemented, High)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 14 — Advisory Visits & Self-Monitoring. See that file in the repository root for the complete source analysis._
MAW_MODULE_14,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Web Application', 'Mobile Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-048',
                        'number' => 48,
                        'title' => 'Employer self-monitoring',
                        'description' => <<<'MAW_TASK_048'
**Source Task ID:** TASK-048 (`Mother-At-Work_TASKS.md`, MODULE 14 — Advisory Visits & Self-Monitoring)
**Source Requirement ID(s):** CR-014 · COM-024
**Source Document(s):** DOC-01 TOR · DOC-02 SRS
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** In Progress · High · Feature

- **Module:** M&E
- **Type:** Implementation
- **Source:** CR-014 (TOR §1(iii)) · COM-024 (SRS FR-ME-007, FR-ME-008)
- **Classification:** Client Requirement + Our Commitment (COMBINED)
- **Priority:** High
- **Status:** **Partially Implemented**
- **Requirement:** TOR §1(iii) names employer self-monitoring as one of three M&E modes. SRS FR-ME-007
  commits to a factory self-monitoring dashboard with own-scorecard view, supporting-document upload
  and a technical-assistance request; FR-ME-008 to monthly update requirements, real-time score
  updates and trend visualisation.
- **Current Implementation:** The building blocks exist: a factory-level guard and role, dynamic
  assessment forms, quarter assignments per factory, association-scoped data isolation, media upload,
  and a support-ticket system that could serve technical-assistance requests. What is not evidenced is
  a **dedicated self-monitoring surface for the factory manager** combining own scorecard, document
  upload and assistance request, nor a monthly cadence (the implemented cadence is quarterly).
- **Gap:** The factory-facing self-monitoring dashboard; own-scorecard view depends on TASK-024;
  monthly vs quarterly cadence conflicts with the delivered quarterly model.
- **Evidence:**
  - `config/auth.php` — `web` guard on `App\Models\User` with `rmg_id`; `database/migrations/2025_11_26_080054_alter_table_users_add_column_rmg_id.php`
  - `app/Services/RoleService.php:36,41` — `INDUSTRY`, `MANAGER` roles
  - `database/migrations/2026_07_02_160500_add_deadline_and_rmg_to_quarters_and_assignments.php` — per-factory assignment
  - `app/Models/Support.php`; `app/Services/SupportService.php`; `routes/dashboard.php` — support resource
  - `app/Traits/Media/HasMedia.php` — document upload capability
  - No `self-monitoring` route, controller or page in `routes/` or `resources/js/pages/`
- **Dependencies:** TASK-024, TASK-019
- **Acceptance Criteria:**
  - [ ] Factory manager dashboard showing own scorecard and standard-by-standard status
  - [ ] Supporting-document upload against a submission
  - [ ] Technical-assistance request routed into the ticket system
  - [ ] Monthly vs quarterly cadence resolved with UNICEF (currently quarterly)

#### Source Traceability

- Matrix CR-014: Employer self-monitoring (TOR §1(iii)) — Partially Implemented — Guards/roles/forms exist; no factory self-monitoring dashboard
- CONFLICT-06: Monthly self-monitoring cadence vs quarterly implementation — clarification: Confirm quarterly supersedes monthly for all monitoring modes, including employer self-monitoring.
- Open question Q-11: Does quarterly monitoring supersede the SRS monthly self-monitoring cadence for all modes?
- Recommended sequence: Phase 6 — Outstanding Commitments
MAW_TASK_048,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-049',
                        'number' => 49,
                        'title' => 'Advisory visit scheduling, checklists and outcomes',
                        'description' => <<<'MAW_TASK_049'
**Source Task ID:** TASK-049 (`Mother-At-Work_TASKS.md`, MODULE 14 — Advisory Visits & Self-Monitoring)
**Source Requirement ID(s):** CR-013 · COM-025
**Source Document(s):** DOC-01 TOR · DOC-02 SRS
**Original Status:** Partially Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** In Progress · High · Feature

- **Module:** M&E
- **Type:** Implementation
- **Source:** CR-013 (TOR §1(ii)) · COM-025 (SRS FR-ME-004/005/006; SRS Use Case 2)
- **Classification:** Client Requirement + Our Commitment (COMBINED)
- **Priority:** High
- **Status:** **Partially Implemented**
- **Requirement:** TOR §1(ii) names advisory visits and supportive supervision as a monitoring mode.
  SRS FR-ME-004 commits to visit scheduling with a calendar view, advisor assignment and visit types
  (initial assessment, follow-up, supportive supervision); FR-ME-005 to per-type checklists with
  rating scales and **photo evidence requirement**; FR-ME-006 to outcome tracking (completion status,
  average compliance score, visit frequency and coverage). Use Case 2 describes an advisor starting a
  visit with GPS and timestamp captured.
- **Current Implementation:** The **visit record** is essentially built — `Monitoring` captures GPS,
  date, factory, form, quarter, approval status and now the factory attendee/PoC; the mobile API
  supports assigned factories and form submission; `association_tasks` provides task assignment with
  checklist items, completions, comments, attachments and deadline reminders, which is close to
  supportive supervision. What is missing is the **visit-type taxonomy** (`FormType` has Assessment,
  Monitoring, Quiz, Survey — no initial/follow-up/supportive-supervision distinction), a **calendar
  view**, mandatory **photo evidence**, and the **outcome/coverage analytics**.
- **Gap:** Visit types, calendar scheduling UI, photo-evidence enforcement, coverage metrics.
- **Evidence:**
  - `app/Models/Monitoring/Monitoring.php:43-62`; `database/migrations/2026_07_17_000000_add_lat_lon_to_monitorings_table.php`
  - `app/Enums/FormType.php:15-32` — four types, none a visit type
  - `database/migrations/2026_07_06_150000_create_association_tasks_tables.php`; `app/Models/AssociationTask/`
  - `config/kaz.php` — `association_task` deadline reminders, comment attachments and limits
  - `routes/api.php` — `monitoring/assigned-industries`, `{type}/form-builder`
  - `app/Console/Commands/SendAssociationTaskDeadlineReminders.php`; `routes/console.php`
  - No calendar component in `resources/js/`; `Calendar.vue` is a date-picker primitive
- **Dependencies:** TASK-046, TASK-065
- **Acceptance Criteria:**
  - [ ] Visit types defined and selectable
  - [ ] Calendar view of scheduled visits with advisor assignment
  - [ ] Per-type checklists with rating scales
  - [ ] Photo evidence required where the checklist demands it
  - [ ] Coverage and frequency metrics on the advisor dashboard

#### Source Traceability

- Matrix CR-013: Advisory visits and supportive supervision (TOR §1(ii)) — Partially Implemented — `monitorings` + association tasks; no visit types/calendar
- Recommended sequence: Phase 6 — Outstanding Commitments
- Dependency `TASK-065` not linked: mobile-context reference to TASK-065 means the absent "mobile app release" task, as in TASK-014
MAW_TASK_049,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment'],
                    ],
                ],
            ],
            [
                'module_num' => 15,
                'name' => '15 — API & Mobile Application',
                'description' => <<<'MAW_MODULE_15'
**Source Module:** MODULE 15 — API & Mobile Application (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-050, TASK-051, TASK-052, TASK-053, TASK-054, TASK-055 (6)
**Requirement Sources:** REV-SEP-029 · REV-SEP-030 · REV-SEP-028 · BUG-003 · REVIEW-009 · REVIEW-010 · BUG-004 · REVIEW-011 · BUG-005 · REVIEW-012 · BUG-006
**Original Status Breakdown:** Not Implemented (partly blocked): 1 · Implemented — Needs Verification: 1 · Bug: 4

**Related Conflicts:**
- CONFLICT-08 — July 13 blocking dependency unresolved past its deadline

**Tasks:**
- TASK-050 — Reports on the mobile app (7 standard reports with export) (Not Implemented (partly blocked), High)
- TASK-051 — Report submission from the web app (Implemented — Needs Verification, High)
- TASK-052 — BUG: `POST /api/upload` routes to a non-existent controller method (Bug, High)
- TASK-053 — SECURITY: unauthenticated public file upload accepting SVG (Bug, Critical)
- TASK-054 — SECURITY: `uploadImageIcon` deletes an arbitrary caller-supplied path (Bug, Critical)
- TASK-055 — SECURITY: SCORM postback authorization fails open when the secret is unset (Bug, Critical)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 15 — API & Mobile Application. See that file in the repository root for the complete source analysis._
MAW_MODULE_15,
                'status' => 'in_progress',
                'priority' => 'critical',
                'scopes' => ['Mobile Application', 'API Integration'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-050',
                        'number' => 50,
                        'title' => 'Reports on the mobile app (7 standard reports with export)',
                        'description' => <<<'MAW_TASK_050'
**Source Task ID:** TASK-050 (`Mother-At-Work_TASKS.md`, MODULE 15 — API & Mobile Application)
**Source Requirement ID(s):** REV-SEP-029 · REV-SEP-030
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented (partly blocked) · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** Blocked · High · Feature

- **Module:** API & Mobile
- **Type:** Implementation
- **Source:** REV-SEP-029, REV-SEP-030
- **Classification:** **Review / Change — NEW REQUIREMENT**
- **Priority:** High
- **Status:** **Not Implemented** (partly blocked)
- **Requirement:** Sep 17: *"Reports on the Mobile App similar as the Web Admin Report - 7 Standard
  Report - no need to show all data columns, cumulative value for each 7 Standard, can have an export
  features"*, and *"Customer will provide report format which will be implemented on the Mobile App."*
- **Current Implementation:** The mobile API is substantial — versioned `/api/v1`, Sanctum auth,
  profile, monitoring (including assigned factories and form builder), quiz, materials/courses,
  notices, support tickets, association tasks, feed, locations, setup and eleven dashboard analytics
  endpoints. It exposes **no report endpoints at all**: a search of `routes/api.php` for report routes
  returns nothing.
- **Gap:** Report endpoints returning cumulative per-standard values, a reduced column set, and export.
  Blocked twice over — on UNICEF's web report format (TASK-025) and on the separate mobile report
  format the client promised.
- **Evidence:**
  - `routes/api.php` — 184 lines; no `report` route
  - `routes/api.php:48-63` — dashboard analytics only (aggregate statistics, not the 7 standard reports)
  - `app/Http/Controllers/Api/` — 11 subdirectories, none for reports
  - Web-side counterparts that would be mirrored: `app/Services/Reports/` (six services)
- **Dependencies:** TASK-025 (blocker), TASK-026, TASK-085
- **Acceptance Criteria:**
  - [ ] UNICEF supplies the mobile report format
  - [ ] Seven standard-level endpoints returning cumulative values
  - [ ] Column set reduced per the client's instruction
  - [ ] Export available from the mobile app
  - [ ] Association scoping and permissions enforced identically to the web reports

#### Source Traceability

- Matrix REV-SEP-029: Mobile 7-standard reports (Sep 17) — Not Implemented — No report endpoints
- CONFLICT-08: July 13 blocking dependency unresolved past its deadline — clarification: Has UNICEF delivered the format? If not, the schedule and any contractual milestone tied to reporting need renegotiation.
- Recommended sequence: Phase 5 — Reporting (starts on receipt of the format)
MAW_TASK_050,
                        'source_status' => 'Not Implemented (partly blocked)',
                        'status' => 'blocked',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 17 Sep', 'New Requirement'],
                    ],
                    [
                        'source_task_id' => 'TASK-051',
                        'number' => 51,
                        'title' => 'Report submission from the web app',
                        'description' => <<<'MAW_TASK_051'
**Source Task ID:** TASK-051 (`Mother-At-Work_TASKS.md`, MODULE 15 — API & Mobile Application)
**Source Requirement ID(s):** REV-SEP-028
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Implemented — Needs Verification · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** Ready for QA · High · Enhancement

- **Module:** API & Mobile / Monitoring
- **Type:** Enhancement
- **Source:** REV-SEP-028
- **Classification:** **Review / Change — NEW REQUIREMENT**
- **Priority:** High
- **Status:** **Implemented** — **Needs Verification**
- **Requirement:** Sep 17: *"Report Submission from Web App besides the Dedicated Mobile App."*
- **Current Implementation:** Web-based monitoring submission appears to exist already — the dashboard
  has a full monitoring module with create/edit/show screens, form requests, store and update actions,
  and a monitoring controller distinct from the API one. The same `MonitoringRequest` validates both
  paths, including the new factory-attendee fields.
- **Gap:** Needs confirmation that the web path is complete for the association/inspector role (not
  only for admin correction), and that GPS capture — natural on mobile — has a sensible web equivalent.
- **Evidence:**
  - `app/Http/Controllers/Dashboard/Monitoring/MonitoringController.php:138` — web store handling `factoryAttendeeName`
  - `app/Http/Requests/Monitoring/MonitoringRequest.php`, `UpdateMonitoringRequest.php` — shared validation
  - `app/Actions/Monitoring/{StoreMonitoringAction,UpdateMonitoringAction}.php`
  - `resources/js/pages/dashboard/monitoring/{Index,Show,Edit}.vue`
  - `routes/dashboard.php:88` — monitoring prefix behind `can:monitoring_access`
  - `tests/Feature/Dashboard/Monitoring/MonitoringControllerTest.php`
- **Dependencies:** TASK-014, TASK-065
- **Acceptance Criteria:**
  - [ ] Confirm an association inspector can complete a full submission from the web UI
  - [ ] GPS handled sensibly on web (optional, or map-picked)
  - [ ] Parity of validation and approval workflow between web and mobile paths

#### Source Traceability

- Matrix REV-SEP-028: Report submission from web (Sep 17) — Implemented — Dashboard monitoring module
- Dependency `TASK-065` not linked: mobile-context reference to TASK-065 means the absent "mobile app release" task, as in TASK-014
MAW_TASK_051,
                        'source_status' => 'Implemented — Needs Verification',
                        'status' => 'ready_for_qa',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'New Requirement', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-052',
                        'number' => 52,
                        'title' => 'BUG: `POST /api/upload` routes to a non-existent controller method',
                        'description' => <<<'MAW_TASK_052'
**Source Task ID:** TASK-052 (`Mother-At-Work_TASKS.md`, MODULE 15 — API & Mobile Application)
**Source Requirement ID(s):** BUG-003 · REVIEW-009
**Source Document(s):** Repository review (BUG) · Repository review (REVIEW)
**Original Status:** Bug · **Original Priority:** High · **Original Type:** Bug
**Imported As:** To Do · High · Bug

- **Module:** API
- **Type:** Bug
- **Source:** BUG-003 · REVIEW-009
- **Classification:** Bug / Defect
- **Priority:** **High**
- **Status:** **Bug**
- **Requirement:** Every registered route must resolve to a real handler.
- **Current Implementation:** `routes/api.php` registers
  `Route::post('upload', [FormBuilderController::class, 'upload'])->name('upload')` with a comment
  stating it is *"public to allow direct calls."* `FormBuilderController` has **no `upload` method** —
  its public methods are index, create, quizForMaterial, store, edit, update, inputs,
  uploadImageIcon, deleteImageIcon, destroy, restore, review, approve, decline. Any POST to
  `/api/upload` therefore throws at dispatch. Confirmed live:
  `php artisan route:list --path=api/upload` shows the route bound to
  `FormBuilderController@upload`.
- **Gap:** Either the method is missing or the route is vestigial. Being **unauthenticated**, it is an
  anonymous 500 generator and, if the method were added carelessly, would become a public upload
  endpoint.
- **Evidence:**
  - `routes/api.php:173-174` — the route and its "public" comment
  - `app/Http/Controllers/Dashboard/Form/FormBuilderController.php` — full public-method list, no `upload`
  - `php artisan route:list --path=api/upload` → `POST api/upload … FormBuilderController@upload`
- **Dependencies:** TASK-053, TASK-054
- **Acceptance Criteria:**
  - [ ] Determine whether the endpoint is needed; if not, remove the route
  - [ ] If needed, implement with authentication, strict MIME/size validation and rate limiting
  - [ ] A test asserts the route either 404s or behaves correctly — never 500s

#### Source Traceability

- Recommended sequence: Phase 1 — Security & Critical Defects
MAW_TASK_052,
                        'source_status' => 'Bug',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Defect', 'Source: Code Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-053',
                        'number' => 53,
                        'title' => 'SECURITY: unauthenticated public file upload accepting SVG',
                        'description' => <<<'MAW_TASK_053'
**Source Task ID:** TASK-053 (`Mother-At-Work_TASKS.md`, MODULE 15 — API & Mobile Application)
**Source Requirement ID(s):** REVIEW-010 · BUG-004
**Source Document(s):** Repository review (REVIEW) · Repository review (BUG) · DOC-02 SRS
**Original Status:** Bug · **Original Priority:** Critical · **Original Type:** Bug / Security
**Imported As:** To Do · Critical · Bug

- **Module:** API / Security
- **Type:** Bug / Security
- **Source:** REVIEW-010 · BUG-004 · COM (SRS NFR-SEC-008 XSS, NFR-SEC-009 file upload restrictions)
- **Classification:** Technical review — **security**
- **Priority:** **Critical**
- **Status:** **Bug**
- **Requirement:** SRS NFR-SEC-009 commits to file upload restrictions by type and size; NFR-SEC-008
  commits to XSS prevention through input sanitisation, output encoding and a Content Security Policy.
- **Current Implementation:** `POST /form/file-upload` is registered in `routes/web.php` **outside any
  auth middleware and with no throttle**. It validates
  `mimes:jpg,jpeg,png,gif,svg,webp,pdf,doc,docx,xls,xlsx|max:10240` and stores to the **public** disk,
  returning a public URL. Three problems compound:
  1. **SVG is accepted.** An SVG may contain `<script>`; served from the application's own origin via
     `/storage/...`, opening it executes script in that origin — stored XSS against any user who
     follows the link, with session cookies in scope.
  2. **No authentication or rate limit.** Anyone on the internet can store 10 MB files indefinitely —
     storage exhaustion and use of the platform as an anonymous file host.
  3. **No binding to a survey.** The upload is accepted regardless of whether any form is being filled.
- **Gap:** Authentication or a signed/scoped token, SVG removal or sanitisation, throttling, and
  ideally a non-public disk with signed URLs.
- **Evidence:**
  - `routes/web.php` — `Route::post('form/file-upload', [SurveyController::class, 'uploadFile'])->name('survey.file-upload')`, registered before the `auth` group and outside it
  - `app/Http/Controllers/Frontend/SurveyController.php:47-59` — validation including `svg`, then `->store('form-response', 'public')` and `Storage::url($path)`
  - `config/filesystems.php` — `public` disk root `storage/app/public`, URL `APP_URL . '/storage'`
  - `config/media.php` — the stricter MIME whitelist used elsewhere is **not** applied here
  - `bootstrap/app.php` — no global throttle on web routes
- **Dependencies:** TASK-052, TASK-054, TASK-092
- **Acceptance Criteria:**
  - [ ] `svg` removed from the accepted list, or SVGs sanitised server-side before storage
  - [ ] Endpoint authenticated, or bound to a valid survey via a signed URL
  - [ ] Rate limiting applied per IP and per session
  - [ ] Uploads stored on a non-public disk and served through signed, access-checked URLs
  - [ ] MIME verified from file content, not just the extension
  - [ ] A security test asserts an SVG containing a script tag is rejected

#### Source Traceability

- Recommended sequence: Phase 1 — Security & Critical Defects
- Dependency `TASK-092` not linked: TASK-092 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_053,
                        'source_status' => 'Bug',
                        'status' => 'todo',
                        'priority' => 'critical',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review', 'Source: Code Defect', 'Source: SRS Commitment', 'Security'],
                    ],
                    [
                        'source_task_id' => 'TASK-054',
                        'number' => 54,
                        'title' => 'SECURITY: `uploadImageIcon` deletes an arbitrary caller-supplied path',
                        'description' => <<<'MAW_TASK_054'
**Source Task ID:** TASK-054 (`Mother-At-Work_TASKS.md`, MODULE 15 — API & Mobile Application)
**Source Requirement ID(s):** REVIEW-011 · BUG-005
**Source Document(s):** Repository review (REVIEW) · Repository review (BUG)
**Original Status:** Bug · **Original Priority:** Critical · **Original Type:** Bug / Security
**Imported As:** To Do · Critical · Bug

- **Module:** API / Security
- **Type:** Bug / Security
- **Source:** REVIEW-011 · BUG-005
- **Classification:** Technical review — **security (IDOR / arbitrary file deletion)**
- **Priority:** **Critical**
- **Status:** **Bug**
- **Requirement:** SRS NFR-SEC-004 commits to resource-level permissions — *"user can only access own
  data"* — and authorisation checks on every API request.
- **Current Implementation:** `FormBuilderController::uploadImageIcon()` reads `oldPath` **straight
  from the request** and deletes it from the public disk if it exists, with **no validation that the
  path belongs to the caller, or even that it sits under the `form-icons/` prefix**:
  `$oldPath = $request->input('oldPath'); if ($oldPath && Storage::disk('public')->exists($oldPath)) { Storage::disk('public')->delete($oldPath); }`.
  Any authenticated user who can reach this endpoint can delete **any file on the public disk** —
  survey response uploads, editor images, site logos, gallery assets — by naming its path. The route
  sits inside the `auth:sanctum` setup group, so this requires a valid token but no special privilege.
- **Gap:** Ownership and prefix validation before deletion.
- **Evidence:**
  - `app/Http/Controllers/Dashboard/Form/FormBuilderController.php:243-247` — the unvalidated delete
  - `app/Http/Controllers/Dashboard/Form/FormBuilderController.php:250` — new file stored under `form-icons`, showing the intended scope
  - `routes/api.php` — `setup/dynamic-form/upload-file` → `FormBuilderController@uploadImageIcon`, inside the `auth:sanctum` group; the sibling `dynamic-form/delete-file` → `deleteImageIcon` warrants the same check
  - `config/filesystems.php` — `public` disk shared by media, editor images and survey uploads
- **Dependencies:** TASK-053, TASK-092
- **Acceptance Criteria:**
  - [ ] `oldPath` validated to sit under the expected prefix, with `..` traversal rejected
  - [ ] Deletion permitted only for a file the caller owns or is authorised to manage
  - [ ] Same validation applied to `deleteImageIcon`
  - [ ] A security test asserts deleting a path outside `form-icons/` is refused

#### Source Traceability

- Recommended sequence: Phase 1 — Security & Critical Defects
- Dependency `TASK-092` not linked: TASK-092 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_054,
                        'source_status' => 'Bug',
                        'status' => 'todo',
                        'priority' => 'critical',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review', 'Source: Code Defect', 'Security'],
                    ],
                    [
                        'source_task_id' => 'TASK-055',
                        'number' => 55,
                        'title' => 'SECURITY: SCORM postback authorization fails open when the secret is unset',
                        'description' => <<<'MAW_TASK_055'
**Source Task ID:** TASK-055 (`Mother-At-Work_TASKS.md`, MODULE 15 — API & Mobile Application)
**Source Requirement ID(s):** REVIEW-012 · BUG-006
**Source Document(s):** Repository review (REVIEW) · Repository review (BUG)
**Original Status:** Bug · **Original Priority:** Critical · **Original Type:** Bug / Security
**Imported As:** To Do · Critical · Bug

- **Module:** API / Security / LMS
- **Type:** Bug / Security
- **Source:** REVIEW-012 · BUG-006
- **Classification:** Technical review — **security (authentication bypass)**
- **Priority:** **Critical**
- **Status:** **Bug**
- **Requirement:** SRS NFR-SEC-011 commits to API authentication on secured endpoints; NFR-SEC-004 to
  authorisation checks on every API request.
- **Current Implementation:** The two SCORM postback endpoints are deliberately outside
  `auth:sanctum`, protected only by a shared secret. `ScormPostbackController::isAuthorized()` begins:
  `$secret = (string) config('app.scorm_postback_secret'); if ($secret === '') { return true; }` —
  **an empty secret authorises every caller**. The comparison logic that follows is sound
  (`hash_equals` for both the query token and HTTP basic credentials), which makes the fail-open branch
  the single weak point. `.env.example` ships `SCORM_POSTBACK_SECRET=` **empty**, so a deployment that
  copies the example and does not set it is wide open by default. These endpoints mutate course import
  state and registration/completion data, so an anonymous caller could forge course completions or
  corrupt import status.
- **Gap:** Fail-closed behaviour when the secret is missing.
- **Evidence:**
  - `app/Http/Controllers/Api/Scorm/ScormPostbackController.php:83-99` — `isAuthorized()` with the `return true` on empty secret
  - `routes/api.php:176-183` — the SCORM group registered **outside** the `auth:sanctum` group, with a comment noting it is unauthenticated except for the token/basic secret
  - `.env.example` — `SCORM_POSTBACK_SECRET=` (empty)
  - `app/Jobs/LMS/ImportMaterialCourse.php`; `app/Services/CourseImportService.php`; `database/migrations/2026_09_07_191200_add_import_status_to_materials_table.php` — the state these postbacks mutate
- **Dependencies:** TASK-092, TASK-095
- **Acceptance Criteria:**
  - [ ] Empty secret rejects the request (fail closed) and logs a configuration error
  - [ ] `SCORM_POSTBACK_SECRET` documented as mandatory and set in every environment
  - [ ] Deployment checklist verifies it is non-empty before go-live
  - [ ] A test asserts an unauthenticated postback is refused when the secret is unset

#### Source Traceability

- Recommended sequence: Phase 1 — Security & Critical Defects
- Dependency `TASK-092` not linked: TASK-092 does not exist in the register (TASK-001 to TASK-085)
- Dependency `TASK-095` not linked: TASK-095 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_055,
                        'source_status' => 'Bug',
                        'status' => 'todo',
                        'priority' => 'critical',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review', 'Source: Code Defect', 'Security'],
                    ],
                ],
            ],
            [
                'module_num' => 16,
                'name' => '16 — Integration & Interoperability',
                'description' => <<<'MAW_MODULE_16'
**Source Module:** MODULE 16 — Integration & Interoperability (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-056, TASK-057, TASK-058, TASK-059 (4)
**Requirement Sources:** CR-004 · COM-026 · COM-027 · COM-028 · COM-029
**Original Status Breakdown:** Not Implemented: 3 · Partially Implemented: 1

**Related Conflicts:**
- CONFLICT-09 — BGMEA/BKMEA API specifications never received
- CONFLICT-07 — Report Card commitments vs delivered scoring

**Tasks:**
- TASK-056 — BGMEA / BKMEA bidirectional data synchronization API (Not Implemented, Critical)
- TASK-057 — DIFE data provision (Not Implemented, High)
- TASK-058 — Webhook notifications to external systems (Not Implemented, Medium)
- TASK-059 — API rate limiting, versioning and documentation (Partially Implemented, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 16 — Integration & Interoperability. See that file in the repository root for the complete source analysis._
MAW_MODULE_16,
                'status' => 'in_progress',
                'priority' => 'critical',
                'scopes' => ['API Integration'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-056',
                        'number' => 56,
                        'title' => 'BGMEA / BKMEA bidirectional data synchronization API',
                        'description' => <<<'MAW_TASK_056'
**Source Task ID:** TASK-056 (`Mother-At-Work_TASKS.md`, MODULE 16 — Integration & Interoperability)
**Source Requirement ID(s):** CR-004 · COM-026
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Not Implemented · **Original Priority:** Critical · **Original Type:** Implementation
**Imported As:** To Do · Critical · Feature

- **Module:** Integration & Interoperability
- **Type:** Implementation
- **Source:** CR-004 (TOR §3b) · COM-026 (SRS FR-INT-002; Inception Module 7, §3.5, §14.3.1/14.3.2)
- **Classification:** **Client Requirement + Our Commitment** (COMBINED)
- **Priority:** **Critical**
- **Status:** **Not Implemented**
- **Requirement:** TOR §3(b): *"The Mothers@Work ME system will include a robust data synchronization
  API to enable interoperability with external data systems, such as those of BGMEA, BKMEA, and DIFE,
  allowing bidirectional data exchange."* SRS FR-INT-002 commits to factory master-data sync
  (name, address, contact, membership status, size/workforce), enrollment data exchange (new
  enrollments and member verification from the associations into M@W) and performance-summary sharing
  (compliance scores, no individual worker data). Inception §14.3.1–14.3.2 carries full BGMEA and
  BKMEA integration specifications; Module 7 commits to a bidirectional API.
- **Current Implementation:** **None.** BGMEA and BKMEA exist only as `association_types` reference
  rows and as the `Association` guard/role — that is internal user organisation, not system
  integration. There is no outbound client, no sync job, no inbound integration endpoint, no
  credential configuration, and no mapping of external factory identifiers. A repository-wide search
  for BGMEA/BKMEA across `app/`, `routes/` and `config/` returns only a role comment and an API
  docblock describing the association-type hash id.
- **Gap:** The entire integration. The SRS §2.6.1 assumption — *"BGMEA and BKMEA will provide API
  specifications within 2 weeks of project start"* — appears unmet, which is the likely root cause and
  should be escalated rather than silently absorbed.
- **Evidence:**
  - `app/Services/RoleService.php:30` — comment *"Role name Association for BGMEA, BKMEA and others"*
  - `app/Http/Controllers/Api/Profile/IndustryController.php:28` — docblock referencing BGMEA/BKMEA as an association-type value
  - `database/migrations/2025_11_26_074405_create_association_types_table.php`; `database/seeders/AssociationTypeSeeder.php`
  - `config/services.php` — third-party credentials for youtube and firebase only; no association endpoints
  - `routes/api.php` — no integration or sync routes
  - No `app/Services/Integration/` or equivalent; no sync job in `app/Jobs/`
- **Dependencies:** TASK-057, TASK-058, TASK-086 (client dependency)
- **Acceptance Criteria:**
  - [ ] BGMEA and BKMEA API specifications obtained, or the dependency formally escalated and the scope renegotiated
  - [ ] Factory master data synchronised, with conflict rules agreed
  - [ ] Enrollment and member verification consumed from the associations
  - [ ] Performance summaries shared outward, excluding individual worker data
  - [ ] Credentials stored in configuration, never hardcoded; OAuth 2.0 or API key per SRS §4.3.2
  - [ ] Sync failures retried and alerted

#### Source Traceability

- Matrix CR-004: Data sync API — BGMEA/BKMEA/DIFE interoperability (TOR §3b) — Not Implemented — No integration code found
- Matrix COM-026: BGMEA/BKMEA bidirectional integration (SRS FR-INT-002) — Not Implemented — Association types only
- CONFLICT-09: BGMEA/BKMEA API specifications never received — clarification: Escalate formally: have the specifications been provided? If not, renegotiate scope or agree a stub/manual-exchange interim.
- Open question Q-14: Have the BGMEA/BKMEA API specifications been provided? If not, is interoperability descoped or deferred?
- Recommended sequence: Phase 2 — Escalate Blockers (parallel with Phase 1; no development)
- Recommended sequence: Phase 7 — Integration
- Dependency `TASK-086 (client dependency)` not linked: TASK-086 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_056,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'critical',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-057',
                        'number' => 57,
                        'title' => 'DIFE data provision',
                        'description' => <<<'MAW_TASK_057'
**Source Task ID:** TASK-057 (`Mother-At-Work_TASKS.md`, MODULE 16 — Integration & Interoperability)
**Source Requirement ID(s):** CR-004 · COM-027
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Not Implemented · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** To Do · High · Feature

- **Module:** Integration & Interoperability
- **Type:** Implementation
- **Source:** CR-004 (TOR §3b) · COM-027 (SRS FR-INT-004; Inception §14.3.3)
- **Classification:** Client Requirement + Our Commitment (COMBINED)
- **Priority:** High
- **Status:** **Not Implemented**
- **Requirement:** SRS FR-INT-004 commits to providing DIFE with aggregate standards statistics, number
  of factories by region and worker coverage numbers; SRS §4.3.2 specifies a REST/JSON interface with
  API-key authentication on a monthly push cadence.
- **Current Implementation:** None. The **data** for all three payload elements exists — aggregate
  standards statistics from monitoring, factories by division/district, and worker coverage from the
  industry workforce fields and the women-workers-coverage endpoint — but there is no DIFE client,
  endpoint, scheduled push or credential configuration. The scheduler runs four tasks, none of them an
  outbound DIFE push.
- **Gap:** The DIFE interface and its monthly schedule.
- **Evidence:**
  - Repository-wide search for `dife` in `app/`, `routes/`, `config/` returns no matches
  - `routes/console.php` — four scheduled tasks: `app:clear-upload-temp`, `cache:prune-expired`, `notices:send-scheduled`, `association-tasks:send-deadline-reminders`
  - `routes/api.php:59` — `women-workers-coverage` (the coverage data that would be pushed)
  - `database/migrations/2026_07_06_200000_add_workforce_fields_to_industries_table.php` — worker counts
- **Dependencies:** TASK-056, TASK-086
- **Acceptance Criteria:**
  - [ ] DIFE endpoint specification and credentials obtained
  - [ ] Aggregate-only payload — no factory-identifying or worker-level data beyond what is agreed
  - [ ] Monthly scheduled push with retry and failure alerting
  - [ ] Payload contents approved by UNICEF against DPIA constraints

#### Source Traceability

- Matrix CR-004: Data sync API — BGMEA/BKMEA/DIFE interoperability (TOR §3b) — Not Implemented — No integration code found
- Matrix COM-027: DIFE data provision (SRS FR-INT-004) — Not Implemented — No DIFE code
- CONFLICT-09: BGMEA/BKMEA API specifications never received — clarification: Escalate formally: have the specifications been provided? If not, renegotiate scope or agree a stub/manual-exchange interim.
- Recommended sequence: Phase 7 — Integration
- Dependency `TASK-086` not linked: TASK-086 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_057,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-058',
                        'number' => 58,
                        'title' => 'Webhook notifications to external systems',
                        'description' => <<<'MAW_TASK_058'
**Source Task ID:** TASK-058 (`Mother-At-Work_TASKS.md`, MODULE 16 — Integration & Interoperability)
**Source Requirement ID(s):** COM-028
**Source Document(s):** DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Not Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** To Do · Medium · Feature

- **Module:** Integration & Interoperability
- **Type:** Implementation
- **Source:** COM-028 (SRS FR-INT-003; Inception Module 7)
- **Classification:** **Our Commitment — COMMITMENT EXTENSION** (webhooks are not named in the TOR)
- **Priority:** Medium
- **Status:** **Not Implemented**
- **Requirement:** SRS FR-INT-003: webhook notifications for factory status changes (enrolled,
  certified, suspended), compliance score updates and report submissions, with configurable endpoints
  and a retry mechanism for failed deliveries.
- **Current Implementation:** None. The only `webhook_url` strings in the project belong to
  `spatie/laravel-backup`'s Slack/Discord notification config — unrelated to this commitment. Two of
  the three trigger events also lack a data source: factory lifecycle states (TASK-012) and compliance
  score updates (TASK-024).
- **Gap:** Webhook subscription model, dispatcher, signing, and retry with backoff.
- **Evidence:**
  - `config/backup.php:228,241` — the unrelated backup notification webhooks
  - No webhook model, migration, job or controller anywhere in `app/`
  - `database/migrations/` — no webhook subscription table
- **Dependencies:** TASK-012, TASK-024, TASK-056
- **Acceptance Criteria:**
  - [ ] Configurable webhook endpoints per subscriber
  - [ ] Events dispatched for the three committed triggers
  - [ ] Payloads signed so receivers can verify authenticity
  - [ ] Failed deliveries retried with backoff and surfaced for support

#### Source Traceability

- Matrix COM-028: Webhook notifications (SRS FR-INT-003) — Not Implemented — Only backup Slack webhooks
- CONFLICT-07: Report Card commitments vs delivered scoring — clarification: Which FR-ME-001/003 elements remain in scope, given the September focus shifted toward checklist-based reports?
- CONFLICT-09: BGMEA/BKMEA API specifications never received — clarification: Escalate formally: have the specifications been provided? If not, renegotiate scope or agree a stub/manual-exchange interim.
- Recommended sequence: Phase 7 — Integration
MAW_TASK_058,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: SRS Commitment', 'Source: Inception Commitment', 'Commitment Extension'],
                    ],
                    [
                        'source_task_id' => 'TASK-059',
                        'number' => 59,
                        'title' => 'API rate limiting, versioning and documentation',
                        'description' => <<<'MAW_TASK_059'
**Source Task ID:** TASK-059 (`Mother-At-Work_TASKS.md`, MODULE 16 — Integration & Interoperability)
**Source Requirement ID(s):** COM-029
**Source Document(s):** DOC-02 SRS
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Implementation / Review
**Imported As:** In Progress · Medium · Feature

- **Module:** API
- **Type:** Implementation / Review
- **Source:** COM-029 (SRS FR-INT-001, NFR-SEC-011, NFR-MAINT-001, NFR-INTER-001)
- **Classification:** Our Commitment
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** SRS FR-INT-001 commits to RESTful APIs with standard CRUD, JSON, **API versioning**,
  **rate limiting**, **request/response logging** and **API documentation**. NFR-SEC-011 adds request
  size limits, a CORS allowlist and error messages that do not reveal system internals.
- **Current Implementation:** Versioning is done — every route sits under `/api/v1` with `api.v1.*`
  names. Request/response logging is partly covered by `irabbi360/laravel-api-inspector` with its own
  analytics table. Controllers carry structured docblocks describing payloads, which is a documentation
  seed but not published API documentation. **Rate limiting is not evidenced** — no `throttle`
  middleware appears on the API groups and no `RateLimiter::for` definition was found; note the OTP
  module *does* define its own rate limits in `config/kaz.php`, so the gap is specific to the general
  API. No CORS allowlist configuration is present.
- **Gap:** Throttling on API routes (especially the unauthenticated login, forgot-password and SCORM
  postback paths), a published API reference, and an explicit CORS policy.
- **Evidence:**
  - `routes/api.php:28,37` — `Route::prefix('v1')->name('api.v1.')` for public and authenticated groups
  - `bootstrap/app.php:19-46` — middleware configuration; Sanctum stateful and CSRF exclusions, **no throttle**
  - `config/api-inspector.php`; `database/migrations/2026_01_09_061529_create_api_inspector_analytics_table.php`
  - `config/kaz.php` — `rate_limiting` block scoped to OTP only
  - `app/Http/Controllers/Api/Monitoring/MonitoringController.php:183` — docblock-style payload documentation
  - No `config/cors.php` present
- **Dependencies:** TASK-053, TASK-055, TASK-092
- **Acceptance Criteria:**
  - [ ] Throttling on all API routes, tighter on unauthenticated endpoints
  - [ ] Login and forgot-password rate-limited against credential stuffing
  - [ ] Published API documentation for the mobile team and integration partners
  - [ ] CORS allowlist configured explicitly
  - [ ] Error responses carry no stack traces or system detail in production

#### Source Traceability

- Matrix COM-029: API versioning, rate limiting, docs (SRS FR-INT-001) — Partially Implemented — `/api/v1` + inspector; no throttle
- Recommended sequence: Phase 1 — Security & Critical Defects
- Dependency `TASK-092` not linked: TASK-092 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_059,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: SRS Commitment'],
                    ],
                ],
            ],
            [
                'module_num' => 17,
                'name' => '17 — Support & Help Desk',
                'description' => <<<'MAW_MODULE_17'
**Source Module:** MODULE 17 — Support & Help Desk (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-060, TASK-061 (2)
**Requirement Sources:** CR-008 · COM-030 · CR-007 · COM-031
**Original Status Breakdown:** Partially Implemented: 2

**Tasks:**
- TASK-060 — Ticket system, workflow and analytics (Partially Implemented, Medium)
- TASK-061 — Help centre, knowledge base and in-app tooltips (Partially Implemented, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 17 — Support & Help Desk. See that file in the repository root for the complete source analysis._
MAW_MODULE_17,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-060',
                        'number' => 60,
                        'title' => 'Ticket system, workflow and analytics',
                        'description' => <<<'MAW_TASK_060'
**Source Task ID:** TASK-060 (`Mother-At-Work_TASKS.md`, MODULE 17 — Support & Help Desk)
**Source Requirement ID(s):** CR-008 · COM-030
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** In Progress · Medium · Feature

- **Module:** Support & Help Desk
- **Type:** Implementation
- **Source:** CR-008 (TOR §3d) · COM-030 (SRS FR-SH-003/004/005; Inception Module 8, §7.3.1)
- **Classification:** Client Requirement + Our Commitment (COMBINED)
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** TOR §3(d) requires a ticket-based support system for issue resolution. SRS FR-SH-003
  commits to tickets with subject, description, category, priority, attachments and affected module;
  FR-SH-004 to auto-assignment by category, reassignment, status tracking, **SLA response-time
  tracking**, email notification on status change, user comments and internal agent notes; FR-SH-005 to
  ticket analytics (volume by category/priority/role, average resolution time, common issues, export).
- **Current Implementation:** The core exists — a `supports` table, model, service with role-based
  visibility (Super Admin and Admin see all), a resource controller, a policy, priority and status
  enums, and a mobile API surface. Not evidenced: auto-assignment by category, SLA response-time
  tracking, internal-only agent notes, and the FR-SH-005 analytics set.
- **Gap:** Assignment automation, SLA measurement and ticket analytics — the last of which matters
  because the Inception SLA section commits to reporting on response times.
- **Evidence:**
  - `database/migrations/2026_07_02_000001_create_supports_table.php`; `app/Models/Support.php`
  - `app/Enums/SupportPriority.php`, `app/Enums/SupportStatus.php`
  - `app/Services/SupportService.php:23,83` — Super Admin/Admin see all tickets
  - `app/Policies/SupportPolicy.php`; `routes/dashboard.php` — `Route::resource('support', ...)`
  - `routes/api.php` — `App\Http\Controllers\Api\SupportTicket\SupportTicketController`
  - `resources/js/pages/dashboard/support/`
  - No SLA timestamp columns on `supports`; no ticket analytics page or service
- **Dependencies:** TASK-061, TASK-090
- **Acceptance Criteria:**
  - [ ] Auto-assignment by category, with manual reassignment
  - [ ] First-response and resolution times recorded against the SLA targets in the Inception report
  - [ ] Internal notes distinguishable from user-visible comments
  - [ ] Ticket analytics with export
  - [ ] Status-change emails delivered

#### Source Traceability

- Matrix CR-008: Ticket-based support system (TOR §3d) — Partially Implemented — `supports` + service + policy; no SLA tracking
- Matrix COM-030: Ticket workflow + SLA + analytics (SRS FR-SH-003/004/005) — Partially Implemented — Core tickets; no SLA or analytics
- Recommended sequence: Phase 6 — Outstanding Commitments
- Dependency `TASK-090` not linked: TASK-090 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_060,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-061',
                        'number' => 61,
                        'title' => 'Help centre, knowledge base and in-app tooltips',
                        'description' => <<<'MAW_TASK_061'
**Source Task ID:** TASK-061 (`Mother-At-Work_TASKS.md`, MODULE 17 — Support & Help Desk)
**Source Requirement ID(s):** CR-007 · COM-031
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** In Progress · Medium · Feature

- **Module:** Support & Help Desk
- **Type:** Implementation
- **Source:** CR-007 (TOR §3d in-app guidance and tooltips) · COM-031 (SRS FR-SH-001/002; Inception Module 8, §7.2.3)
- **Classification:** Client Requirement + Our Commitment (COMBINED)
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** TOR §3(d): *"Incorporate in-app guidance, tooltips, and user feedback loops to
  improve usability."* SRS FR-SH-001 commits to a searchable knowledge base, FAQ organised by topic and
  **context-sensitive help for the current page**; FR-SH-002 to tooltips on hover/tap.
- **Current Implementation:** Tooltips are available — a global PrimeVue `Tooltip` directive is
  registered, and dynamic form inputs carry a `hints` column, so field-level guidance is supported. A
  substantial user manual is bundled (`resources/user-manual/1.0/{en,bn}`, ~59 topics each) served at
  `/user-manual` with permission mapping. What is missing against FR-SH-001 is a **searchable** FAQ
  knowledge base and **context-sensitive** help that opens the topic for the page the user is on.
- **Gap:** Search over help content; page-to-topic binding; a distinct FAQ structure.
- **Evidence:**
  - `resources/js/app.ts:39` — `app.directive('tooltip', Tooltip)`
  - `database/migrations/2025_12_11_000002_add_hints_to_dynamic_form_inputs.php`
  - `resources/user-manual/1.0/en/` and `/bn/` — bilingual topic set including `introduction`, `navigation`, `dashboard`, `monitoring`, `roles-matrix`, `custom-reports`, `support-ticket`
  - `config/user-manual.php:10-11` — `route_prefix` `user-manual`, `route_name` `user-manual.show`; `:22` — `content_path` `resource_path('user-manual')`; `:39` — `cache_prefix`
  - `resources/user-manual/MAINTENANCE.md`; `scripts/generate-docs-from-html.py`
- **Dependencies:** TASK-062, TASK-060
- **Acceptance Criteria:**
  - [ ] Help content searchable from within the app
  - [ ] Context-sensitive help opens the topic matching the current page
  - [ ] FAQ section organised by topic
  - [ ] Tooltips present on complex form fields, bilingual

#### Source Traceability

- Matrix CR-007: Training, e-learning, in-app guidance (TOR §3d) — Partially Implemented — LMS + SCORM + tooltips; no searchable help
- Matrix COM-031: Help centre + context-sensitive help (SRS FR-SH-001/002) — Partially Implemented — Manual + tooltips; no search
- Recommended sequence: Phase 6 — Outstanding Commitments
MAW_TASK_061,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                ],
            ],
            [
                'module_num' => 18,
                'name' => '18 — Documentation & User Manual',
                'description' => <<<'MAW_MODULE_18'
**Source Module:** MODULE 18 — Documentation & User Manual (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-062, TASK-063, TASK-064 (3)
**Requirement Sources:** REV-SEP-031 · REV-JUN-002 · CR-011 · REV-SEP-002 · COM-032 · REVIEW-013
**Original Status Breakdown:** Implemented — Needs Verification: 1 · Not Implemented: 1 · Partially Implemented: 1

**Related Conflicts:**
- CONFLICT-05 — PostgreSQL committed, MySQL built

**Tasks:**
- TASK-062 — Role-specific user manual (Implemented — Needs Verification, High)
- TASK-063 — Simplify the user manual for general users (Not Implemented, Medium)
- TASK-064 — Replace the README-era project documentation set (Partially Implemented, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 18 — Documentation & User Manual. See that file in the repository root for the complete source analysis._
MAW_MODULE_18,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-062',
                        'number' => 62,
                        'title' => 'Role-specific user manual',
                        'description' => <<<'MAW_TASK_062'
**Source Task ID:** TASK-062 (`Mother-At-Work_TASKS.md`, MODULE 18 — Documentation & User Manual)
**Source Requirement ID(s):** REV-SEP-031 · REV-JUN-002 · CR-011
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-04 Meeting Minutes 29 Jun · DOC-01 TOR
**Original Status:** Implemented — Needs Verification · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** Ready for QA · High · Enhancement

- **Module:** Documentation
- **Type:** Enhancement
- **Source:** REV-SEP-031 · REV-JUN-002 · CR-011 (TOR §4 deliverables, §6)
- **Classification:** **Review / Change reinforcing a client deliverable** (COMBINED)
- **Priority:** High
- **Status:** **Implemented** — **Needs Verification**

#### Requirement Evolution

- **CR-011** (TOR §4, §6) — a comprehensive user manual including each module's manual, plus an
  administrative manual, required before project completion.
- **REV-JUN-002** (29 Jun) — *"Rename User Manual Position and convert to Bangla modular format."*
- **REV-SEP-031** (17 Sep) — *"User Manual to be Role Specific - if Admin - will get the full Featurea
  Manual, if General User - Limited Access to that same Manual."*
- **REV-SEP-002** (17 Sep) — *"User manual must be easier to understand by general users."* → TASK-063.

- **Requirement (current):** One manual, filtered by the reader's role and permissions.
- **Current Implementation:** This is built. `config/user-manual.php` defines a
  `permission-mapper` (127 mapping entries) supporting three forms — a permission list (OR), a
  `['roles' => [...]]` constraint, and `'*'` for unrestricted topics — plus `super_admin_roles`
  granting the Super Admin the full manual. The June modular-Bangla request is also satisfied: content
  is split per topic under `1.0/en` and `1.0/bn` with ~59 topics each. A feature test covers the
  permission behaviour.
- **Gap:** Content currency — the September meeting's other remarks (terminology, updated screenshots,
  plain language) mean the manual needs regeneration once TASK-009 and the UI changes land.
- **Evidence:**
  - `config/user-manual.php:80-110` — mapper semantics documented inline; `:88` `super_admin_roles`; `:96` `'roles-matrix' => '*'`; `:106-110` material permissions
  - `resources/user-manual/1.0/bn/` and `/en/` — parallel bilingual topic sets
  - `tests/Feature/UserManual/UserManualPermissionTest.php`
  - `resources/user-manual/MAINTENANCE.md`; `scripts/generate-docs-from-html.py`
- **Dependencies:** TASK-063, TASK-009
- **Acceptance Criteria:**
  - [x] Manual filtered by role and permission
  - [x] Modular bilingual (EN/BN) topic structure
  - [ ] Verified with a real non-admin account that restricted topics are hidden
  - [ ] Regenerated after the Factory terminology change and UI updates

#### Source Traceability

- Matrix CR-011: Comprehensive user manual per module (TOR §4, §6) — Implemented — `resources/user-manual/1.0/{en,bn}` + permission mapper
- Matrix REV-JUN-002: User manual Bangla modular (June 29) — Implemented — `user-manual/1.0/bn/`
- Matrix REV-SEP-031: Role-specific user manual (Sep 17) — Implemented — `permission-mapper` + test
- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_062,
                        'source_status' => 'Implemented — Needs Verification',
                        'status' => 'ready_for_qa',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'Source: Review 29 Jun', 'Source: Client Requirement (ToR)', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-063',
                        'number' => 63,
                        'title' => 'Simplify the user manual for general users',
                        'description' => <<<'MAW_TASK_063'
**Source Task ID:** TASK-063 (`Mother-At-Work_TASKS.md`, MODULE 18 — Documentation & User Manual)
**Source Requirement ID(s):** REV-SEP-002
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented · **Original Priority:** Medium · **Original Type:** Enhancement
**Imported As:** To Do · Medium · Enhancement

- **Module:** Documentation
- **Type:** Enhancement
- **Source:** REV-SEP-002
- **Classification:** Review / Change
- **Priority:** Medium
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"User manual must be easier to understand by general users."*
- **Current Implementation:** The manual is comprehensive and organised by feature, with topics named
  after system objects (`dynamic-forms`, `site-section`, `association-type`, `roles-matrix`,
  `instructions-for-crud-operations`). That structure mirrors the software rather than the reader's
  task, which is consistent with the client's feedback. SRS NFR-USE-002 also commits to supporting
  low-literacy and low-digital-literacy users with visual aids, icons and minimal text.
- **Gap:** Plain-language rewrite for the general-user path, task-oriented framing, and more visuals.
- **Evidence:**
  - `resources/user-manual/1.0/en/` — technical topic names listed above
  - `config/user-manual.php` — `'*'` topics are the ones a general user sees
  - SRS §6.3 NFR-USE-002 — low-literacy commitment
- **Dependencies:** TASK-062, TASK-009
- **Acceptance Criteria:**
  - [ ] General-user topics rewritten in plain language, task-oriented
  - [ ] Screenshots and step numbering added for common tasks
  - [ ] Bengali version reviewed by a native speaker, not machine-translated
  - [ ] UNICEF reviews and approves before launch

#### Source Traceability

- Matrix CR-011: Comprehensive user manual per module (TOR §4, §6) — Implemented — `resources/user-manual/1.0/{en,bn}` + permission mapper
- Matrix REV-SEP-002: Simpler user manual (Sep 17) — Not Implemented — Technical topic naming
- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_063,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep'],
                    ],
                    [
                        'source_task_id' => 'TASK-064',
                        'number' => 64,
                        'title' => 'Replace the README-era project documentation set',
                        'description' => <<<'MAW_TASK_064'
**Source Task ID:** TASK-064 (`Mother-At-Work_TASKS.md`, MODULE 18 — Documentation & User Manual)
**Source Requirement ID(s):** CR-011 · COM-032 · REVIEW-013
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · Repository review (REVIEW)
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Documentation
**Imported As:** In Progress · Medium · Documentation

- **Module:** Documentation
- **Type:** Documentation
- **Source:** CR-011 · COM-032 (SRS NFR-DOC-001) · REVIEW-013
- **Classification:** Our Commitment
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** SRS NFR-DOC-001 commits to user manuals, administrator manuals, **database schema
  documentation** and **deployment guides**. TOR §6 requires a user manual, an administrative manual
  and complete source code before completion.
- **Current Implementation:** The repository README was rewritten on 2026-09-09 into a full project
  reference — architecture, environment parameters, routing, jobs and scheduling, console commands,
  testing, code style and a deployment checklist. The bundled user manual covers end users. Still
  absent: **database schema documentation** and a distinct **administrator manual**.
- **Gap:** Schema documentation and the administrator manual named in both the TOR and the SRS.
- **Evidence:**
  - `README.md` — current project reference (architecture, env parameters, deployment)
  - `resources/user-manual/` — end-user manual
  - No ERD, data dictionary or schema document in the repository, despite SRS §7.1 committing to a data
    model with an ERD figure
  - No `CLAUDE.md` / `AGENTS.md` / cursor rules present (checked)
- **Dependencies:** TASK-062
- **Acceptance Criteria:**
  - [ ] Database schema documented (ERD plus data dictionary), matching SRS §7.1 and Appendix C
  - [ ] Administrator manual produced as a distinct deliverable
  - [ ] Deployment guide validated against a real deployment
  - [ ] Documentation handed over per the TOR final-handover deliverable

#### Source Traceability

- CONFLICT-05: PostgreSQL committed, MySQL built — clarification: Approve the MySQL and integer-PK decisions and amend the SRS, or justify them formally.
- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_064,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Code Review'],
                    ],
                ],
            ],
            [
                'module_num' => 19,
                'name' => '19 — UI/UX & Branding',
                'description' => <<<'MAW_MODULE_19'
**Source Module:** MODULE 19 — UI/UX & Branding (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-065, TASK-066, TASK-067 (3)
**Requirement Sources:** REV-SEP-003 · COM-033 · REV-SEP-005 · REV-SEP-001
**Original Status Breakdown:** Not Implemented: 2 · Partially Implemented: 1

**Related Conflicts:**
- CONFLICT-04 — Light colours vs WCAG contrast and UI consistency

**Tasks:**
- TASK-065 — Adopt a lighter, varied colour palette (Not Implemented, Medium)
- TASK-066 — Verify the application name is "Mothers@Work" everywhere (Partially Implemented, Medium)
- TASK-067 — Update aged content before launch (Not Implemented, High)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 19 — UI/UX & Branding. See that file in the repository root for the complete source analysis._
MAW_MODULE_19,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-065',
                        'number' => 65,
                        'title' => 'Adopt a lighter, varied colour palette',
                        'description' => <<<'MAW_TASK_065'
**Source Task ID:** TASK-065 (`Mother-At-Work_TASKS.md`, MODULE 19 — UI/UX & Branding)
**Source Requirement ID(s):** REV-SEP-003 · COM-033
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep · DOC-02 SRS
**Original Status:** Not Implemented · **Original Priority:** Medium · **Original Type:** Enhancement
**Imported As:** To Do · Medium · Enhancement

- **Module:** UI/UX
- **Type:** Enhancement
- **Source:** REV-SEP-003 · COM-033 (SRS NFR-UI-004 consistent UI, NFR-UI-003 contrast)
- **Classification:** **Review / Change — NEW REQUIREMENT** (and partly in tension with NFR-UI-004)
- **Priority:** Medium
- **Status:** **Not Implemented**
- **Requirement:** Sep 17: *"Customer suggested that we should not use any Dark Color, must use light
  color, also do not need to always use same color throughout the application, can use matching color
  along with the base color."*
- **Current Implementation:** Theming is centralised and therefore tractable — PrimeVue's Aura preset
  with `darkModeSelector: '.dark'`, Tailwind 4, and an appearance system with a light/dark/system
  toggle persisted in a non-encrypted `appearance` cookie. There is no restriction to a single accent
  colour in the design tokens, but no secondary/complementary palette has been introduced either, and
  dark mode remains fully available.
- **Gap:** A defined light-first palette with complementary accents per module; a decision on whether
  dark mode is retained, since the client asked for no dark colours.
- **Evidence:**
  - `resources/js/app.ts:40-49` — PrimeVue Aura preset with `darkModeSelector: '.dark'`
  - `app/Http/Middleware/HandleAppearance.php`; `resources/js/composables/useAppearance.ts`; `resources/js/app.ts:83` — `initializeTheme()`
  - `bootstrap/app.php:31` — `encryptCookies(except: ['appearance', 'sidebar_state'])`
  - `resources/css/app.css`; `package.json` — `tailwindcss ^4.1.1`, `tw-animate-css`
  - Numerous dark utility classes remain, e.g. `resources/js/pages/Welcome.vue:133`
- **Dependencies:** TASK-066, TASK-009
- **Acceptance Criteria:**
  - [ ] Light-first palette agreed with UNICEF, with complementary accents
  - [ ] Applied consistently across dashboard and landing surfaces
  - [ ] Contrast ratios still meet WCAG (SRS NFR-UI-003) — light palettes commonly fail this
  - [ ] Decision recorded on retaining or removing dark mode
  - [ ] Change does not contradict NFR-UI-004's consistency commitment

#### Source Traceability

- Matrix REV-SEP-003: Light colour palette (Sep 17) — Not Implemented — Dark mode still present
- CONFLICT-04: Light colours vs WCAG contrast and UI consistency — clarification: Confirm that AA contrast takes precedence where it conflicts with the light-palette preference, and whether dark mode is removed.
- Open question Q-13: Where the light-palette preference conflicts with WCAG AA contrast, which takes precedence? Is dark mode removed?
- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_065,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'Source: SRS Commitment', 'New Requirement'],
                    ],
                    [
                        'source_task_id' => 'TASK-066',
                        'number' => 66,
                        'title' => 'Verify the application name is "Mothers@Work" everywhere',
                        'description' => <<<'MAW_TASK_066'
**Source Task ID:** TASK-066 (`Mother-At-Work_TASKS.md`, MODULE 19 — UI/UX & Branding)
**Source Requirement ID(s):** REV-SEP-005
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Enhancement
**Imported As:** In Progress · Medium · Enhancement

- **Module:** UI/UX / Branding
- **Type:** Enhancement
- **Source:** REV-SEP-005
- **Classification:** Review / Change
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** Sep 17: *"Throughout the Application, must check the App name is 'Mothers@Work'."*
- **Current Implementation:** Inconsistent. The Vite entry point falls back to **`'Mother @ Work'`**
  (with spaces) when `VITE_APP_NAME` is unset, while `.env.example` ships `APP_NAME=Laravel` — so an
  environment built from the example renders "Laravel" in page titles. The correct name is available
  from settings (`GeneralSettings::$site_name` / `$site_name_bn`, shared into every Inertia response),
  which is the right source, but the fallback chain does not use it.
- **Gap:** A single authoritative app name across page titles, emails, PDF exports, the mobile app and
  the manifest.
- **Evidence:**
  - `resources/js/app.ts:25` — `const appName = import.meta.env.VITE_APP_NAME || 'Mother @ Work'`
  - `resources/js/app.ts:28-29` — `title: (title) => title ? \`${title} | ${appName}\` : appName`
  - `.env.example` — `APP_NAME=Laravel`, `VITE_APP_NAME="${APP_NAME}"`
  - `app/Settings/GeneralSettings.php` — `site_name`, `site_name_bn`
  - `app/Http/Middleware/HandleInertiaRequests.php` — shares `'name' => config('app.name')` and `settings.siteName`
- **Dependencies:** TASK-065, TASK-095
- **Acceptance Criteria:**
  - [ ] `APP_NAME` set to `Mothers@Work` in every environment
  - [ ] Frontend fallback corrected to the same spelling
  - [ ] Emails, PDF exports and the mobile app use the identical string
  - [ ] Spelling confirmed with UNICEF: "Mothers@Work" per the Sep 17 minutes

#### Source Traceability

- Recommended sequence: Phase 8 — Compliance, QA & Launch
- Dependency `TASK-095` not linked: TASK-095 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_066,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep'],
                    ],
                    [
                        'source_task_id' => 'TASK-067',
                        'number' => 67,
                        'title' => 'Update aged content before launch',
                        'description' => <<<'MAW_TASK_067'
**Source Task ID:** TASK-067 (`Mother-At-Work_TASKS.md`, MODULE 19 — UI/UX & Branding)
**Source Requirement ID(s):** REV-SEP-001
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented · **Original Priority:** High · **Original Type:** Enhancement
**Imported As:** To Do · High · Enhancement

- **Module:** UI/UX / CMS
- **Type:** Enhancement
- **Source:** REV-SEP-001 (both clauses)
- **Classification:** Review / Change — **blocked on client content**
- **Priority:** High
- **Status:** **Not Implemented**
- **Requirement:** Sep 17 opening items: *"UNICEF will review all contents before Launching"* and
  *"Updated information needed to be incorporated, current informations are old or already aged."*
- **Current Implementation:** Content is CMS- and settings-driven, so updating does not require a
  deploy: site sections with bilingual translations, DB-backed translation keys manageable from the
  admin, and configurable achievement figures. Those achievement numbers are currently **hardcoded in
  config** (`pregnant_covered => 15`, `female_covered => 350`) rather than derived from data, which is
  exactly the kind of value that goes stale. The landing page also still ships three home-page
  variants (`landing`, `landingV2`, `landingV3`), with older versions reachable at `/previous-1` and
  `/previous-2`.
- **Gap:** UNICEF's content review and updated figures; a decision on whether achievement counts should
  be computed from live data; removal of superseded landing variants before launch.
- **Evidence:**
  - `config/kaz.php` — `'achievements' => ['pregnant_covered' => 15, 'female_covered' => 350]`
  - `lang/en/frontend.home.json` — `achievement.factories_covered`, `factories_female_covered`, `factories_mother_covered`
  - `routes/web.php` — `/`→`homeVersion3`, `/previous-1`→`homeVersion1`, `/previous-2`→`home`
  - **Note:** `/previous-1` and `/previous-2` are both registered with the name `home.previous-1` — a duplicate route name (see TASK-073)
  - `resources/js/pages/{landing,landingV2,landingV3}/`
  - `app/Models/CMS/SiteSection.php`; `database/seeders/data/site_sections/`
- **Dependencies:** TASK-041, TASK-085, TASK-073
- **Acceptance Criteria:**
  - [ ] UNICEF reviews and signs off all public content before launch
  - [ ] Achievement figures updated, and ideally computed from live data
  - [ ] Superseded landing variants removed or deliberately retained
  - [ ] Content review recorded as a launch gate

#### Source Traceability

- Matrix REV-SEP-001: Content review + update aged info (Sep 17) — Not Implemented — Hardcoded achievement figures
- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_067,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep'],
                    ],
                ],
            ],
            [
                'module_num' => 20,
                'name' => '20 — Administration, Audit & Data',
                'description' => <<<'MAW_MODULE_20'
**Source Module:** MODULE 20 — Administration, Audit & Data (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-068, TASK-069, TASK-070, TASK-071, TASK-072, TASK-073, TASK-074 (7)
**Requirement Sources:** REV-JUN-007 · COM-034 · REV-JUN-008 · REVIEW-014 · REVIEW-015 · REVIEW-016 · REVIEW-017 · REVIEW-018 · COM-035
**Original Status Breakdown:** Implemented: 2 · Needs Verification: 4 · Conflict: 1

**Related Conflicts:**
- CONFLICT-05 — PostgreSQL committed, MySQL built

**Tasks:**
- TASK-068 — Public assessment form (Implemented, Medium)
- TASK-069 — Sample user database and demo data (Implemented, Low)
- TASK-070 — REVIEW: lang JSON files are bypassed at runtime (Needs Verification, Medium)
- TASK-071 — REVIEW: duplicate test file at two paths (Needs Verification, Low)
- TASK-072 — REVIEW: `env()` called outside configuration files (Needs Verification, High)
- TASK-073 — REVIEW: duplicate route name `home.previous-1` (Needs Verification, Low)
- TASK-074 — REVIEW: SRS commits to PostgreSQL; the system runs MySQL (Conflict, Medium)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 20 — Administration, Audit & Data. See that file in the repository root for the complete source analysis._
MAW_MODULE_20,
                'status' => 'in_progress',
                'priority' => 'high',
                'scopes' => ['Admin Panel'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-068',
                        'number' => 68,
                        'title' => 'Public assessment form',
                        'description' => <<<'MAW_TASK_068'
**Source Task ID:** TASK-068 (`Mother-At-Work_TASKS.md`, MODULE 20 — Administration, Audit & Data)
**Source Requirement ID(s):** REV-JUN-007 · COM-034
**Source Document(s):** DOC-04 Meeting Minutes 29 Jun · DOC-02 SRS
**Original Status:** Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** Done · Medium · Feature

- **Module:** Public Survey
- **Type:** Implementation
- **Source:** REV-JUN-007 · COM-034 (SRS FR-DC-003 custom form creation)
- **Classification:** Review / Change + Our Commitment
- **Priority:** Medium
- **Status:** **Implemented**
- **Requirement:** June 29: *"Develop Public Assessment Form."*
- **Current Implementation:** Delivered as the Survey form type. `FormType::SURVEY` was added to the
  dynamic-form enum; public routes serve `/form/{slug}` for display and submission; the controller
  resolves only forms that are Active, of type Survey, **approved**, and within their `end_at` window;
  a dedicated request validates responses; a survey layout and export exist, and the dashboard offers
  survey-form management with visibility and status toggles.
- **Gap:** The file-upload endpoint serving this form is insecure — TASK-053.
- **Evidence:**
  - `database/migrations/2026_06_30_104310_add_survey_to_type_enum_in_dynamic_forms_table.php`
  - `app/Enums/FormType.php` — `SURVEY` case with label and tag
  - `routes/web.php` — `GET /form/{slug}`, `POST /form/{slug}` on `Frontend\SurveyController`
  - `app/Http/Controllers/Frontend/SurveyController.php:61-74` — `findPublicSurvey()` with active/approved/window guards
  - `app/Http/Requests/Frontend/StoreSurveyResponseRequest.php:67` — required-field enforcement
  - `app/Exports/SurveyResponseExport.php`; `routes/dashboard.php:174-177` — survey-form resource, visibility and status toggles
  - `resources/js/layouts/landing/SurveyLayout.vue`; `resources/js/pages/landing/Survey/`
  - `database/seeders/SurveyFormsTableSeeder.php`
- **Dependencies:** TASK-053
- **Acceptance Criteria:**
  - [x] Public form reachable by slug without authentication
  - [x] Only approved, active, in-window forms served
  - [x] Responses validated and exportable
  - [ ] Upload security resolved (TASK-053)

#### Source Traceability

- Matrix REV-JUN-007: Public Assessment Form (June 29) — Implemented — `FormType::SURVEY` + public routes
MAW_TASK_068,
                        'source_status' => 'Implemented',
                        'status' => 'done',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 29 Jun', 'Source: SRS Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-069',
                        'number' => 69,
                        'title' => 'Sample user database and demo data',
                        'description' => <<<'MAW_TASK_069'
**Source Task ID:** TASK-069 (`Mother-At-Work_TASKS.md`, MODULE 20 — Administration, Audit & Data)
**Source Requirement ID(s):** REV-JUN-008
**Source Document(s):** DOC-04 Meeting Minutes 29 Jun
**Original Status:** Implemented · **Original Priority:** Low · **Original Type:** Implementation
**Imported As:** Done · Low · Feature

- **Module:** Data / Administration
- **Type:** Implementation
- **Source:** REV-JUN-008
- **Classification:** Review / Change
- **Priority:** Low
- **Status:** **Implemented**
- **Requirement:** June 29: *"Provide a sample user database."*
- **Current Implementation:** A full seeder chain provisions reference data and demo content in
  dependency order — material categories and tags; settings, association types, form inputs,
  permissions, translations, site sections and dynamic form inputs; divisions and districts; roles,
  role-permission mappings, users, industries and associations; materials and posts; then assessment
  setup, dashboard chart configs, survey forms and report templates. A dedicated action builds sample
  monitoring payloads, including the factory-attendee fields.
- **Gap:** None. Confirm the credentials handed to UNICEF are documented and that demo data is excluded
  from production.
- **Evidence:**
  - `database/seeders/DatabaseSeeder.php` — the ordered `call([...])` chain
  - `database/seeders/UserSeeder.php`, `IndustrySeeder.php`, `AssociationSeeder.php`, `AssessmentSetupSeeder.php`
  - `app/Actions/Monitoring/BuildMonitoringSamplePayloadAction.php:24`; `tests/Unit/Actions/Monitoring/BuildMonitoringSamplePayloadActionTest.php`
  - `database/seeders/data/` — `site_sections/`, `translations/`
  - `orangehill/iseed` available for regenerating seeders from data
- **Acceptance Criteria:**
  - [x] Seeders cover users, factories, associations and assessment data
  - [ ] Demo credentials documented for UNICEF
  - [ ] Production seeding limited to reference data, excluding demo users and posts

#### Source Traceability

- Matrix REV-JUN-008: Sample user database (June 29) — Implemented — Full seeder chain
MAW_TASK_069,
                        'source_status' => 'Implemented',
                        'status' => 'done',
                        'priority' => 'low',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 29 Jun'],
                    ],
                    [
                        'source_task_id' => 'TASK-070',
                        'number' => 70,
                        'title' => 'REVIEW: lang JSON files are bypassed at runtime',
                        'description' => <<<'MAW_TASK_070'
**Source Task ID:** TASK-070 (`Mother-At-Work_TASKS.md`, MODULE 20 — Administration, Audit & Data)
**Source Requirement ID(s):** REVIEW-014
**Source Document(s):** Repository review (REVIEW)
**Original Status:** Needs Verification · **Original Priority:** Medium · **Original Type:** Review
**Imported As:** To Do · Medium · Maintenance

- **Module:** Localization
- **Type:** Review
- **Source:** REVIEW-014
- **Classification:** Technical review — maintainability
- **Priority:** Medium
- **Status:** **Needs Verification**
- **Requirement:** Translation sources should be unambiguous.
- **Current Implementation:** `LocalizationService::getAllLocalization()` composes translations from
  **the database and settings only** — the call to `getDataFromLangFile()` is **commented out**. The
  method that reads `lang/{locale}/*.json` is therefore dead code, and the 91 keys in `lang/en/*.json`
  are served only because the DB seeder happens to carry all of them (verified: all 91 JSON keys are
  present among the 346 seeded `en` keys, so nothing renders as a raw key today). The hazard is
  latent but real: a developer editing a JSON file will see no effect, and a new key added only to
  JSON will render as its own key string, since `__()` falls back to returning the key.
  Results are cached with `Cache::rememberForever`, so DB edits need the cache cleared — which
  `clearCache()` does handle.
- **Gap:** Decide the single source of truth and remove or reinstate the other.
- **Evidence:**
  - `app/Services/LocalizationService.php:92-108` — `array_replace_recursive( /* getDataFromLangFile() commented out */ getDataFromDatabase(), getDataFromSetting() )`
  - `app/Services/LocalizationService.php:13-31` — `getDataFromLangFile()` retained but uncalled
  - `resources/js/composables/useLocalization.ts:47-49` — `if (!translation) return key`
  - `lang/en/*.json` (91 keys) vs `database/seeders/data/translations/translations.json` (346 `en` keys) — full coverage confirmed
  - `app/Services/LocalizationService.php:105` — `Cache::rememberForever`; `:110-121` — `clearCache()`
  - `tests/Feature/Localization/TranslationSeederTest.php`
- **Dependencies:** TASK-045, TASK-041
- **Acceptance Criteria:**
  - [ ] Single source of truth chosen (database recommended, since it is admin-editable)
  - [ ] If DB: `lang/*.json` removed or marked clearly as seed-only input, and `getDataFromLangFile()` deleted
  - [ ] A test asserts every key referenced in Vue resolves in both locales
  - [ ] Developer documentation states where to add a translation

#### Source Traceability

- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_070,
                        'source_status' => 'Needs Verification',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-071',
                        'number' => 71,
                        'title' => 'REVIEW: duplicate test file at two paths',
                        'description' => <<<'MAW_TASK_071'
**Source Task ID:** TASK-071 (`Mother-At-Work_TASKS.md`, MODULE 20 — Administration, Audit & Data)
**Source Requirement ID(s):** REVIEW-015
**Source Document(s):** Repository review (REVIEW)
**Original Status:** Needs Verification · **Original Priority:** Low · **Original Type:** Review
**Imported As:** To Do · Low · Maintenance

- **Module:** Testing
- **Type:** Review
- **Source:** REVIEW-015
- **Classification:** Technical review
- **Priority:** Low
- **Status:** **Needs Verification**
- **Requirement:** Tests should exist once.
- **Current Implementation:** `TranslationSeederTest.php` exists at both
  `tests/Feature/Localization/` and `tests/Feature/Feature/Localization/` — the latter under a
  doubled `Feature/Feature` directory, which indicates an accidental copy rather than an intentional
  variant.
- **Gap:** Confirm the duplicate and remove the stray copy.
- **Evidence:**
  - `tests/Feature/Localization/TranslationSeederTest.php`
  - `tests/Feature/Feature/Localization/TranslationSeederTest.php`
- **Acceptance Criteria:**
  - [ ] Duplicate identified and the redundant copy removed
  - [ ] Suite still green afterwards

#### Source Traceability

- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_071,
                        'source_status' => 'Needs Verification',
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-072',
                        'number' => 72,
                        'title' => 'REVIEW: `env()` called outside configuration files',
                        'description' => <<<'MAW_TASK_072'
**Source Task ID:** TASK-072 (`Mother-At-Work_TASKS.md`, MODULE 20 — Administration, Audit & Data)
**Source Requirement ID(s):** REVIEW-016
**Source Document(s):** Repository review (REVIEW)
**Original Status:** Needs Verification · **Original Priority:** High · **Original Type:** Review
**Imported As:** To Do · High · Maintenance

- **Module:** Infrastructure / Configuration
- **Type:** Review
- **Source:** REVIEW-016
- **Classification:** Technical review — **production defect risk**
- **Priority:** **High**
- **Status:** **Needs Verification**
- **Requirement:** `env()` must be read only inside `config/*` files, because `php artisan
  config:cache` — which the deployment checklist prescribes — makes `env()` return `null` everywhere
  else.
- **Current Implementation:** A dashboard route closure calls `env('MOBILE_APP_URL', '#')` directly.
  Once `config:cache` runs in production, this silently degrades to `'#'`, so the App Download page
  offers a dead link. The same value is also modelled inconsistently: `config/kaz.php` hardcodes a
  Google Drive URL as `android_app_download_url`, so there are two competing sources for the app
  download link and `MOBILE_APP_URL` is absent from `.env.example`.
- **Gap:** Move the value into config and reconcile the duplication.
- **Evidence:**
  - `routes/dashboard.php` — `Route::get('app-download', function () { $appDlUrl = env('MOBILE_APP_URL', '#'); ... })`
  - `config/kaz.php` — `'android_app_download_url' => 'https://drive.google.com/file/d/1sHg.../view'` (hardcoded, not env-driven)
  - `.env.example` — no `MOBILE_APP_URL` entry
  - `README.md` deployment section — prescribes `php artisan config:cache`
- **Dependencies:** TASK-095
- **Acceptance Criteria:**
  - [ ] `MOBILE_APP_URL` moved into a config file and read via `config()`
  - [ ] Duplicate app-download URL sources reconciled to one
  - [ ] Variable documented in `.env.example`
  - [ ] Verified working after `config:cache`

#### Source Traceability

- Recommended sequence: Phase 8 — Compliance, QA & Launch
- Dependency `TASK-095` not linked: TASK-095 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_072,
                        'source_status' => 'Needs Verification',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-073',
                        'number' => 73,
                        'title' => 'REVIEW: duplicate route name `home.previous-1`',
                        'description' => <<<'MAW_TASK_073'
**Source Task ID:** TASK-073 (`Mother-At-Work_TASKS.md`, MODULE 20 — Administration, Audit & Data)
**Source Requirement ID(s):** REVIEW-017
**Source Document(s):** Repository review (REVIEW) · Repository review (BUG)
**Original Status:** Needs Verification · **Original Priority:** Low · **Original Type:** Review
**Imported As:** To Do · Low · Maintenance

- **Module:** Routing
- **Type:** Review
- **Source:** REVIEW-017 · BUG (minor)
- **Classification:** Technical review
- **Priority:** Low
- **Status:** **Needs Verification**
- **Requirement:** Route names must be unique — Laravel silently keeps the last registration, so
  `route('home.previous-1')` resolves to the wrong URL.
- **Current Implementation:** Two distinct routes are registered with the same name:
  `/previous-1` → `homeVersion1` and `/previous-2` → `home`, both named `home.previous-1`. Any
  `route()` or Wayfinder-generated helper for that name points at `/previous-2`.
- **Gap:** Rename the second route, or remove both if the legacy landing variants are being retired
  (TASK-067).
- **Evidence:**
  - `routes/web.php` — `Route::get('/previous-1', 'homeVersion1')->name('home.previous-1');` and
    `Route::get('/previous-2', 'home')->name('home.previous-1');`
  - `resources/js/routes/` — Wayfinder generates helpers from these names
- **Dependencies:** TASK-067
- **Acceptance Criteria:**
  - [ ] Route names unique, or both legacy routes removed
  - [ ] Generated route helpers regenerated

#### Source Traceability

- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_073,
                        'source_status' => 'Needs Verification',
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Source: Code Defect', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-074',
                        'number' => 74,
                        'title' => 'REVIEW: SRS commits to PostgreSQL; the system runs MySQL',
                        'description' => <<<'MAW_TASK_074'
**Source Task ID:** TASK-074 (`Mother-At-Work_TASKS.md`, MODULE 20 — Administration, Audit & Data)
**Source Requirement ID(s):** REVIEW-018 · COM-035
**Source Document(s):** Repository review (REVIEW) · DOC-02 SRS
**Original Status:** Conflict · **Original Priority:** Medium · **Original Type:** Review
**Imported As:** Blocked · Medium · Maintenance

- **Module:** Infrastructure / Data
- **Type:** Review
- **Source:** REVIEW-018 · COM-035 (SRS §2.4.1, §5.2.3, NFR-SW-001)
- **Classification:** Technical review — **documentation vs implementation conflict**
- **Priority:** Medium
- **Status:** **Conflict**
- **Requirement:** SRS §2.4.1 specifies *"Database: PostgreSQL"*; §5.2.3 lists PostgreSQL in the data
  layer; NFR-SW-001 states the system SHALL interface with a PostgreSQL database server. The Inception
  report §3.2 likewise commits to *"PostgreSQL for relational database."*
- **Current Implementation:** The deployed database is **MySQL** (`.env`: `DB_CONNECTION=mysql`,
  `DB_DATABASE=mother_care`), while `.env.example` still ships `DB_CONNECTION=sqlite`. Migrations use
  MySQL-flavoured constructs — notably `enum` columns (`monitorings.type`, `monitorings.status`,
  `report_templates.type`) and `Schema::disableForeignKeyConstraints()` in the seeder. The SRS data
  model also specifies **UUID primary keys** (NFR-DATA-001, Appendix C); the implementation uses
  auto-increment integers with Hashids for external exposure — a reasonable engineering choice, but a
  second divergence from the committed data design.
- **Gap:** Neither divergence is recorded as an approved change. This matters for the final handover:
  the SRS is a contractual deliverable and currently describes a system that was not built.
- **Evidence:**
  - `.env` — `DB_CONNECTION=mysql`, `DB_DATABASE=mother_care`
  - `.env.example` — `DB_CONNECTION=sqlite`
  - `database/migrations/2025_11_26_121132_create_monitorings_table.php` — `$table->enum('type', FormType::values())`, `$table->enum('status', ...)`
  - `database/migrations/2026_07_31_161756_create_report_templates_table.php` — `$table->enum('type', ...)`
  - `database/migrations/2025_11_26_075606_create_industries_table.php` — `$table->id()` auto-increment, not UUID
  - `app/Traits/HasHashIdRouteBinding.php`; `config/hashids.php` — the ID-exposure strategy actually used
  - `database/seeders/DynamicFormInputsTableSeeder.php` — `Schema::disableForeignKeyConstraints()`
- **Dependencies:** TASK-064, TASK-096
- **Acceptance Criteria:**
  - [ ] UNICEF informed of the MySQL and integer-PK decisions, with rationale
  - [ ] SRS amended (Rev-H) or a formal change note appended, so the handover documentation matches the system
  - [ ] `.env.example` updated from `sqlite` to the real engine to prevent misconfigured deployments
  - [ ] Decision recorded in the architecture documentation

#### Source Traceability

- Matrix COM-035: PostgreSQL (SRS §2.4.1) — Conflict — Running MySQL
- CONFLICT-05: PostgreSQL committed, MySQL built — clarification: Approve the MySQL and integer-PK decisions and amend the SRS, or justify them formally.
- Open question Q-12: Are the MySQL and integer-primary-key decisions approved, and will the SRS be amended to match?
- Recommended sequence: Phase 8 — Compliance, QA & Launch
- Dependency `TASK-096` not linked: TASK-096 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_074,
                        'source_status' => 'Conflict',
                        'status' => 'blocked',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Source: SRS Commitment', 'Conflict'],
                    ],
                ],
            ],
            [
                'module_num' => 21,
                'name' => '21 — Remaining Commitment Gaps',
                'description' => <<<'MAW_MODULE_21'
**Source Module:** MODULE 21 — Remaining Commitment Gaps (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-075, TASK-076, TASK-077, TASK-078, TASK-079, TASK-080, TASK-081, TASK-082 (8)
**Requirement Sources:** CR-009 · COM-036 · COM-037 · COM-038 · COM-039 · CR-005 · COM-040 · COM-041 · CR-010 · COM-042 · CR-001 · COM-043
**Original Status Breakdown:** Partially Implemented: 2 · Implemented — Needs Verification: 1 · Not Implemented — Needs Verification: 2 · Not Implemented: 3

**Related Conflicts:**
- CONFLICT-04 — Light colours vs WCAG contrast and UI consistency

**Tasks:**
- TASK-075 — Document library for the Resource Sharing Hub (Partially Implemented, Medium)
- TASK-076 — Data approval workflow for submissions (Implemented — Needs Verification, Medium)
- TASK-077 — Accessibility (WCAG) conformance (Not Implemented — Needs Verification, Medium)
- TASK-078 — Test coverage for critical workflows (Partially Implemented, Medium)
- TASK-079 — Data retention policy implementation (Not Implemented, Medium)
- TASK-080 — DPIA compliance evidence (Not Implemented, Critical)
- TASK-081 — Containerization and CI pipeline (Not Implemented, Medium)
- TASK-082 — Cloud deployment architecture per the SRS (Not Implemented — Needs Verification, High)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 21 — Remaining Commitment Gaps. See that file in the repository root for the complete source analysis._
MAW_MODULE_21,
                'status' => 'in_progress',
                'priority' => 'critical',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-075',
                        'number' => 75,
                        'title' => 'Document library for the Resource Sharing Hub',
                        'description' => <<<'MAW_TASK_075'
**Source Task ID:** TASK-075 (`Mother-At-Work_TASKS.md`, MODULE 21 — Remaining Commitment Gaps)
**Source Requirement ID(s):** CR-009 · COM-036
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** In Progress · Medium · Feature

- **Module:** Resource Hub
- **Type:** Implementation
- **Source:** CR-009 (TOR §3e) · COM-036 (SRS FR-RS-001/002; Inception Module 6)
- **Classification:** Client Requirement + Our Commitment (COMBINED)
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** TOR §3(e) requires a cloud-hosted digital hub/archive for M@W implementation
  resources including IEC materials and training modules. SRS FR-RS-001 commits to document upload
  (PDF, Word, Excel, PowerPoint), categorisation (Guidelines, IEC materials, Templates, Reports),
  sub-categories and tags, and access control (public / registered / specific roles); FR-RS-002 to
  search by title, description and content, and filtering by category, date and author.
- **Current Implementation:** The LMS partially serves this — materials with categories, tags, media
  storage, SCORM packages and YouTube video, plus a resource section on the landing page. What is not
  evidenced is a **document library as a distinct artifact** with the committed document-type
  categorisation, per-document access control (public vs registered vs role) and content search.
- **Gap:** Document-specific categorisation and access control; search across documents.
- **Evidence:**
  - `app/Models/LMS/{Material,MaterialCategory}.php`; `database/migrations/2025_11_26_130827_create_materials_table.php`
  - `app/Models/Setup/Tag.php`; `app/Actions/LMS/Material/CreateMaterialLMS.php:50-68` — banner and resource media collections
  - `config/media.php` — document MIME and size limits already configured
  - `lang/en/frontend.home.json` — `resource.title` → *"Mothers@Work Resource"* landing section
  - No dedicated document-library model, route or page
- **Dependencies:** TASK-044, TASK-030
- **Acceptance Criteria:**
  - [ ] UNICEF confirms whether the LMS satisfies the hub, or a separate library is required
  - [ ] If separate: document categories, tags and per-document access control
  - [ ] Search by title and description, filterable by category and date

#### Source Traceability

- Matrix CR-009: Digital hub, multimedia, scrollable feed (TOR §3e) — Partially Implemented — LMS + feed built, feed now hidden; no gallery
MAW_TASK_075,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-076',
                        'number' => 76,
                        'title' => 'Data approval workflow for submissions',
                        'description' => <<<'MAW_TASK_076'
**Source Task ID:** TASK-076 (`Mother-At-Work_TASKS.md`, MODULE 21 — Remaining Commitment Gaps)
**Source Requirement ID(s):** COM-037
**Source Document(s):** DOC-02 SRS
**Original Status:** Implemented — Needs Verification · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** Ready for QA · Medium · Feature

- **Module:** Monitoring / Data Quality
- **Type:** Implementation
- **Source:** COM-037 (SRS FR-DC-007, FR-DC-008)
- **Classification:** Our Commitment
- **Priority:** Medium
- **Status:** **Implemented** — **Needs Verification**
- **Requirement:** SRS FR-DC-007: submissions enter "Pending Review", designated approvers
  approve/reject, with comments and feedback. FR-DC-008: retain all versions of submitted data, who
  submitted it, when and from which device, approval history, edit history and an exportable audit log.
- **Current Implementation:** The approval half is built — `Monitoring` uses the `Approvable` trait
  with a `Status` enum defaulting to `PENDING`, a `reviewed_at` timestamp, `was_pending`/`was_approved`/
  `was_reviewed` accessors, and a polymorphic `approvals` table recording each decision. Edit history
  comes from `laravel-auditing` via `UsesAuditing`. **Device information is not captured** (FR-DC-008),
  and the presence of reviewer comments on a monitoring decision is not evidenced.
- **Gap:** Device capture; approver comments; confirmation that the audit log export exists.
- **Evidence:**
  - `app/Models/Monitoring/Monitoring.php:27` — `use Approvable, ExportableHeaders, HasHashIdRouteBinding, SoftDeletes, UsesAuditing`
  - `app/Models/Monitoring/Monitoring.php:29-41` — the three status accessors; `:53` `status`; `:54` `reviewed_at`
  - `database/migrations/2025_11_26_121132_create_monitorings_table.php` — `status` defaults to `Status::PENDING`
  - `app/Traits/Approvable.php`; `app/Models/Approval.php`
  - `database/migrations/2025_12_03_131928_create_audits_table.php`
- **Dependencies:** TASK-006
- **Acceptance Criteria:**
  - [x] Submissions default to pending and require approval
  - [x] Approval decisions recorded with actor and timestamp
  - [ ] Reviewer comments captured on approve/reject
  - [ ] Device/user-agent recorded with each submission
  - [ ] Audit log exportable (FR-AD-004)

#### Source Traceability

- Matrix COM-037: Data approval workflow + audit trail (SRS FR-DC-007/008) — Implemented — `Approvable` + `approvals` + auditing
MAW_TASK_076,
                        'source_status' => 'Implemented — Needs Verification',
                        'status' => 'ready_for_qa',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: SRS Commitment', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-077',
                        'number' => 77,
                        'title' => 'Accessibility (WCAG) conformance',
                        'description' => <<<'MAW_TASK_077'
**Source Task ID:** TASK-077 (`Mother-At-Work_TASKS.md`, MODULE 21 — Remaining Commitment Gaps)
**Source Requirement ID(s):** COM-038
**Source Document(s):** DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Not Implemented — Needs Verification · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** To Do · Medium · Feature

- **Module:** UI/UX / Compliance
- **Type:** Implementation
- **Source:** COM-038 (SRS NFR-UI-003, NFR-COMP-001; Inception §15.1 "WCAG 2.1 Level AA Compliance")
- **Classification:** **Our Commitment — COMMITMENT EXTENSION** (the TOR does not name WCAG; the Inception commits to Level AA explicitly)
- **Priority:** Medium
- **Status:** **Not Implemented** — **Needs Verification**
- **Requirement:** The Inception report dedicates §15.1 to WCAG 2.1 Level AA across all four
  principles, with §15.1.5 committing to an accessibility testing strategy. SRS NFR-UI-003 requires
  standard contrast ratios, keyboard navigation and alt text.
- **Current Implementation:** Some foundations are present — semantic PrimeVue and reka-ui components
  with built-in ARIA, at least one explicit `aria-label` on the nav toggle, and a responsive
  mobile-first layout. There is **no evidence of an accessibility audit, automated a11y testing, or a
  conformance statement**, and no axe/pa11y/lighthouse tooling in the dev dependencies. The Sep 17
  light-palette request (TASK-065) directly threatens contrast conformance.
- **Gap:** Audit, remediation and a testing strategy — all explicitly committed in the Inception report.
- **Evidence:**
  - `resources/js/layouts/landing/Navbar.vue:237` — `aria-label="Toggle navigation"`
  - `package.json` devDependencies — eslint, prettier, vue-tsc; **no a11y testing tool**
  - `resources/js/components/ui/` — reka-ui primitives (accessible by construction)
  - No accessibility test in `tests/`; no conformance document in the repository
- **Dependencies:** TASK-065
- **Acceptance Criteria:**
  - [ ] Automated a11y scan integrated and run against key pages
  - [ ] Manual keyboard-navigation and screen-reader pass on core journeys
  - [ ] Contrast verified after the palette change
  - [ ] Conformance statement produced, or the Level AA commitment formally renegotiated

#### Source Traceability

- Matrix COM-038: WCAG 2.1 AA (Inception §15.1) — Not Implemented — No a11y tooling or audit
- CONFLICT-04: Light colours vs WCAG contrast and UI consistency — clarification: Confirm that AA contrast takes precedence where it conflicts with the light-palette preference, and whether dark mode is removed.
- Open question Q-13: Where the light-palette preference conflicts with WCAG AA contrast, which takes precedence? Is dark mode removed?
- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_077,
                        'source_status' => 'Not Implemented — Needs Verification',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: SRS Commitment', 'Source: Inception Commitment', 'Commitment Extension', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-078',
                        'number' => 78,
                        'title' => 'Test coverage for critical workflows',
                        'description' => <<<'MAW_TASK_078'
**Source Task ID:** TASK-078 (`Mother-At-Work_TASKS.md`, MODULE 21 — Remaining Commitment Gaps)
**Source Requirement ID(s):** COM-039
**Source Document(s):** DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Partially Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** In Progress · Medium · Feature

- **Module:** Testing / Quality
- **Type:** Implementation
- **Source:** COM-039 (SRS NFR-TEST-001, NFR-MAINT-001; Inception §4.2 Quality Assurance)
- **Classification:** Our Commitment
- **Priority:** Medium
- **Status:** **Partially Implemented**
- **Requirement:** SRS NFR-TEST-001 commits to integration tests for critical workflows, end-to-end
  tests for user journeys, API testing, performance testing and a separate test environment.
- **Current Implementation:** A real suite exists — 78 test files (Pest 3), with a dedicated
  `.env.testing`, covering authentication and 2FA, dashboard and its caching, monitoring, reports,
  form-builder approval, notifications, localization, settings, exports, the report engine and its
  guardrails, and several actions and support classes. Coverage is thin or absent in exactly the areas
  the September meeting is changing: **no LMS material tests, no quiz-attempt or pass-mark tests, no
  SCORM import or postback tests, no support-ticket tests, no association-task tests, and no
  end-to-end or performance tests**.
- **Gap:** Tests for the modules about to change, and the E2E/performance commitments.
- **Evidence:**
  - 78 files under `tests/` (`tests/Feature/**`, `tests/Unit/**`), `tests/Pest.php`, `tests/TestCase.php`, `tests/Support/MonitoringFormBuilderHelpers.php`
  - `phpunit.xml`; `.env.testing`; `composer.json` — `"test": ["@php artisan config:clear --ansi", "@php artisan test"]`
  - Present: `tests/Feature/Dashboard/Monitoring/MonitoringControllerTest.php`, `tests/Feature/Dashboard/Reports/*` (5 files), `tests/Unit/Services/ReportEngine*`
  - Absent: any test file matching material, quiz, scorm, support or association-task
- **Dependencies:** TASK-034, TASK-035, TASK-055
- **Acceptance Criteria:**
  - [ ] Tests for quiz pass/fail at the agreed threshold and for hidden score display
  - [ ] Tests for SCORM postback authorization, including the fail-closed case
  - [ ] Tests for support tickets and association tasks
  - [ ] Security regression tests for the upload endpoints
  - [ ] Performance test against the 750-factory target, or the commitment renegotiated

#### Source Traceability

- Matrix COM-039: Testing strategy (SRS NFR-TEST-001) — Partially Implemented — 78 test files; gaps in LMS/quiz/SCORM
- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_078,
                        'source_status' => 'Partially Implemented',
                        'status' => 'in_progress',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-079',
                        'number' => 79,
                        'title' => 'Data retention policy implementation',
                        'description' => <<<'MAW_TASK_079'
**Source Task ID:** TASK-079 (`Mother-At-Work_TASKS.md`, MODULE 21 — Remaining Commitment Gaps)
**Source Requirement ID(s):** CR-005 · COM-040
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Not Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** To Do · Medium · Feature

- **Module:** Administration / Compliance
- **Type:** Implementation
- **Source:** CR-005 (DPIA) · COM-040 (SRS NFR-DATA-003, §2.5.2; Inception §13)
- **Classification:** Client Requirement + Our Commitment
- **Priority:** Medium
- **Status:** **Not Implemented**
- **Requirement:** SRS §2.5.2 requires maintaining audit trails for 90 days; NFR-DATA-003 sets
  retention policies for operational data, audit logs (90 days), deleted user data, session data,
  notification history and support tickets — most marked *"depends on discussion"* in the SRS itself.
  SRS FR-AD-002 commits to managing data retention periods as configurable system parameters.
- **Current Implementation:** No retention enforcement. Audit records accumulate indefinitely — the
  scheduler prunes only expired cache entries and temporary uploads. Soft deletes are used widely, so
  "deleted" data is retained by default with no purge path. There is no retention configuration in
  `GeneralSettings` or `config/kaz.php`.
- **Gap:** The policy itself (still undecided in the SRS) and its enforcement.
- **Evidence:**
  - `routes/console.php` — scheduled tasks: `app:clear-upload-temp`, `cache:prune-expired`, `notices:send-scheduled`, `association-tasks:send-deadline-reminders` — no audit or data pruning
  - `app/Console/Commands/ClearUploadTemp.php` — temp uploads only
  - `database/migrations/2025_12_03_131928_create_audits_table.php` — no TTL or pruning
  - `app/Settings/GeneralSettings.php` — no retention parameters
  - `config/audit.php` present but no retention scheduling built on it
- **Dependencies:** TASK-006, TASK-080
- **Acceptance Criteria:**
  - [ ] UNICEF decides the retention periods the SRS left open
  - [ ] Periods configurable per FR-AD-002
  - [ ] Scheduled pruning honours them, with audit logs kept for at least 90 days
  - [ ] Purge of deleted user data defined in line with DPIA

#### Source Traceability

- Matrix COM-040: Data retention policies (SRS NFR-DATA-003) — Not Implemented — No pruning scheduled
- Recommended sequence: Phase 8 — Compliance, QA & Launch
MAW_TASK_079,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-080',
                        'number' => 80,
                        'title' => 'DPIA compliance evidence',
                        'description' => <<<'MAW_TASK_080'
**Source Task ID:** TASK-080 (`Mother-At-Work_TASKS.md`, MODULE 21 — Remaining Commitment Gaps)
**Source Requirement ID(s):** CR-005 · COM-041
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Not Implemented · **Original Priority:** Critical · **Original Type:** Documentation / Implementation
**Imported As:** To Do · Critical · Documentation

- **Module:** Security / Compliance
- **Type:** Documentation / Implementation
- **Source:** CR-005 (TOR §3b, §8.A.6) · COM-041 (SRS NFR-SEC-007; Inception §13 DPIA Compliance Plan)
- **Classification:** **Client Requirement + Our Commitment** (COMBINED)
- **Priority:** **Critical**
- **Status:** **Not Implemented**
- **Requirement:** TOR §3(b) requires data protection measures aligned with UNICEF's DPIA
  requirements. SRS NFR-SEC-007 commits to a documented DPIA, Privacy by Design, **user consent
  management**, and a **privacy policy and terms of service**. The Inception report §13 sets out a
  DPIA process, personal-data inventory, data-protection controls and a data-breach response plan.
- **Current Implementation:** Individual controls exist — RBAC, hashed IDs, auditing, soft deletes,
  HTTPS assumptions, an encryption-capable settings layer, backups via `spatie/laravel-backup`. The
  **compliance artifacts do not**: no DPIA document, no personal-data inventory, no consent management,
  no privacy policy or terms of service page, and no breach-response runbook in the repository. A
  `LegalPage.vue` component exists, which suggests the slot for policy content was anticipated but not
  filled. Note the DPIA is scored explicitly in the TOR's evaluation criteria (§8.A.6).
- **Gap:** Every named compliance artifact, plus consent capture.
- **Evidence:**
  - `resources/js/pages/landing/LegalPage.vue` — generic legal page component; no privacy-policy or terms content seeded
  - `database/seeders/data/site_sections/` — no privacy or terms section
  - No DPIA, data inventory or breach-response document anywhere in the repository
  - No consent field on any user table across the 87 migrations
  - `config/backup.php`; `spatie/laravel-backup` — the controls that do exist
- **Dependencies:** TASK-079, TASK-092, TASK-096
- **Acceptance Criteria:**
  - [ ] DPIA completed and signed off with UNICEF
  - [ ] Personal-data inventory produced per Inception §13.1.2
  - [ ] Privacy policy and terms of service published and linked
  - [ ] Consent captured and revocable where personal data is collected
  - [ ] Breach-response procedure documented with named contacts

#### Source Traceability

- Matrix CR-005: DPIA, encryption, RBAC, MFA, backup (TOR §3b) — Partially Implemented — RBAC + backup present; MFA unenforced; no DPIA
- Matrix COM-041: DPIA, consent, privacy policy (SRS NFR-SEC-007) — Not Implemented — No DPIA artifacts
- Recommended sequence: Phase 8 — Compliance, QA & Launch
- Dependency `TASK-092` not linked: TASK-092 does not exist in the register (TASK-001 to TASK-085)
- Dependency `TASK-096` not linked: TASK-096 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_080,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'critical',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment', 'Security'],
                    ],
                    [
                        'source_task_id' => 'TASK-081',
                        'number' => 81,
                        'title' => 'Containerization and CI pipeline',
                        'description' => <<<'MAW_TASK_081'
**Source Task ID:** TASK-081 (`Mother-At-Work_TASKS.md`, MODULE 21 — Remaining Commitment Gaps)
**Source Requirement ID(s):** CR-010 · COM-042
**Source Document(s):** DOC-01 TOR · DOC-03 Inception Report
**Original Status:** Not Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** To Do · Medium · Feature

- **Module:** Infrastructure / Deployment
- **Type:** Implementation
- **Source:** CR-010 (TOR §3f) · COM-042 (Inception §3.1.1, §4.1)
- **Classification:** Client Requirement
- **Priority:** Medium
- **Status:** **Not Implemented**
- **Requirement:** TOR §3(f): *"Adopt containerization (e.g., Docker) tools for flexible deployment
  and auto-scaling. Implement continuous integration pipelines to facilitate subsequent updates and
  iterative feature development."*
- **Current Implementation:** Neither is present. There is no `Dockerfile`, no `docker-compose.yml`
  and no CI workflow directory in the repository. `laravel/sail` is a dev dependency, which provides
  a local Docker environment but is not a deployment containerization strategy and is not configured
  here. Deployment is currently manual, per the README checklist.
- **Gap:** Container images, orchestration configuration, and an automated build/test/deploy pipeline.
- **Evidence:**
  - Repository root listing — no `Dockerfile`, no `docker-compose.yml`, no `.github/` or other CI directory
  - `composer.json` require-dev — `laravel/sail ^1.41` (local only, unconfigured)
  - `README.md` deployment section — manual command sequence
- **Dependencies:** TASK-078, TASK-095
- **Acceptance Criteria:**
  - [ ] Dockerfile producing a reproducible production image
  - [ ] CI pipeline running Pint, ESLint and the Pest suite on every push
  - [ ] Automated deployment to staging, with a promotion path to production
  - [ ] Horizontal scaling demonstrated, or the requirement renegotiated with UNICEF

#### Source Traceability

- Matrix CR-010: Containerization + CI pipelines (TOR §3f) — Not Implemented — No Dockerfile or CI config
- Recommended sequence: Phase 8 — Compliance, QA & Launch
- Dependency `TASK-095` not linked: TASK-095 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_081,
                        'source_status' => 'Not Implemented',
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: Inception Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-082',
                        'number' => 82,
                        'title' => 'Cloud deployment architecture per the SRS',
                        'description' => <<<'MAW_TASK_082'
**Source Task ID:** TASK-082 (`Mother-At-Work_TASKS.md`, MODULE 21 — Remaining Commitment Gaps)
**Source Requirement ID(s):** CR-001 · CR-010 · COM-043
**Source Document(s):** DOC-01 TOR · DOC-02 SRS · DOC-03 Inception Report
**Original Status:** Not Implemented — Needs Verification · **Original Priority:** High · **Original Type:** Implementation
**Imported As:** To Do · High · Feature

- **Module:** Infrastructure / Deployment
- **Type:** Implementation
- **Source:** CR-001, CR-010 · COM-043 (SRS NFR-ARCH-002, §5.2; Inception §3.2)
- **Classification:** Client Requirement + Our Commitment
- **Priority:** High
- **Status:** **Not Implemented** — **Needs Verification**
- **Requirement:** SRS NFR-ARCH-002 commits to AWS deployment, a Singapore or Mumbai region, Multi-AZ
  for high availability, optional auto-scaling and load balancing, and separate Staging (UAT) and
  Production environments. §5.2 adds Cloudflare as proxy, S3 for file storage and CloudFront as CDN.
- **Current Implementation:** Cannot be confirmed from the repository, and the configuration suggests
  divergence: the media disk is the **local** `upload` disk rooted at `storage/app/public/uploads`
  rather than S3, and while an `s3` disk is defined, `FILESYSTEM_DISK` defaults to `local` and
  `MEDIA_DISK` to `upload`. No CDN or Cloudflare configuration is present. Local file storage is
  incompatible with the horizontal scaling committed in TOR §3(f).
- **Gap:** Confirmation of the deployed topology; S3/CDN adoption if scaling is to be real.
- **Evidence:**
  - `config/filesystems.php` — `upload` disk with `'root' => storage_path('app/public/uploads')`; `s3` disk defined but unused by default
  - `config/media.php:15` — `'disk' => env('MEDIA_DISK', 'upload')`
  - `.env.example` — `FILESYSTEM_DISK=local`; AWS keys present but empty
  - `app/Traits/Media/HasMedia.php`; `app/Actions/LMS/Material/CreateMaterialLMS.php:50-68` — media written to the configured disk
  - No CDN, Cloudflare or infrastructure-as-code configuration in the repository
- **Dependencies:** TASK-081, TASK-095
- **Acceptance Criteria:**
  - [ ] Deployed architecture documented and compared against SRS §5.3
  - [ ] Media moved to S3 (or equivalent) if more than one application node is planned
  - [ ] Staging environment mirroring production, per the SRS
  - [ ] Divergences from the committed architecture recorded and approved

#### Source Traceability

- Matrix CR-001: Web + mobile apps, modular architecture (TOR §3a) — Implemented — Laravel 12 + Inertia/Vue; Flutter app external
- Matrix COM-043: AWS Multi-AZ, S3, CDN (SRS NFR-ARCH-002) — Needs Verification — Media on local disk
- Recommended sequence: Phase 8 — Compliance, QA & Launch
- Dependency `TASK-095` not linked: TASK-095 does not exist in the register (TASK-001 to TASK-085)
MAW_TASK_082,
                        'source_status' => 'Not Implemented — Needs Verification',
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)', 'Source: SRS Commitment', 'Source: Inception Commitment', 'Needs Review'],
                    ],
                ],
            ],
            [
                'module_num' => 22,
                'name' => '22 — Closing Items',
                'description' => <<<'MAW_MODULE_22'
**Source Module:** MODULE 22 — Closing Items (`Mother-At-Work_TASKS.md`)
**Source Tasks:** TASK-083, TASK-084, TASK-085 (3)
**Requirement Sources:** REV-JUN-014 · COM-044 · REV-SEP-030 · REV-JUL-001 · REV-SEP-001
**Original Status Breakdown:** Implemented: 1 · Partially Implemented — Needs Verification: 1 · Not Implemented (blocked on client): 1

**Tasks:**
- TASK-083 — Profile Image API (Implemented, Medium)
- TASK-084 — Home page "Tutorial" menu renamed to "LMS" (Partially Implemented — Needs Verification, Low)
- TASK-085 — Consolidated UNICEF content and format dependencies (Not Implemented (blocked on client), Critical)

_Imported from `Mother-At-Work_TASKS.md`, MODULE 22 — Closing Items. See that file in the repository root for the complete source analysis._
MAW_MODULE_22,
                'status' => 'in_progress',
                'priority' => 'critical',
                'scopes' => ['Web Application'],
                'tasks' => [
                    [
                        'source_task_id' => 'TASK-083',
                        'number' => 83,
                        'title' => 'Profile Image API',
                        'description' => <<<'MAW_TASK_083'
**Source Task ID:** TASK-083 (`Mother-At-Work_TASKS.md`, MODULE 22 — Closing Items)
**Source Requirement ID(s):** REV-JUN-014 · COM-044
**Source Document(s):** DOC-04 Meeting Minutes 29 Jun · DOC-02 SRS
**Original Status:** Implemented · **Original Priority:** Medium · **Original Type:** Implementation
**Imported As:** Done · Medium · Feature

- **Module:** API / Profile
- **Type:** Implementation
- **Source:** REV-JUN-014 · COM-044 (SRS FR-UM-012 "Upload profile photo")
- **Classification:** Review / Change + Our Commitment
- **Priority:** Medium
- **Status:** **Implemented**
- **Requirement:** June 29: *"Add Profile Image API."* SRS FR-UM-012 commits to profile management
  including photo upload, contact update, password change and language preference.
- **Current Implementation:** Delivered. `POST /api/v1/auth/profile` accepts a multipart `avatar`
  validated as an image restricted to `jpeg,jpg,png,webp` at a 1 MB ceiling — note this endpoint uses
  a **tighter and safer** whitelist than the public survey upload (TASK-053), and correctly excludes
  SVG. The file is passed to a dedicated `UpdateProfileAction`, and the response returns the enriched
  profile resource. The web side shares an avatar URL into every Inertia response, resolving
  `avatar_large` with an `optimized` fallback.
- **Gap:** None found.
- **Evidence:**
  - `routes/api.php:44` — `Route::post('profile', [AuthController::class, 'updateProfile'])`
  - `app/Http/Controllers/Api/Auth/AuthController.php:170-184` — `updateProfile` with `$request->file('avatar')`
  - `app/Http/Requests/API/UpdateProfileRequest.php:38` — `['nullable','image','mimes:jpeg,jpg,png,webp','max:1024']`; `:69` attribute label
  - `app/Http/Middleware/HandleInertiaRequests.php` — `getFirstMediaUrl('user_avatars','avatar_large') ?? getFirstMediaUrl('user_avatars','optimized')`
  - `app/Traits/Media/HasMedia.php`; `config/media.php` — conversions and limits
  - `tests/Feature/API/UpdateProfileTest.php`; `tests/Unit/Http/Requests/API/UpdateProfileRequestTest.php`
- **Acceptance Criteria:**
  - [x] Avatar uploadable via the mobile API with type and size validation
  - [x] Conversions generated and served
  - [x] Covered by feature and unit tests

#### Source Traceability

- Matrix REV-JUN-014: Profile Image API (June 29) — Implemented — `AuthController:175` avatar handling
MAW_TASK_083,
                        'source_status' => 'Implemented',
                        'status' => 'done',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Review 29 Jun', 'Source: SRS Commitment'],
                    ],
                    [
                        'source_task_id' => 'TASK-084',
                        'number' => 84,
                        'title' => 'Home page "Tutorial" menu renamed to "LMS"',
                        'description' => <<<'MAW_TASK_084'
**Source Task ID:** TASK-084 (`Mother-At-Work_TASKS.md`, MODULE 22 — Closing Items)
**Source Requirement ID(s):** REV-SEP-030
**Source Document(s):** DOC-06 Meeting Minutes 17 Sep
**Original Status:** Partially Implemented — Needs Verification · **Original Priority:** Low · **Original Type:** Enhancement
**Imported As:** In Progress · Low · Enhancement

- **Module:** CMS / UI
- **Type:** Enhancement
- **Source:** REV-SEP-030
- **Classification:** Review / Change
- **Priority:** Low
- **Status:** **Partially Implemented** — **Needs Verification**
- **Requirement:** Sep 17: *"Change the Home Page Title Tutorial to LMS."*
- **Current Implementation:** The label **was changed, but to a different word than requested**. The
  key `frontend.navbar.tutorial` is seeded in the database as **"Learning Hub"** (EN) and
  **"লার্নিং হাব"** (BN) — not "Tutorial", and not "LMS". It renders in six places: the desktop and
  mobile navbars, the footer, and three spots on the courses page. Note the route path is still
  `/tutorials`, and a second navbar key `frontend.navbar.course` ("Courses" / "কোর্সসমূহ") also exists,
  so three vocabularies are in play — Tutorial (URL), Learning Hub (menu) and Courses (key).
- **Gap:** Confirm whether "Learning Hub" is an accepted substitute for "LMS". If the literal request
  stands, one translation value changes — no deploy needed.
- **Evidence:**
  - `database/seeders/data/translations/translations.json` — `{"locale":"en","group":"frontend.navbar","key":"tutorial","value":"Learning Hub"}` and the `bn` equivalent
  - `resources/js/layouts/landing/Navbar.vue:310,396`; `resources/js/layouts/landing/Footer.vue:142`; `resources/js/pages/landing/Course/Index.vue:77,97,104` — the six usages
  - `lang/en/frontend.navbar.json` — contains `"course": "Courses"` but **no** `tutorial` key (supplied from the DB instead — see TASK-070)
  - `routes/web.php` — `Route::get('/tutorials', ...)->name('courses.index')`
- **Dependencies:** TASK-070, TASK-042
- **Acceptance Criteria:**
  - [ ] UNICEF confirms "Learning Hub" is acceptable, or the label is set to "LMS"
  - [ ] One vocabulary chosen across menu, footer, page headings and ideally the URL
  - [ ] Bengali equivalent agreed (an acronym may not translate meaningfully)

#### Source Traceability

- Matrix REV-SEP-030: Tutorial → LMS on home page (Sep 17) — Partially Implemented — Renders "Learning Hub", not "LMS"
- Open question Q-08: Is "Learning Hub" accepted in place of "LMS" for the home page menu?
- Recommended sequence: Phase 3 — September Review Items That Are Unblocked
MAW_TASK_084,
                        'source_status' => 'Partially Implemented — Needs Verification',
                        'status' => 'in_progress',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Review 17 Sep', 'Needs Review'],
                    ],
                    [
                        'source_task_id' => 'TASK-085',
                        'number' => 85,
                        'title' => 'Consolidated UNICEF content and format dependencies',
                        'description' => <<<'MAW_TASK_085'
**Source Task ID:** TASK-085 (`Mother-At-Work_TASKS.md`, MODULE 22 — Closing Items)
**Source Requirement ID(s):** REV-JUL-001 · REV-SEP-001
**Source Document(s):** DOC-05 Meeting Minutes 13 Jul · DOC-06 Meeting Minutes 17 Sep
**Original Status:** Not Implemented (blocked on client) · **Original Priority:** Critical · **Original Type:** Process
**Imported As:** Blocked · Critical · Research

- **Module:** Process / Client Dependency
- **Type:** Process
- **Source:** REV-JUL-001 · REV-SEP-001, -008, -009, -017, -022, -023, -024, -030 · SRS §2.6.1
- **Classification:** Review / Change — **client-side blocker register**
- **Priority:** **Critical**
- **Status:** **Not Implemented** (blocked on client)
- **Requirement:** Several tasks cannot proceed without client-supplied content or specifications.
  Consolidated here so the blockers are visible in one place rather than scattered across tasks.
- **Outstanding client deliverables:**

  | # | Awaited from | Item | Blocks |
  |---|---|---|---|
  | 1 | UNICEF | Finalized report format per the Rule Book (agreed 13 Jul) | TASK-025, TASK-026, TASK-050 |
  | 2 | UNICEF | Mobile app report format | TASK-050 |
  | 3 | UNICEF | Legal reference definitions for footnotes + Std 7 citation | TASK-016 |
  | 4 | UNICEF | Certificate design | TASK-032 |
  | 5 | UNICEF | Landing page hero title (EN/BN) | TASK-041 |
  | 6 | UNICEF | Replacement name for the "Story" menu | TASK-042 |
  | 7 | UNICEF | Final Story category list | TASK-043 |
  | 8 | UNICEF | Updated content and achievement figures | TASK-067 |
  | 9 | UNICEF | Content review sign-off before launch | TASK-031, TASK-067 |
  | 10 | UNICEF | Supreme Admin nomination | TASK-008 |
  | 11 | BGMEA / BKMEA | API specifications (SRS assumption, 2 weeks from start) | TASK-056 |
  | 12 | DIFE | Endpoint specification and credentials | TASK-057 |
  | 13 | UNICEF | E-learning module content (SRS §2.6.1 assumption) | TASK-030 |

- **Evidence:** DOC-05 action items table (all rows "Pending"/"Awaiting Start"); DOC-06 items listed
  above; SRS §2.6.1 assumptions; SRS §2.6.2 dependencies.
- **Acceptance Criteria:**
  - [ ] Each row tracked with an owner and a due date
  - [ ] Items 1, 11 and 12 escalated formally — all are months overdue against their original dates
  - [ ] Delivery schedule renegotiated to reflect actual receipt dates
  - [ ] Receipt confirmed by email, per the July 13 next steps

#### Source Traceability

- Recommended sequence: Phase 2 — Escalate Blockers (parallel with Phase 1; no development)
MAW_TASK_085,
                        'source_status' => 'Not Implemented (blocked on client)',
                        'status' => 'blocked',
                        'priority' => 'critical',
                        'task_type' => 'Research',
                        'labels' => ['Source: Review 13 Jul', 'Source: Review 17 Sep'],
                    ],
                ],
            ],
        ];
    }
}
