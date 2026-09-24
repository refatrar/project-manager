<?php

namespace Database\Seeders;

use App\Actions\OMS\CreateProject;
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
 * One-time import of the ALP-RTM / Skilfo (UNICEF) requirement-traceability
 * audit (`ALP-RTM_TASKS.md`, generated 2026-09-22) into a dedicated "ALP-RTM"
 * project: 35 `ProjectModule`s and 118 `Task`s, one row per task in the
 * source document's register, each retaining (in its description) the
 * original module, source document, requirement ID, and original
 * status/priority exactly as written, plus `Label`s for the source
 * category (Client/Commitment/UAT/New/Review) and `TaskDependency` rows for
 * the cross-references the source document makes between tasks.
 *
 * Status mapping (the app has no exact equivalent for two of the five
 * source statuses, so the literal original string is kept in each task's
 * description as well):
 *   TODO -> todo, COMPLETED -> done, BLOCKED -> blocked,
 *   REVIEW REQUIRED -> in_review (closest fit), NOT APPLICABLE -> cancelled
 *   (closest fit for "no code action needed").
 *
 * Not wired into `DatabaseSeeder` — this is a one-off real dataset, not
 * demo/showcase data (see `ProfilingSeeder`'s docblock for the same
 * reasoning). Requires an existing team and the base `TaskType`/`Scope`
 * seed data (`ScopeSeeder`, `TaskTypeSeeder`), i.e. run this after the
 * normal seeder chain. Idempotent: skips entirely if a project with code
 * `ALPRTM` already exists on the team.
 *
 * Run with: php artisan db:seed --class=AlpRtmImportSeeder
 */
class AlpRtmImportSeeder extends Seeder
{
    /**
     * Seed the database.
     */
    public function run(): void
    {
        $team = Team::query()->first();
        if ($team === null) {
            $this->command->warn('AlpRtmImportSeeder: no team exists yet - run the base seeders first. Skipping.');

            return;
        }

        if (Project::query()->where('team_id', $team->id)->where('code', 'ALPRTM')->exists()) {
            $this->command->info('AlpRtmImportSeeder: an ALP-RTM project already exists on this team - skipping.');

            return;
        }

        $creator = User::query()->orderBy('id')->first();
        if ($creator === null) {
            $this->command->warn('AlpRtmImportSeeder: no user exists to attribute the import to. Skipping.');

            return;
        }

        $taskTypeIds = TaskType::query()->whereIn('name', ['Feature', 'Bug', 'Enhancement', 'Research', 'Documentation', 'Maintenance'])->pluck('id', 'name');
        if ($taskTypeIds->count() < 6) {
            throw new RuntimeException('AlpRtmImportSeeder: run TaskTypeSeeder first - one or more of the 6 required task types is missing.');
        }

        $scopeIds = Scope::query()->pluck('id', 'name');

        DB::transaction(function () use ($team, $creator, $taskTypeIds, $scopeIds): void {
            $project = $this->createProject($team, $creator);
            $labelIds = $this->createLabels($team, $creator);

            $origIdToDbId = [];
            $statusPositionCounters = ['todo' => 0, 'done' => 0, 'blocked' => 0, 'in_review' => 0, 'cancelled' => 0];

            foreach ($this->modules() as $modulePlan) {
                $module = $this->createModule($project, $modulePlan, $creator, $scopeIds);

                foreach ($modulePlan['tasks'] as $taskPlan) {
                    $task = $this->createTask($project, $module, $taskPlan, $creator, $taskTypeIds, $labelIds, $statusPositionCounters);
                    $origIdToDbId[$taskPlan['orig_task_id']] = $task->id;
                }
            }

            $project->save();

            $this->createDependencies($origIdToDbId, $creator);
        });

        $this->command->info('AlpRtmImportSeeder: imported ALP-RTM project (35 modules, 118 tasks).');
    }

    private function createProject(Team $team, User $creator): Project
    {
        $project = (new CreateProject)->handle($team, $creator, [
            'code' => 'ALPRTM',
            'name' => 'ALP-RTM',
            'description' => $this->projectDescription(),
            'status' => ProjectStatus::Active,
            'priority' => Priority::High,
            'health' => ProjectHealth::AtRisk,
            'client_name' => 'UNICEF',
        ]);
        $project->refresh();

        return $project;
    }

    /**
     * @return array<string, int>
     */
    private function createLabels(Team $team, User $creator): array
    {
        $colors = [
            'Source: Client Requirement (ToR)' => '#0891B2',
            'Source: SRS Commitment' => '#4338CA',
            'Source: UAT Feedback' => '#B45309',
            'Source: Skilfo New Requirement' => '#15803D',
            'Source: Code Review' => '#BE123C',
            'Security' => '#7F1D1D',
            'Needs Review' => '#CA8A04',
        ];

        $ids = [];
        foreach ($colors as $name => $color) {
            $label = Label::query()->where('team_id', $team->id)->where('name', $name)->first();
            if ($label === null) {
                $label = new Label(['name' => $name, 'color' => $color, 'description' => 'Created for the ALP-RTM import.']);
                $label->team_id = $team->id;
                $label->created_by = $creator->id;
                $label->save();
            }
            $ids[$name] = $label->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $modulePlan
     * @param  Collection<string, int>  $scopeIds
     */
    private function createModule(Project $project, array $modulePlan, User $creator, $scopeIds): ProjectModule
    {
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

        if (isset($scopeIds[$modulePlan['scope']])) {
            $module->deliveryScopes()->attach($scopeIds[$modulePlan['scope']]);
        }

        return $module;
    }

    /**
     * @param  array<string, mixed>  $taskPlan
     * @param  Collection<string, int>  $taskTypeIds
     * @param  array<string, int>  $labelIds
     * @param  array<string, int>  $statusPositionCounters
     */
    private function createTask(Project $project, ProjectModule $module, array $taskPlan, User $creator, $taskTypeIds, array $labelIds, array &$statusPositionCounters): Task
    {
        $status = (string) $taskPlan['status'];
        $statusPositionCounters[$status] = ($statusPositionCounters[$status] ?? 0) + 1;

        $task = new Task([
            'title' => $taskPlan['title'],
            'description' => $taskPlan['description'],
            'status' => TaskStatus::from($status),
            'priority' => Priority::from($taskPlan['priority']),
            'position' => $statusPositionCounters[$status],
        ]);
        $task->project_id = $project->id;
        $task->project_module_id = $module->id;
        $task->task_type_id = $taskTypeIds[$taskPlan['task_type']];
        $task->number = $project->next_task_number;
        $project->next_task_number++;
        $task->created_by = $creator->id;
        $task->save();

        $labelIdsForTask = array_values(array_unique(array_filter(
            array_map(static fn (string $name) => $labelIds[$name] ?? null, $taskPlan['labels']),
        )));
        if ($labelIdsForTask !== []) {
            $task->labels()->attach($labelIdsForTask);
        }

        return $task;
    }

    /**
     * @param  array<string, int>  $origIdToDbId
     */
    private function createDependencies(array $origIdToDbId, User $creator): void
    {
        $pairs = [];
        foreach ($this->modules() as $modulePlan) {
            foreach ($modulePlan['tasks'] as $taskPlan) {
                foreach ($taskPlan['crossrefs'] as $refOrigId) {
                    $pairs[] = [$taskPlan['orig_task_id'], $refOrigId];
                }
            }
        }
        foreach ($this->extraDependencies() as [$origA, $origB]) {
            $pairs[] = [$origA, $origB];
        }

        $seen = [];
        foreach ($pairs as [$origA, $origB]) {
            $fromId = $origIdToDbId[$origA] ?? null;
            $toId = $origIdToDbId[$origB] ?? null;
            if ($fromId === null || $toId === null || $fromId === $toId) {
                continue;
            }

            $pairKey = min($fromId, $toId).'-'.max($fromId, $toId);
            if (isset($seen[$pairKey])) {
                continue;
            }
            $seen[$pairKey] = true;

            $dependency = new TaskDependency([
                'task_id' => $fromId,
                'related_task_id' => $toId,
                'type' => TaskDependencyType::RelatesTo,
            ]);
            $dependency->created_by = $creator->id;
            $dependency->save();
        }
    }

    /**
     * Cross-references the source document makes in prose but not via a
     * literal `TASK-XXX` mention inside the referencing task's own text.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function extraDependencies(): array
    {
        return [
            ['TASK-059', 'TASK-095'], // both wire an email notification to Event/Activity creation (Module 17 vs Module 29) - likely overlapping work in the source document.
        ];
    }

    private function projectDescription(): string
    {
        return <<<'PROJECT_DESCRIPTION'
ALP-RTM / Skilfo (UNICEF) — Real-Time Monitoring (RTM) / M&E system for out-of-school-youth vocational training (learners, trainers, mastercraft persons, training centers, monitoring visits, employment outcomes). Laravel 10 + Inertia.js + Vue.js, operating as two tenants on one codebase: the original ALP scope and the newer Skilfo/BNFE scope.

Imported from a full project audit and requirement-traceability analysis (`ALP-RTM_TASKS.md`, generated 2026-09-22) covering four source documents:
- **CLIENT** — ToR for ALP RTM.docx (original Terms of Reference)
- **COMMITMENT** — System Requirement Specification (SRS) 1.pdf (14 Mar 2024)
- **UAT** — UAT Feedback By KAZ, 4 September
- **NEW** — Skilfo Projects Comparison.pdf (scope expansion)
- **REVIEW** — full codebase review of `/var/www/kazsoft/kaz_unicef-alp`

35 modules and 118 tasks were imported, each retaining its original module, source document, requirement ID, status, and priority (see each module's and task's description for full detail, and the source `ALP-RTM_TASKS.md` file in this repo for the complete unabridged analysis).

**Note on source-document numbering:** the source document's own summary table states 121 total tasks, but only 118 task rows actually exist in its register — task numbers TASK-071, TASK-072, TASK-073 are skipped/never defined in the source file itself (not an import error). The source's Critical-priority count (9) likewise does not correspond to any literal per-task priority tag in the register — no task is tagged CRITICAL; only HIGH/MEDIUM/LOW appear. Both discrepancies are pre-existing in the source document, not introduced during import.

## Open Questions / Clarifications Required (from source document)

- **OQ-001** (NEW-011): What exactly are "CA-1" through "CA-4" in the Monthly Progress Report, and how often are they filled? Are they assessment-, attendance-, or performance-related?
- **OQ-002** (NEW-007): How does "Literacy Center" differ from the existing "Training Center"? Is it a distinct module or a Training Center sub-type?
- **OQ-003** (NEW-005): What are the precise differences intended between "Pre-Assessment" and "Training Assessment" (NEW-005)?
- **OQ-004** (NEW-009): Beyond the existing Trainer/MCP profile, what additional data does the general "Craft Database" (NEW-009) need to capture?
- **OQ-005** (NEW-008): Should "Institution" (NEW-008) be a fully independent module, or always linked to Training Centers/Courses?
- **OQ-006** (REV-027): Is the Task Tracking / Form Builder / Support-ticketing functionality found in the codebase an intentional, approved deliverable, unbilled scope creep, or something that should be removed/hidden?
- **OQ-007** (CR-009): Is the 100GB storage / 16GB RAM hardware spec in the SRS (COM-028) still adequate now that the target scale has grown from ~25,000 to ~100,000 participants (CR-009) plus the added Skilfo/BNFE scope?
- **OQ-008** (COM-028): Does "mobile SSO" (COM-028) mean integration with an existing UNICEF/partner identity provider (OIDC/SAML), or something narrower (e.g. shared session between web and mobile)?

## Requirement Conflicts (from source document)

- **CONF-001**: CR-018/COM-031 commit to "automated backup procedures" vs. Actual Backup feature is manual-trigger only (index/store/destroy, no scheduled job) — Impact: Data-loss risk if backups depend on an admin remembering to click a button Resolution needed: Decide: add a scheduled job, or formally amend the commitment to "on-demand backup" (TASK-106)
- **CONF-002**: SRS commits (COM-012/013/014) to a "Logbooks" timeline tab on every profile-type detail page vs. Trainer/MCP/Learner detail pages all omit the Logbook tab despite the underlying data/relation existing — Impact: Client-visible gap between spec and delivered UI across 3 modules Resolution needed: Add the missing tab uniformly (TASK-023/027/033) rather than three separate ad hoc fixes
PROJECT_DESCRIPTION;
    }

    /**
     * The full parsed ALP-RTM module/task register, one entry per module
     * of `ALP-RTM_TASKS.md`, in source order.
     *
     * @return array<int, array{module_num: int, name: string, description: string, status: string, priority: string, scope: string, tasks: array<int, array{orig_task_id: string, title: string, description: string, status: string, priority: string, task_type: string, labels: array<int, string>, crossrefs: array<int, string>}>}>
     */
    private function modules(): array
    {
        return [
            [
                'module_num' => 1,
                'name' => '01 — Authentication, RBAC & Security',
                'description' => <<<'MODULE_DESC_1'
**Requirement Sources:** CLIENT: CR-013, CR-014 · COMMITMENT: COM-007, COM-008, COM-029 · REVIEW: REV-001–REV-006, REV-020

**Current Implementation:**
RBAC uses `spatie/laravel-permission` (`app/Models/Role.php`, `AuthServiceProvider.php`), enforced almost entirely via `can:<ability>_*` route middleware across `routes/dashboard.php`/`api.php`/`api.v2.php`. Seeded roles exactly match the committed 7-role model (Super Admin, Admin, Partner, Mastercraft, Trainer, Manager, Frontline) plus legitimate later additions (Assessor, BNFE directors). Auth is Laravel's standard web guard + Sanctum for the mobile API; bcrypt hashing; login rate-limiting exists; CSRF is correctly scoped (only API routes excluded). Versioning (`Versionable` trait) and audit logging (`owen-it/laravel-auditing`) are genuine, working implementations, not stubs.

**Requirement Gap:**
Route-level ability gating is strong, but object-level (per-record) authorization is inconsistent — hand-rolled per controller/service rather than centralized in policies for some models (e.g. no `McpPolicy`). BNFE dashboard routes have no permission gate at all. Several COM-029 checklist items (DB-backed sessions, IP session lock, 2FA) are not implemented. File upload validation is too permissive for non-image files.

_Imported from `ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_1,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Admin Panel',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-001',
                        'title' => 'Add `can:` permission middleware to all BNFE dashboard routes',
                        'description' => <<<'TASK_DESC_2'
**Original Task ID:** TASK-001 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** REVIEW · **Source Requirement ID(s):** REV-001
**Original Status:** TODO · **Original Priority:** HIGH

Add `can:` permission middleware to all BNFE dashboard routes

#### Gate BNFE dashboard routes with permission middleware

- **Type:** [SECURITY]
- **Source:** REVIEW · **Source ID:** REV-001 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** Every other dashboard module is gated by `can:<module>_access` (and finer-grained create/update/delete/view abilities); BNFE routes should follow the same pattern.
- **Current State:** `routes/dashboard.php:165-169` registers `bnfe/learners`, `bnfe/training-centers`, `bnfe/occupations`, `bnfe/programs` inside only the outer `auth.multi`/`isActive`/`verified` group, with no `can:` gate.
- **Gap:** Any authenticated user of any role — including Frontline — can view/act on BNFE data that should likely be restricted to Admin/Manager/Partner roles.
- **Required Change:** Add appropriate `can:bnfe_*` middleware (define new permissions if needed) matching the access level intended for BNFE data, and seed them into `PermissionsSeeder`.
- **Dependencies:** None.
- **Acceptance Criteria:** Frontline (and any other role not intended to see BNFE data) receives a 403 when hitting BNFE routes; Admin/Manager/Partner retain access as intended.
TASK_DESC_2,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-002',
                        'title' => 'Create `McpPolicy` and route MCP authorization through policy + object-level checks consistently',
                        'description' => <<<'TASK_DESC_3'
**Original Task ID:** TASK-002 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** REVIEW · **Source Requirement ID(s):** REV-002
**Original Status:** TODO · **Original Priority:** HIGH

Create `McpPolicy` and route MCP authorization through policy + object-level checks consistently
TASK_DESC_3,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-003',
                        'title' => 'Restrict `FileUploadController`\'s generic `file` field to an explicit MIME/extension allow-list; consider private-disk storage for non-image uploads',
                        'description' => <<<'TASK_DESC_4'
**Original Task ID:** TASK-003 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** REVIEW · **Source Requirement ID(s):** REV-003
**Original Status:** TODO · **Original Priority:** HIGH

Restrict `FileUploadController`'s generic `file` field to an explicit MIME/extension allow-list; consider private-disk storage for non-image uploads

#### Harden generic file upload endpoint

- **Type:** [SECURITY]
- **Source:** REVIEW · **Source ID:** REV-003 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** COM-029 commits to "uploaded file validation" as part of the security checklist.
- **Current State:** `app/Http/Controllers/FileUploadController.php:15-18` validates the generic `file` field only as `['nullable','file','max:5120']` with no MIME/extension allow-list, and stores it on the **public** disk with an immediately-returned public URL. The parallel `image` field IS correctly restricted to `jpeg,png,jpg,gif,svg`.
- **Gap:** Arbitrary file types (including executable/script content) can be uploaded and are immediately publicly hosted under the application's own domain.
- **Required Change:** Add an explicit allow-list (e.g. pdf, doc, docx, xls, xlsx per the "curricula/training materials" use case in CR-005) and reconsider whether these attachments need to be on a public vs. authenticated-access disk.
- **Dependencies:** None.
- **Acceptance Criteria:** Uploading a disallowed file type is rejected with a validation error; allowed document types continue to work for attachments (Feedback, Event, Stakeholder, Post Training).
TASK_DESC_4,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-004',
                        'title' => 'Switch `SESSION_DRIVER` to `database` in production config, per COM-029',
                        'description' => <<<'TASK_DESC_5'
**Original Task ID:** TASK-004 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-029
**Original Status:** TODO · **Original Priority:** MEDIUM

Switch `SESSION_DRIVER` to `database` in production config, per COM-029
TASK_DESC_5,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: SRS Commitment', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-005',
                        'title' => 'Implement IP-based session lock/invalidation-on-anomaly per COM-029 (or document decision not to, with UNICEF sign-off)',
                        'description' => <<<'TASK_DESC_6'
**Original Task ID:** TASK-005 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-029
**Original Status:** TODO · **Original Priority:** MEDIUM

Implement IP-based session lock/invalidation-on-anomaly per COM-029 (or document decision not to, with UNICEF sign-off)
TASK_DESC_6,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: SRS Commitment', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-006',
                        'title' => 'Evaluate adding 2FA to admin/manager-level web login (no current implementation)',
                        'description' => <<<'TASK_DESC_7'
**Original Task ID:** TASK-006 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** REVIEW · **Source Requirement ID(s):** REV-005
**Original Status:** TODO · **Original Priority:** LOW

Evaluate adding 2FA to admin/manager-level web login (no current implementation)
TASK_DESC_7,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-007',
                        'title' => 'Verify `FeedbackRequest` sanitizes/escapes `description` before it is rendered via `v-html` in `Feedback/Details.vue`; add sanitization if feedback can originate from low-trust/public sources',
                        'description' => <<<'TASK_DESC_8'
**Original Task ID:** TASK-007 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** REVIEW · **Source Requirement ID(s):** REV-006
**Original Status:** TODO · **Original Priority:** MEDIUM

Verify `FeedbackRequest` sanitizes/escapes `description` before it is rendered via `v-html` in `Feedback/Details.vue`; add sanitization if feedback can originate from low-trust/public sources
TASK_DESC_8,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-008',
                        'title' => 'Confirm with UNICEF whether a mobile "SSO" mechanism (COM-028) is still required; if yes, scope an OAuth/OIDC integration alongside Sanctum',
                        'description' => <<<'TASK_DESC_9'
**Original Task ID:** TASK-008 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-028
**Original Status:** TODO · **Original Priority:** LOW

Confirm with UNICEF whether a mobile "SSO" mechanism (COM-028) is still required; if yes, scope an OAuth/OIDC integration alongside Sanctum
TASK_DESC_9,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-009',
                        'title' => 'Commission/attach the UNICEF-mandated third-party security assessment (CR-014) and track its findings as follow-up tasks',
                        'description' => <<<'TASK_DESC_10'
**Original Task ID:** TASK-009 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** CLIENT · **Source Requirement ID(s):** CR-014
**Original Status:** TODO · **Original Priority:** HIGH

Commission/attach the UNICEF-mandated third-party security assessment (CR-014) and track its findings as follow-up tasks
TASK_DESC_10,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Research',
                        'labels' => ['Source: Client Requirement (ToR)', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-010',
                        'title' => 'Audit all Service classes (`LearnerService`, `TrainerService`, `EnterpriseService`, etc.) for the same partner-scoping pattern found in `McpService`, to confirm no IDOR gap exists elsewhere',
                        'description' => <<<'TASK_DESC_11'
**Original Task ID:** TASK-010 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** REVIEW · **Source Requirement ID(s):** REV-002
**Original Status:** TODO · **Original Priority:** HIGH

Audit all Service classes (`LearnerService`, `TrainerService`, `EnterpriseService`, etc.) for the same partner-scoping pattern found in `McpService`, to confirm no IDOR gap exists elsewhere
TASK_DESC_11,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-011',
                        'title' => 'Reconcile the two parallel tenancy mechanisms (`tenant`/`tenant_alp`/`tenant_skilfo` global-scope traits vs. ad hoc `partner_id` filtering in services) into one documented, centrally-enforced approach',
                        'description' => <<<'TASK_DESC_12'
**Original Task ID:** TASK-011 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** REVIEW · **Source Requirement ID(s):** REV-008
**Original Status:** TODO · **Original Priority:** MEDIUM

Reconcile the two parallel tenancy mechanisms (`tenant`/`tenant_alp`/`tenant_skilfo` global-scope traits vs. ad hoc `partner_id` filtering in services) into one documented, centrally-enforced approach
TASK_DESC_12,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-012',
                        'title' => 'Add `SoftDeletes` + `Versionable` to `Enterprise` and `Assessor` models for parity with Trainer/MCP/Learner restore functionality',
                        'description' => <<<'TASK_DESC_13'
**Original Task ID:** TASK-012 (`ALP-RTM_TASKS.md`, Module 1 — Authentication, RBAC & Security)
**Source:** REVIEW · **Source Requirement ID(s):** REV-007
**Original Status:** TODO · **Original Priority:** MEDIUM

Add `SoftDeletes` + `Versionable` to `Enterprise` and `Assessor` models for parity with Trainer/MCP/Learner restore functionality
TASK_DESC_13,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 2,
                'name' => '02 — Multi-Tenancy & Partner-Level Data Isolation',
                'description' => <<<'MODULE_DESC_14'
**Requirement Sources:** CLIENT: CR-013, CR-021 · REVIEW: REV-002, REV-008

**Current Implementation:**
Two coexisting isolation mechanisms: (a) `HasSingleTenant`/`HasMultipleTenant` traits applying a global scope on `tenant`/`tenant_alp`/`tenant_skilfo` boolean columns (ALP vs Skilfo separation) on Profile/Mcp/Trainer/Enterprise/Assessor; (b) hand-written `partner_id` filtering inside individual Service classes (confirmed correct in `McpService`, not exhaustively verified elsewhere) for partner-level data segregation. `SkilfoDefaultPartnerDonorScope` only applies to Skilfo-only users, not general Partner-role users.

**Requirement Gap:**
The dual mechanism is functional where checked but architecturally duplicated — a new Service class that forgets the manual `partner_id` filter reopens an IDOR without any centralized safety net.

_Imported from `ALP-RTM_TASKS.md`, Module 2 — Multi-Tenancy & Partner-Level Data Isolation. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_14,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-013',
                        'title' => 'Design and adopt a single centralized partner/tenant scoping mechanism (e.g., a global scope applied uniformly rather than per-service)',
                        'description' => <<<'TASK_DESC_15'
**Original Task ID:** TASK-013 (`ALP-RTM_TASKS.md`, Module 2 — Multi-Tenancy & Partner-Level Data Isolation)
**Source:** REVIEW · **Source Requirement ID(s):** REV-008
**Original Status:** TODO · **Original Priority:** MEDIUM

Design and adopt a single centralized partner/tenant scoping mechanism (e.g., a global scope applied uniformly rather than per-service)
TASK_DESC_15,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-014',
                        'title' => 'Audit `LearnerService`, `TrainerService`, `EnterpriseService`, `AssessorService` `show()`/`edit()` methods for the same partner-scoping guarantee confirmed in `McpService`',
                        'description' => <<<'TASK_DESC_16'
**Original Task ID:** TASK-014 (`ALP-RTM_TASKS.md`, Module 2 — Multi-Tenancy & Partner-Level Data Isolation)
**Source:** REVIEW · **Source Requirement ID(s):** REV-002
**Original Status:** TODO · **Original Priority:** HIGH

Audit `LearnerService`, `TrainerService`, `EnterpriseService`, `AssessorService` `show()`/`edit()` methods for the same partner-scoping guarantee confirmed in `McpService`
TASK_DESC_16,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-015',
                        'title' => 'Document the ALP vs. Skilfo tenant boundary (what data is shared vs. isolated) for the dev team, since this is currently implicit in scattered trait usage',
                        'description' => <<<'TASK_DESC_17'
**Original Task ID:** TASK-015 (`ALP-RTM_TASKS.md`, Module 2 — Multi-Tenancy & Partner-Level Data Isolation)
**Source:** REVIEW · **Source Requirement ID(s):** —
**Original Status:** TODO · **Original Priority:** LOW

Document the ALP vs. Skilfo tenant boundary (what data is shared vs. isolated) for the dev team, since this is currently implicit in scattered trait usage
TASK_DESC_17,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-016',
                        'title' => 'Confirm CR-021 (no external data sharing without UNICEF permission) is reflected in any cross-tenant/cross-partner export or API surface — verify exports can\'t leak another partner\'s data',
                        'description' => <<<'TASK_DESC_18'
**Original Task ID:** TASK-016 (`ALP-RTM_TASKS.md`, Module 2 — Multi-Tenancy & Partner-Level Data Isolation)
**Source:** CLIENT · **Source Requirement ID(s):** CR-021
**Original Status:** TODO · **Original Priority:** MEDIUM

Confirm CR-021 (no external data sharing without UNICEF permission) is reflected in any cross-tenant/cross-partner export or API surface — verify exports can't leak another partner's data
TASK_DESC_18,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Client Requirement (ToR)', 'Security'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 3,
                'name' => '03 — User & Role Management',
                'description' => <<<'MODULE_DESC_19'
**Requirement Sources:** COMMITMENT: COM-007, COM-008

**Current Implementation:**
IMPLEMENTED. Full CRUD for users/roles/permissions via `RolesController`/`UserController`, `spatie/laravel-permission`-backed, matching the SRS's Add-User screen and permission-tree screenshots closely.

**Requirement Gap:**
None material found beyond the object-level authorization gaps already tracked in Module 1.

_Imported from `ALP-RTM_TASKS.md`, Module 3 — User & Role Management. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_19,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Admin Panel',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-017',
                        'title' => 'Spot-check that the live permission tree UI (Expand All/Collapse All, per-module checkboxes) still matches the SRS screenshot\'s granularity as new modules (Assessor, Enterprise, BNFE) were added',
                        'description' => <<<'TASK_DESC_20'
**Original Task ID:** TASK-017 (`ALP-RTM_TASKS.md`, Module 3 — User & Role Management)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-007
**Original Status:** TODO · **Original Priority:** LOW

Spot-check that the live permission tree UI (Expand All/Collapse All, per-module checkboxes) still matches the SRS screenshot's granularity as new modules (Assessor, Enterprise, BNFE) were added
TASK_DESC_20,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-018',
                        'title' => 'Confirm newly-added roles (Assessor, Director General, Director M&E, AD DBNFE) have a documented, UNICEF-approved permission set, not just inherited defaults',
                        'description' => <<<'TASK_DESC_21'
**Original Task ID:** TASK-018 (`ALP-RTM_TASKS.md`, Module 3 — User & Role Management)
**Source:** REVIEW · **Source Requirement ID(s):** —
**Original Status:** TODO · **Original Priority:** MEDIUM

Confirm newly-added roles (Assessor, Director General, Director M&E, AD DBNFE) have a documented, UNICEF-approved permission set, not just inherited defaults
TASK_DESC_21,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-019',
                        'title' => 'Verify "can change user password" (Superadmin/Admin only per SRS matrix) is still correctly restricted after later role additions',
                        'description' => <<<'TASK_DESC_22'
**Original Task ID:** TASK-019 (`ALP-RTM_TASKS.md`, Module 3 — User & Role Management)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-008
**Original Status:** TODO · **Original Priority:** LOW

Verify "can change user password" (Superadmin/Admin only per SRS matrix) is still correctly restricted after later role additions
TASK_DESC_22,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 4,
                'name' => '04 — Profile: Development Partner (Donor)',
                'description' => <<<'MODULE_DESC_23'
**Requirement Sources:** COMMITMENT: COM-009

**Current Implementation:**
IMPLEMENTED. `Donor` model has all committed fields (name, bn_name, description, total_fund, image, status); full CRUD in web + API V1/V2.

**Requirement Gap:**
None found.

_Imported from `ALP-RTM_TASKS.md`, Module 4 — Profile: Development Partner (Donor). See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_23,
                'status' => 'completed',
                'priority' => 'low',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-020',
                        'title' => 'No action needed — mark COM-009 as verified complete in the next SRS sign-off',
                        'description' => <<<'TASK_DESC_24'
**Original Task ID:** TASK-020 (`ALP-RTM_TASKS.md`, Module 4 — Profile: Development Partner (Donor))
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-009
**Original Status:** NOT APPLICABLE · **Original Priority:** LOW

No action needed — mark COM-009 as verified complete in the next SRS sign-off
TASK_DESC_24,
                        'status' => 'cancelled',
                        'priority' => 'low',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: SRS Commitment'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 5,
                'name' => '05 — Profile: Implementing Partner',
                'description' => <<<'MODULE_DESC_25'
**Requirement Sources:** COMMITMENT: COM-010 · UAT: UAT-016

**Current Implementation:**
IMPLEMENTED. `Partner` model covers type/donor/contact/email/mobile/phone/address/URL/logo/status/feedback relation, matching COM-010.

**Requirement Gap:**
A confirmed related bug (see UAT-016 traceability) exists in how partner/donor/trade names are serialized in the V1 mobile API resource.

_Imported from `ALP-RTM_TASKS.md`, Module 5 — Profile: Implementing Partner. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_25,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-021',
                        'title' => 'Fix duplicate `\'trade\'` array key in `app/Http/Resources/V1/Profile/ProfileApiResource.php:97-99` (currently `trade.name` then `partner.name` then `donor.name` all assigned to the same key, so the donor\'s name silently wins)',
                        'description' => <<<'TASK_DESC_26'
**Original Task ID:** TASK-021 (`ALP-RTM_TASKS.md`, Module 5 — Profile: Implementing Partner)
**Source:** UAT · **Source Requirement ID(s):** UAT-016
**Original Status:** TODO · **Original Priority:** HIGH

Fix duplicate `'trade'` array key in `app/Http/Resources/V1/Profile/ProfileApiResource.php:97-99` (currently `trade.name` then `partner.name` then `donor.name` all assigned to the same key, so the donor's name silently wins)

#### Fix duplicate-key bug in V1 Profile API resource

- **Type:** [BUG]
- **Source:** UAT · **Source ID:** UAT-016 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** A partner's correct name (e.g. "Jagorani Chakra Foundation") should display wherever the UI shows the associated Implementing Partner; a QA reviewer reported it instead showing "Let Us Learn" (a seeded **Donor** name).
- **Current State:** `app/Http/Resources/V1/Profile/ProfileApiResource.php:97-99` builds a response array with **three separate entries all keyed `'trade'`** — `trade.name`, then `partner.name`, then `donor.name` — so in PHP the last assignment silently overwrites the first two, and any consumer reading that key gets the donor's name instead of the trade or partner. The equivalent V2 resource does not have this duplication.
- **Gap:** Confirmed code defect in API V1 only.
- **Required Change:** Give each field its own distinct key (e.g. `trade`, `partner`, `donor`) in `ProfileApiResource` (V1).
- **Dependencies:** None.
- **Acceptance Criteria:** The V1 mobile API response includes correct, distinct trade/partner/donor names; the specific screen the QA reviewer flagged shows the correct partner name.
TASK_DESC_26,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Bug',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 6,
                'name' => '06 — Profile: Stakeholder',
                'description' => <<<'MODULE_DESC_27'
**Requirement Sources:** COMMITMENT: COM-011

**Current Implementation:**
IMPLEMENTED. Backed by `Partnership` model (not a literal "Stakeholder" table) — full field/attachment/status coverage matching COM-011.

_Imported from `ALP-RTM_TASKS.md`, Module 6 — Profile: Stakeholder. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_27,
                'status' => 'in_progress',
                'priority' => 'low',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-022',
                        'title' => 'Document that "Stakeholder" in the SRS maps to the `Partnership` model in code, to avoid future confusion during onboarding/handover',
                        'description' => <<<'TASK_DESC_28'
**Original Task ID:** TASK-022 (`ALP-RTM_TASKS.md`, Module 6 — Profile: Stakeholder)
**Source:** REVIEW · **Source Requirement ID(s):** —
**Original Status:** TODO · **Original Priority:** LOW

Document that "Stakeholder" in the SRS maps to the `Partnership` model in code, to avoid future confusion during onboarding/handover
TASK_DESC_28,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 7,
                'name' => '07 — Profile: Trainer',
                'description' => <<<'MODULE_DESC_29'
**Requirement Sources:** COMMITMENT: COM-012 · NEW: NEW-012, NEW-014

**Current Implementation:**
IMPLEMENTED BUT NEEDS REVIEW. Full field coverage, versioning, soft-delete+restore all confirmed. NEW-014 fields (Email, Industry Experience, Other Experience, auto-calculated Total Experience, "Teaching Experience" relabel) are already implemented for Trainer.

**Requirement Gap:**
The detail/view page has Training Centers, Training Details, and Learners tabs but **no Logbook tab**, despite COM-012 explicitly committing to a "User Logbooks" timeline view.

_Imported from `ALP-RTM_TASKS.md`, Module 7 — Profile: Trainer. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_29,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-023',
                        'title' => 'Add a "Logbooks" tab to Trainer\'s detail page (`Trainer/Details.vue`), backed by the existing `logbooks` relation',
                        'description' => <<<'TASK_DESC_30'
**Original Task ID:** TASK-023 (`ALP-RTM_TASKS.md`, Module 7 — Profile: Trainer)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-012
**Original Status:** TODO · **Original Priority:** MEDIUM

Add a "Logbooks" tab to Trainer's detail page (`Trainer/Details.vue`), backed by the existing `logbooks` relation
TASK_DESC_30,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: SRS Commitment'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-024',
                        'title' => 'Verify Trainer\'s detail-page controller eager-loads the logbook relation needed to power the new tab',
                        'description' => <<<'TASK_DESC_31'
**Original Task ID:** TASK-024 (`ALP-RTM_TASKS.md`, Module 7 — Profile: Trainer)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-012
**Original Status:** TODO · **Original Priority:** MEDIUM

Verify Trainer's detail-page controller eager-loads the logbook relation needed to power the new tab
TASK_DESC_31,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-025',
                        'title' => 'UAT-010: expose a "my attendance" self-service view for Trainer/Frontline users',
                        'description' => <<<'TASK_DESC_32'
**Original Task ID:** TASK-025 (`ALP-RTM_TASKS.md`, Module 7 — Profile: Trainer)
**Source:** UAT · **Source Requirement ID(s):** UAT-010
**Original Status:** TODO · **Original Priority:** MEDIUM

UAT-010: expose a "my attendance" self-service view for Trainer/Frontline users
TASK_DESC_32,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-026',
                        'title' => 'UAT-006/007: extend Gender enum beyond Male/Female/Others, and add "child marriage" to Marital Status (Divorced already exists)',
                        'description' => <<<'TASK_DESC_33'
**Original Task ID:** TASK-026 (`ALP-RTM_TASKS.md`, Module 7 — Profile: Trainer)
**Source:** UAT · **Source Requirement ID(s):** UAT-006, UAT-007
**Original Status:** TODO · **Original Priority:** MEDIUM

UAT-006/007: extend Gender enum beyond Male/Female/Others, and add "child marriage" to Marital Status (Divorced already exists)
TASK_DESC_33,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 8,
                'name' => '08 — Profile: Mastercraft Person (MCP)',
                'description' => <<<'MODULE_DESC_34'
**Requirement Sources:** COMMITMENT: COM-013 · NEW: NEW-013, NEW-014 · UAT: UAT-023

**Current Implementation:**
IMPLEMENTED BUT NEEDS REVIEW. Full field coverage (incl. exact Yes/No/Other toilet-facility match to spec), versioning, soft-delete+restore, and NEW-013 MCP↔Enterprise linkage all confirmed working, including a real migration that removes duplicate fields between MCP and Enterprise.

**Requirement Gap:**
Same missing Logbook tab as Trainer. NEW-014 fields (Email/Industry Experience/Other Experience/Total Experience, "Teaching Experience" relabel) were rolled out to Trainer but **not to MCP**, an inconsistency. UAT-023's duplicate-learner-rows bug was not yet investigated in code.

_Imported from `ALP-RTM_TASKS.md`, Module 8 — Profile: Mastercraft Person (MCP). See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_34,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-027',
                        'title' => 'Add "Logbooks" tab to MCP\'s detail page, same as Trainer (TASK-023)',
                        'description' => <<<'TASK_DESC_35'
**Original Task ID:** TASK-027 (`ALP-RTM_TASKS.md`, Module 8 — Profile: Mastercraft Person (MCP))
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-013
**Original Status:** TODO · **Original Priority:** MEDIUM

Add "Logbooks" tab to MCP's detail page, same as Trainer (TASK-023)
TASK_DESC_35,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: SRS Commitment'],
                        'crossrefs' => ['TASK-023'],
                    ],
                    [
                        'orig_task_id' => 'TASK-028',
                        'title' => 'Add `email`, `industry_experience`, `other_experience`, auto-calculated `total_experience` fields to MCP; relabel "Business Experience" to "Teaching Experience" per NEW-014, for parity with Trainer',
                        'description' => <<<'TASK_DESC_36'
**Original Task ID:** TASK-028 (`ALP-RTM_TASKS.md`, Module 8 — Profile: Mastercraft Person (MCP))
**Source:** NEW · **Source Requirement ID(s):** NEW-014
**Original Status:** TODO · **Original Priority:** MEDIUM

Add `email`, `industry_experience`, `other_experience`, auto-calculated `total_experience` fields to MCP; relabel "Business Experience" to "Teaching Experience" per NEW-014, for parity with Trainer
TASK_DESC_36,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-029',
                        'title' => 'Investigate and fix UAT-023: MCP profile → Learners tab shows the same learner ("Sobuj") repeated 3× with identical DOB/mobile — check the MCP→Learners list query for a join producing duplicate rows (e.g. multiple pivot/attendance rows per…',
                        'description' => <<<'TASK_DESC_37'
**Original Task ID:** TASK-029 (`ALP-RTM_TASKS.md`, Module 8 — Profile: Mastercraft Person (MCP))
**Source:** UAT · **Source Requirement ID(s):** UAT-023
**Original Status:** TODO · **Original Priority:** HIGH

Investigate and fix UAT-023: MCP profile → Learners tab shows the same learner ("Sobuj") repeated 3× with identical DOB/mobile — check the MCP→Learners list query for a join producing duplicate rows (e.g. multiple pivot/attendance rows per learner) rather than distinct learners

#### Investigate duplicate learner rows under MCP profile

- **Type:** [BUG]
- **Source:** UAT · **Source ID:** UAT-023 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** An MCP's detail page "Learners" tab should list each distinct learner associated with that MCP once.
- **Current State:** UAT screenshot shows a single MCP's Learners tab listing "Sobuj" three times with identical DOB (5th September 2024) and mobile number (01722043839) — "Total: 3".
- **Gap:** This was not directly investigated by the code-review agents in this pass (an oversight in the audit scope) — needs a dedicated follow-up: check the query backing the MCP→Learners tab for a join that fans out per attendance/training/pivot record rather than grouping by distinct learner.
- **Required Change:** TBD pending investigation; likely a missing `distinct()`/`groupBy()` or an incorrect join cardinality.
- **Dependencies:** None.
- **Acceptance Criteria:** Each learner appears exactly once per MCP, regardless of how many trainings/attendance records they have with that MCP.
TASK_DESC_37,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Bug',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-030',
                        'title' => 'Fix `Assessor::newUniqueId()` copy-paste bug referencing a non-existent `upazila_id` on Assessor (unrelated model, but same code pattern worth checking on MCP\'s own `newUniqueId()` for a similar latent bug)',
                        'description' => <<<'TASK_DESC_38'
**Original Task ID:** TASK-030 (`ALP-RTM_TASKS.md`, Module 8 — Profile: Mastercraft Person (MCP))
**Source:** REVIEW · **Source Requirement ID(s):** REV-010
**Original Status:** TODO · **Original Priority:** LOW

Fix `Assessor::newUniqueId()` copy-paste bug referencing a non-existent `upazila_id` on Assessor (unrelated model, but same code pattern worth checking on MCP's own `newUniqueId()` for a similar latent bug)
TASK_DESC_38,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-031',
                        'title' => 'UAT-018: add a "CwD" (Children with Disabilities) classification — clarify with UNICEF whether this is a new boolean flag on Learner or a Disability sub-category',
                        'description' => <<<'TASK_DESC_39'
**Original Task ID:** TASK-031 (`ALP-RTM_TASKS.md`, Module 8 — Profile: Mastercraft Person (MCP))
**Source:** UAT · **Source Requirement ID(s):** UAT-018
**Original Status:** TODO · **Original Priority:** MEDIUM

UAT-018: add a "CwD" (Children with Disabilities) classification — clarify with UNICEF whether this is a new boolean flag on Learner or a Disability sub-category
TASK_DESC_39,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-032',
                        'title' => 'UAT-008: add a persisted age-group field (child/adult/**youth**) — currently only a binary child/adult split is computed on the fly for filtering, no stored 3-tier value and no "youth" tier at all',
                        'description' => <<<'TASK_DESC_40'
**Original Task ID:** TASK-032 (`ALP-RTM_TASKS.md`, Module 8 — Profile: Mastercraft Person (MCP))
**Source:** UAT · **Source Requirement ID(s):** UAT-008
**Original Status:** TODO · **Original Priority:** MEDIUM

UAT-008: add a persisted age-group field (child/adult/**youth**) — currently only a binary child/adult split is computed on the fly for filtering, no stored 3-tier value and no "youth" tier at all
TASK_DESC_40,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 9,
                'name' => '09 — Profile: Learner',
                'description' => <<<'MODULE_DESC_41'
**Requirement Sources:** COMMITMENT: COM-014 · UAT: UAT-006, UAT-007, UAT-008, UAT-012, UAT-018, UAT-020 · NEW: NEW-012

**Current Implementation:**
IMPLEMENTED, minor gaps. Full field coverage, versioning, soft-delete+restore confirmed. UAT-012 (intervention/"Modality" column) and UAT-020 (Dropout Reason) are **already resolved** — both wired end-to-end, contrary to how they might read as open complaints.

**Requirement Gap:**
No Logbook tab on the Learner detail page despite the controller already eager-loading the relation (data is fetched but not rendered). Gender/marital-status/age-group/CwD gaps shared with Trainer/MCP (see Module 7/8 tasks — do not duplicate work, fix once at the shared enum/attribute level).

_Imported from `ALP-RTM_TASKS.md`, Module 9 — Profile: Learner. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_41,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-033',
                        'title' => 'Add "Logbooks" tab to Learner\'s detail page — data (`logbooks` relation) is already eager-loaded by the controller but not rendered in `Details.vue`',
                        'description' => <<<'TASK_DESC_42'
**Original Task ID:** TASK-033 (`ALP-RTM_TASKS.md`, Module 9 — Profile: Learner)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-014
**Original Status:** TODO · **Original Priority:** MEDIUM

Add "Logbooks" tab to Learner's detail page — data (`logbooks` relation) is already eager-loaded by the controller but not rendered in `Details.vue`
TASK_DESC_42,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: SRS Commitment'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-034',
                        'title' => 'Implement UAT-006 (multi/extended gender options) at the shared `app/Enums/Gender.php` level so Learner/Trainer/MCP/Assessor all update together',
                        'description' => <<<'TASK_DESC_43'
**Original Task ID:** TASK-034 (`ALP-RTM_TASKS.md`, Module 9 — Profile: Learner)
**Source:** UAT · **Source Requirement ID(s):** UAT-006
**Original Status:** TODO · **Original Priority:** MEDIUM

Implement UAT-006 (multi/extended gender options) at the shared `app/Enums/Gender.php` level so Learner/Trainer/MCP/Assessor all update together
TASK_DESC_43,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-035',
                        'title' => 'Implement UAT-007 ("child marriage" marital-status option) at the shared `MaritalStatus` enum level',
                        'description' => <<<'TASK_DESC_44'
**Original Task ID:** TASK-035 (`ALP-RTM_TASKS.md`, Module 9 — Profile: Learner)
**Source:** UAT · **Source Requirement ID(s):** UAT-007
**Original Status:** TODO · **Original Priority:** MEDIUM

Implement UAT-007 ("child marriage" marital-status option) at the shared `MaritalStatus` enum level
TASK_DESC_44,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-036',
                        'title' => 'Implement UAT-008 (persisted 3-tier age-group field: child/adult/youth) as a real column, not just an on-the-fly filter',
                        'description' => <<<'TASK_DESC_45'
**Original Task ID:** TASK-036 (`ALP-RTM_TASKS.md`, Module 9 — Profile: Learner)
**Source:** UAT · **Source Requirement ID(s):** UAT-008
**Original Status:** TODO · **Original Priority:** MEDIUM

Implement UAT-008 (persisted 3-tier age-group field: child/adult/youth) as a real column, not just an on-the-fly filter
TASK_DESC_45,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-037',
                        'title' => 'Implement UAT-018 (CwD classification) on Learner',
                        'description' => <<<'TASK_DESC_46'
**Original Task ID:** TASK-037 (`ALP-RTM_TASKS.md`, Module 9 — Profile: Learner)
**Source:** UAT · **Source Requirement ID(s):** UAT-018
**Original Status:** TODO · **Original Priority:** MEDIUM

Implement UAT-018 (CwD classification) on Learner
TASK_DESC_46,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-038',
                        'title' => 'No action needed for UAT-012 (intervention/"Modality" already shown on Learner list/detail) — close as resolved',
                        'description' => <<<'TASK_DESC_47'
**Original Task ID:** TASK-038 (`ALP-RTM_TASKS.md`, Module 9 — Profile: Learner)
**Source:** UAT · **Source Requirement ID(s):** UAT-012
**Original Status:** COMPLETED · **Original Priority:** LOW

No action needed for UAT-012 (intervention/"Modality" already shown on Learner list/detail) — close as resolved
TASK_DESC_47,
                        'status' => 'done',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-039',
                        'title' => 'No action needed for UAT-020 (Dropout Reason already required/validated at training-enrollment level) — close as resolved, but confirm this satisfies the client\'s intent (it\'s on the Training enrollment record, not directly on the Learner profile…',
                        'description' => <<<'TASK_DESC_48'
**Original Task ID:** TASK-039 (`ALP-RTM_TASKS.md`, Module 9 — Profile: Learner)
**Source:** UAT · **Source Requirement ID(s):** UAT-020
**Original Status:** COMPLETED · **Original Priority:** LOW

No action needed for UAT-020 (Dropout Reason already required/validated at training-enrollment level) — close as resolved, but confirm this satisfies the client's intent (it's on the Training enrollment record, not directly on the Learner profile — verify this distinction is acceptable)
TASK_DESC_48,
                        'status' => 'done',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 10,
                'name' => '10 — Profile: Training Center',
                'description' => <<<'MODULE_DESC_49'
**Requirement Sources:** COMMITMENT: COM-015 · NEW: NEW-008, NEW-012

**Current Implementation:**
IMPLEMENTED. Trainer selection is confirmed dynamically filtered by partner+location, exactly matching COM-015. Bilingual (EN/BN) name and institution-head fields already present, satisfying that part of NEW-012.

**Requirement Gap:**
NEW-008's "Institution Database" as a possibly-distinct module (head of institution, coordinator, course count, seats, venue code) is not implemented as a separate entity — needs product clarification on whether it's the same as Training Center or genuinely separate (see Open Questions).

_Imported from `ALP-RTM_TASKS.md`, Module 10 — Profile: Training Center. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_49,
                'status' => 'on_hold',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-040',
                        'title' => 'Get client clarification on whether "Institution Database" (NEW-008) is meant to extend Training Center with more fields (venue code, seat count, coordinator) or be a wholly separate module — see Open Questions OQ-005',
                        'description' => <<<'TASK_DESC_50'
**Original Task ID:** TASK-040 (`ALP-RTM_TASKS.md`, Module 10 — Profile: Training Center)
**Source:** NEW · **Source Requirement ID(s):** NEW-008
**Original Status:** BLOCKED · **Original Priority:** MEDIUM

Get client clarification on whether "Institution Database" (NEW-008) is meant to extend Training Center with more fields (venue code, seat count, coordinator) or be a wholly separate module — see Open Questions OQ-005

**Related Open Question(s):**
- OQ-005: Should "Institution" (NEW-008) be a fully independent module, or always linked to Training Centers/Courses?
TASK_DESC_50,
                        'status' => 'blocked',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Skilfo New Requirement', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 11,
                'name' => '11 — Profile: Competency Standard',
                'description' => <<<'MODULE_DESC_51'
**Requirement Sources:** COMMITMENT: COM-016 · UAT: UAT-013

**Current Implementation:**
IMPLEMENTED, one field gap. Occupation/category/repeatable-competency structure matches COM-016 closely.

**Requirement Gap:**
The "Unit Code" sub-field committed in COM-016 is missing from the `competency_skills` migration entirely. Also, per UAT-013, Competency Standard has zero integration with the Monitoring module.

_Imported from `ALP-RTM_TASKS.md`, Module 11 — Profile: Competency Standard. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_51,
                'status' => 'in_progress',
                'priority' => 'low',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-041',
                        'title' => 'Add the missing "Unit Code" column to Competency Skill, per COM-016',
                        'description' => <<<'TASK_DESC_52'
**Original Task ID:** TASK-041 (`ALP-RTM_TASKS.md`, Module 11 — Profile: Competency Standard)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-016
**Original Status:** TODO · **Original Priority:** LOW

Add the missing "Unit Code" column to Competency Skill, per COM-016
TASK_DESC_52,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: SRS Commitment'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 12,
                'name' => '12 — Profile: Assessor (Skilfo)',
                'description' => <<<'MODULE_DESC_53'
**Requirement Sources:** NEW: NEW-002 · REVIEW: REV-010

**Current Implementation:**
PARTIALLY IMPLEMENTED. Field coverage is strong and matches the Skilfo spec closely (name EN/BN, registration no., mobile/email, occupation, methodology, workplace, district, qualification, Rocket account no.).

**Requirement Gap:**
No "assessor dashboard" exists — only standard CRUD pages — despite NEW-002 explicitly calling for one (distinct from the assessor-facing assignment list covered under Module 21). A latent bug references a non-existent `upazila_id` property.

_Imported from `ALP-RTM_TASKS.md`, Module 12 — Profile: Assessor (Skilfo). See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_53,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-042',
                        'title' => 'Design and build a dedicated Assessor-facing dashboard (distinct from admin CRUD) — likely showing assigned learners, assessment progress, certificates issued',
                        'description' => <<<'TASK_DESC_54'
**Original Task ID:** TASK-042 (`ALP-RTM_TASKS.md`, Module 12 — Profile: Assessor (Skilfo))
**Source:** NEW · **Source Requirement ID(s):** NEW-002
**Original Status:** TODO · **Original Priority:** MEDIUM

Design and build a dedicated Assessor-facing dashboard (distinct from admin CRUD) — likely showing assigned learners, assessment progress, certificates issued
TASK_DESC_54,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-043',
                        'title' => 'Fix `Assessor::newUniqueId()` referencing `$this->upazila_id`, a property that doesn\'t exist on the Assessor model/migration — currently silently resolves to null rather than erroring, which may produce malformed unique IDs',
                        'description' => <<<'TASK_DESC_55'
**Original Task ID:** TASK-043 (`ALP-RTM_TASKS.md`, Module 12 — Profile: Assessor (Skilfo))
**Source:** REVIEW · **Source Requirement ID(s):** REV-010
**Original Status:** TODO · **Original Priority:** MEDIUM

Fix `Assessor::newUniqueId()` referencing `$this->upazila_id`, a property that doesn't exist on the Assessor model/migration — currently silently resolves to null rather than erroring, which may produce malformed unique IDs
TASK_DESC_55,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-044',
                        'title' => 'Add `SoftDeletes`/`Versionable` to Assessor for parity with other profile types (cross-ref TASK-012)',
                        'description' => <<<'TASK_DESC_56'
**Original Task ID:** TASK-044 (`ALP-RTM_TASKS.md`, Module 12 — Profile: Assessor (Skilfo))
**Source:** REVIEW · **Source Requirement ID(s):** REV-007
**Original Status:** TODO · **Original Priority:** LOW

Add `SoftDeletes`/`Versionable` to Assessor for parity with other profile types (cross-ref TASK-012)
TASK_DESC_56,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => ['TASK-012'],
                    ],
                ],
            ],
            [
                'module_num' => 13,
                'name' => '13 — Profile: Enterprise (Skilfo)',
                'description' => <<<'MODULE_DESC_57'
**Requirement Sources:** NEW: NEW-001, NEW-013 · REVIEW: REV-011

**Current Implementation:**
IMPLEMENTED, ~85-90% field coverage. GPS, owner info, and the MCP-suitability rating fields (communication skill, reputation, inclusivity, provisions, cleanliness, toilet facility, clean water, social security) are all present via well-modeled enums. MCP↔Enterprise linkage (NEW-013) is fully implemented, including a migration that removes duplicated fields.

**Requirement Gap:**
No distinct "personal conduct" rating field; only student capacity is tracked (no separate employee capacity); an orphaned `qualification()` relation exists with no backing `qualification_id` column.

_Imported from `ALP-RTM_TASKS.md`, Module 13 — Profile: Enterprise (Skilfo). See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_57,
                'status' => 'in_progress',
                'priority' => 'low',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-045',
                        'title' => 'Add a distinct "personal conduct" rating field to Enterprise, separate from the existing reputation/communication ratings, per NEW-001',
                        'description' => <<<'TASK_DESC_58'
**Original Task ID:** TASK-045 (`ALP-RTM_TASKS.md`, Module 13 — Profile: Enterprise (Skilfo))
**Source:** NEW · **Source Requirement ID(s):** NEW-001
**Original Status:** TODO · **Original Priority:** LOW

Add a distinct "personal conduct" rating field to Enterprise, separate from the existing reputation/communication ratings, per NEW-001
TASK_DESC_58,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-046',
                        'title' => 'Add an "employee capacity" field to Enterprise (currently only `student_capacity` exists)',
                        'description' => <<<'TASK_DESC_59'
**Original Task ID:** TASK-046 (`ALP-RTM_TASKS.md`, Module 13 — Profile: Enterprise (Skilfo))
**Source:** NEW · **Source Requirement ID(s):** NEW-001
**Original Status:** TODO · **Original Priority:** LOW

Add an "employee capacity" field to Enterprise (currently only `student_capacity` exists)
TASK_DESC_59,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-047',
                        'title' => 'Remove or properly back the orphaned `qualification()` relation on the `Enterprise` model (no `qualification_id` column exists)',
                        'description' => <<<'TASK_DESC_60'
**Original Task ID:** TASK-047 (`ALP-RTM_TASKS.md`, Module 13 — Profile: Enterprise (Skilfo))
**Source:** REVIEW · **Source Requirement ID(s):** REV-011
**Original Status:** TODO · **Original Priority:** LOW

Remove or properly back the orphaned `qualification()` relation on the `Enterprise` model (no `qualification_id` column exists)
TASK_DESC_60,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 14,
                'name' => '14 — Program: Training Type / Modality',
                'description' => <<<'MODULE_DESC_61'
**Requirement Sources:** COMMITMENT: COM-017

**Current Implementation:**
UNCLEAR. This was not conclusively verified as a distinct CRUD entity separate from `Intervention` (mapped in the UI as "Modality") and `Trade` (mapped as "Occupation") — both of which ARE confirmed implemented under Attribute Setup. It is possible "Training Type" in the SRS maps directly to "Modality"/Intervention rather than being a separate module.

_Imported from `ALP-RTM_TASKS.md`, Module 14 — Program: Training Type / Modality. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_61,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-048',
                        'title' => 'Confirm whether COM-017 "Training Type" is fully satisfied by the existing Intervention ("Modality") + Trade ("Occupation") setup entities, or whether a distinct Training Type CRUD (with "target: learner/MCP/trainer" and "total training hours"…',
                        'description' => <<<'TASK_DESC_62'
**Original Task ID:** TASK-048 (`ALP-RTM_TASKS.md`, Module 14 — Program: Training Type / Modality)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-017
**Original Status:** REVIEW REQUIRED · **Original Priority:** MEDIUM

Confirm whether COM-017 "Training Type" is fully satisfied by the existing Intervention ("Modality") + Trade ("Occupation") setup entities, or whether a distinct Training Type CRUD (with "target: learner/MCP/trainer" and "total training hours" fields) is still missing
TASK_DESC_62,
                        'status' => 'in_review',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 15,
                'name' => '15 — Program: Training/Course (incl. Attendance & Logbook)',
                'description' => <<<'MODULE_DESC_63'
**Requirement Sources:** COMMITMENT: COM-018 · UAT: UAT-017

**Current Implementation:**
IMPLEMENTED BUT NEEDS REVIEW. Full relation model (partner, modality, trade, location, training center, trainers, MCP-trainers, learners w/ dropout+completion pivot, attendances, logbooks, feedbacks). Month-wise and day-wise attendance, "mark as complete" cascading, and feedback linkage are all confirmed working.

**Requirement Gap:**
UAT-017's "blocked fields" complaint has a plausible, concrete mechanism: the Training Center/Trainer/Learners dropdowns are gated behind a strict 4-field AND-condition (Partner + Modality + Division + Trade must ALL be set) before an async lookup populates them — fragile rather than literally broken, but easy for a user to trigger by filling fields out of the expected order.

_Imported from `ALP-RTM_TASKS.md`, Module 15 — Program: Training/Course (incl. Attendance & Logbook). See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_63,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-049',
                        'title' => 'Investigate and improve the 4-field AND-gate controlling when Training Center/Trainer/Learner dropdowns populate in the Training/Course Add/Edit form — consider relaxing the gate, adding inline guidance ("select Partner, Modality, Division and…',
                        'description' => <<<'TASK_DESC_64'
**Original Task ID:** TASK-049 (`ALP-RTM_TASKS.md`, Module 15 — Program: Training/Course (incl. Attendance & Logbook))
**Source:** UAT · **Source Requirement ID(s):** UAT-017
**Original Status:** TODO · **Original Priority:** HIGH

Investigate and improve the 4-field AND-gate controlling when Training Center/Trainer/Learner dropdowns populate in the Training/Course Add/Edit form — consider relaxing the gate, adding inline guidance ("select Partner, Modality, Division and Trade to load options"), or a clearer disabled-state tooltip so users understand why fields appear "blocked"
TASK_DESC_64,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Research',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-050',
                        'title' => 'Confirm the specific data-entry sequence a QA reviewer used when the fields appeared permanently blocked, to fully close out UAT-017 (currently "Likely" not "Confirmed")',
                        'description' => <<<'TASK_DESC_65'
**Original Task ID:** TASK-050 (`ALP-RTM_TASKS.md`, Module 15 — Program: Training/Course (incl. Attendance & Logbook))
**Source:** UAT · **Source Requirement ID(s):** UAT-017
**Original Status:** REVIEW REQUIRED · **Original Priority:** MEDIUM

Confirm the specific data-entry sequence a QA reviewer used when the fields appeared permanently blocked, to fully close out UAT-017 (currently "Likely" not "Confirmed")
TASK_DESC_65,
                        'status' => 'in_review',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: UAT Feedback', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-051',
                        'title' => 'UAT-013: integrate Competency Standard into the Monitoring/Training framework — cross-ref Module 18',
                        'description' => <<<'TASK_DESC_66'
**Original Task ID:** TASK-051 (`ALP-RTM_TASKS.md`, Module 15 — Program: Training/Course (incl. Attendance & Logbook))
**Source:** UAT · **Source Requirement ID(s):** UAT-013
**Original Status:** TODO · **Original Priority:** MEDIUM

UAT-013: integrate Competency Standard into the Monitoring/Training framework — cross-ref Module 18
TASK_DESC_66,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-052',
                        'title' => 'Confirm Training/Course "mark as complete" workflow correctly gates Post Training creation in all cases (spot-checked, appears correct)',
                        'description' => <<<'TASK_DESC_67'
**Original Task ID:** TASK-052 (`ALP-RTM_TASKS.md`, Module 15 — Program: Training/Course (incl. Attendance & Logbook))
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-018
**Original Status:** REVIEW REQUIRED · **Original Priority:** LOW

Confirm Training/Course "mark as complete" workflow correctly gates Post Training creation in all cases (spot-checked, appears correct)
TASK_DESC_67,
                        'status' => 'in_review',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 16,
                'name' => '16 — Program: Post Training & Employment Tracking',
                'description' => <<<'MODULE_DESC_68'
**Requirement Sources:** COMMITMENT: COM-019 · UAT: UAT-019, UAT-021 · NEW: NEW-006, NEW-015

**Current Implementation:**
IMPLEMENTED for the base COM-019 commitment (activity date, certification +details, comment; genuinely gated on the parent training's "completed" status at the database level). Later Skilfo-era migrations added `job_placement_details` and `business_name` free-text fields, partially addressing NEW-015.

**Requirement Gap:**
`EmploymentStatus` enum is flat (Self/Wage/Unemployed) with no "Entrepreneur" parent category as UAT-019 requests. Job Linkage (NEW-006) lacks structured employer/enterprise/designation/date fields and any 6-month follow-up scheduling mechanism — only free text exists. No cross-cutting "Employment" tab (UAT-021) exists anywhere in the app.

_Imported from `ALP-RTM_TASKS.md`, Module 16 — Program: Post Training & Employment Tracking. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_68,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-053',
                        'title' => 'Restructure `EmploymentStatus` to introduce an "Entrepreneur" category with "Self"/"Wage" sub-options, per UAT-019 (currently Self and Wage are flat peers with "Entrepreneur" only baked into a label string)',
                        'description' => <<<'TASK_DESC_69'
**Original Task ID:** TASK-053 (`ALP-RTM_TASKS.md`, Module 16 — Program: Post Training & Employment Tracking)
**Source:** UAT · **Source Requirement ID(s):** UAT-019
**Original Status:** TODO · **Original Priority:** MEDIUM

Restructure `EmploymentStatus` to introduce an "Entrepreneur" category with "Self"/"Wage" sub-options, per UAT-019 (currently Self and Wage are flat peers with "Entrepreneur" only baked into a label string)
TASK_DESC_69,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-054',
                        'title' => 'Add structured employer/enterprise, job designation, and employment-date fields to Post Training / Learner Activity, replacing/supplementing the current free-text `job_placement_details`/`business_name`',
                        'description' => <<<'TASK_DESC_70'
**Original Task ID:** TASK-054 (`ALP-RTM_TASKS.md`, Module 16 — Program: Post Training & Employment Tracking)
**Source:** NEW · **Source Requirement ID(s):** NEW-006, NEW-015
**Original Status:** TODO · **Original Priority:** MEDIUM

Add structured employer/enterprise, job designation, and employment-date fields to Post Training / Learner Activity, replacing/supplementing the current free-text `job_placement_details`/`business_name`
TASK_DESC_70,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-055',
                        'title' => 'Build a 6-month follow-up mechanism for Job Linkage (e.g. a scheduled command that flags employed learners due for a follow-up check) — no such job currently exists',
                        'description' => <<<'TASK_DESC_71'
**Original Task ID:** TASK-055 (`ALP-RTM_TASKS.md`, Module 16 — Program: Post Training & Employment Tracking)
**Source:** NEW · **Source Requirement ID(s):** NEW-006
**Original Status:** TODO · **Original Priority:** MEDIUM

Build a 6-month follow-up mechanism for Job Linkage (e.g. a scheduled command that flags employed learners due for a follow-up check) — no such job currently exists
TASK_DESC_71,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-056',
                        'title' => 'Build a cross-cutting "Employment" view/tab spanning Learner/MCP/Trainer/Training/Monitoring, per UAT-021',
                        'description' => <<<'TASK_DESC_72'
**Original Task ID:** TASK-056 (`ALP-RTM_TASKS.md`, Module 16 — Program: Post Training & Employment Tracking)
**Source:** UAT · **Source Requirement ID(s):** UAT-021
**Original Status:** TODO · **Original Priority:** LOW

Build a cross-cutting "Employment" view/tab spanning Learner/MCP/Trainer/Training/Monitoring, per UAT-021
TASK_DESC_72,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Feature',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-057',
                        'title' => 'Confirm UAT-003 (Event "Target Participant" field) is actually rendering in the live Add/Edit Activity Vue form — the backend field (`target_participants`, distinct from `total_participants`) already exists end-to-end, so this is likely a…',
                        'description' => <<<'TASK_DESC_73'
**Original Task ID:** TASK-057 (`ALP-RTM_TASKS.md`, Module 16 — Program: Post Training & Employment Tracking)
**Source:** UAT · **Source Requirement ID(s):** UAT-003
**Original Status:** REVIEW REQUIRED · **Original Priority:** LOW

Confirm UAT-003 (Event "Target Participant" field) is actually rendering in the live Add/Edit Activity Vue form — the backend field (`target_participants`, distinct from `total_participants`) already exists end-to-end, so this is likely a UI-visibility issue rather than a missing field (cross-ref Module 17)
TASK_DESC_73,
                        'status' => 'in_review',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: UAT Feedback', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 17,
                'name' => '17 — Program: Event/Activity',
                'description' => <<<'MODULE_DESC_74'
**Requirement Sources:** COMMITMENT: COM-020 · UAT: UAT-003

**Current Implementation:**
IMPLEMENTED. `total_participants` and `target_participants` are confirmed as two genuinely separate, validated columns end-to-end (model, migration, both Dashboard and API V1/V2 Store/Update requests).

**Requirement Gap:**
UAT-003 claimed the "Target Participant" field was missing — code evidence contradicts this at the backend level. Needs a UI screenshot / live check to see whether the field is actually rendered and labeled clearly in the Vue form (see TASK-057 above; not duplicated here).

_Imported from `ALP-RTM_TASKS.md`, Module 17 — Program: Event/Activity. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_74,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-058',
                        'title' => 'Verify in a running environment whether "Target Participant" is visibly rendered/labeled in the Add/Edit Activity form; if present but mislabeled/hidden, fix the Vue template rather than the backend',
                        'description' => <<<'TASK_DESC_75'
**Original Task ID:** TASK-058 (`ALP-RTM_TASKS.md`, Module 17 — Program: Event/Activity)
**Source:** UAT · **Source Requirement ID(s):** UAT-003
**Original Status:** REVIEW REQUIRED · **Original Priority:** LOW

Verify in a running environment whether "Target Participant" is visibly rendered/labeled in the Add/Edit Activity form; if present but mislabeled/hidden, fix the Vue template rather than the backend
TASK_DESC_75,
                        'status' => 'in_review',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: UAT Feedback', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-059',
                        'title' => 'UAT-011: wire an automatic email notification specifically to Event/Activity creation (currently no `Event` model/notification exists; closest analog, Training assignment, is email-capable but disabled by default via `NOTIFY_VIA_MAIL` env flag)',
                        'description' => <<<'TASK_DESC_76'
**Original Task ID:** TASK-059 (`ALP-RTM_TASKS.md`, Module 17 — Program: Event/Activity)
**Source:** UAT · **Source Requirement ID(s):** UAT-011
**Original Status:** TODO · **Original Priority:** MEDIUM

UAT-011: wire an automatic email notification specifically to Event/Activity creation (currently no `Event` model/notification exists; closest analog, Training assignment, is email-capable but disabled by default via `NOTIFY_VIA_MAIL` env flag)
TASK_DESC_76,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 18,
                'name' => '18 — Monitoring (ALP)',
                'description' => <<<'MODULE_DESC_77'
**Requirement Sources:** COMMITMENT: COM-021 · UAT: UAT-013, UAT-022

**Current Implementation:**
IMPLEMENTED BUT NEEDS REVIEW. A genuine multi-step PrimeVue Stepper (4 steps for ALP: Background/Learning/Interactions/Safeguarding) covers the SRS's 3-step content (Initial Visit, Learning Environment Assessment, Tracking & Follow-up) with matching fields.

**Requirement Gap:**
Monitoring **creation** only happens via the mobile API (not in this repo) — the web Vue Stepper is edit-only, per the dashboard route registration. No integration with Competency Standard anywhere in the monitoring stack (UAT-013, confirmed by exhaustive grep). A concrete, plausible bug was found for UAT-022: `MonitoringController::exportPdf` accesses `$answer->formPivot->input->type` without a null-safe operator, unlike the sibling `show()` method which does use one — any orphaned/legacy `MonitoringFormAnswer` row would throw an uncaught error here.

_Imported from `ALP-RTM_TASKS.md`, Module 18 — Monitoring (ALP). See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_77,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-060',
                        'title' => 'Fix the unguarded `$answer->formPivot->input->type` access in `MonitoringController::exportPdf` (add null-safe chaining, matching the pattern already used in `show()`) — leading candidate root cause for UAT-022',
                        'description' => <<<'TASK_DESC_78'
**Original Task ID:** TASK-060 (`ALP-RTM_TASKS.md`, Module 18 — Monitoring (ALP))
**Source:** UAT · **Source Requirement ID(s):** UAT-022
**Original Status:** TODO · **Original Priority:** HIGH

Fix the unguarded `$answer->formPivot->input->type` access in `MonitoringController::exportPdf` (add null-safe chaining, matching the pattern already used in `show()`) — leading candidate root cause for UAT-022

#### Fix null-unsafe property access in Monitoring PDF export

- **Type:** [BUG]
- **Source:** UAT · **Source ID:** UAT-022 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** The Monitoring Dashboard/PDF export should not throw an uncaught error.
- **Current State:** `app/Http/Controllers/Dashboard/MonitoringController.php` — `exportPdf` (~line 113-116) accesses `$answer->formPivot->input->type` directly; the sibling `show()` method (~line 85) defensively uses `?->formPivot?->form_id`.
- **Gap:** Any `MonitoringFormAnswer` row with a null `formPivot` or `formPivot->input` (e.g. from a deleted form-builder input, or legacy data predating a form change) will throw "Attempt to read property on null," matching the UAT reviewer's report of the Monitoring Dashboard "displaying an error."
- **Required Change:** Add null-safe operators (`?->`) matching the `show()` method's defensive pattern, and decide on graceful fallback behavior (skip the row / default type) when data is missing.
- **Dependencies:** None.
- **Acceptance Criteria:** Exporting a Monitoring PDF/report no longer throws when encountering orphaned form-answer data; reproduce the original error scenario if possible to confirm the fix.
TASK_DESC_78,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Bug',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-061',
                        'title' => 'Reproduce UAT-022 in a running environment to confirm TASK-060 is the actual root cause (currently "Likely", not "Confirmed")',
                        'description' => <<<'TASK_DESC_79'
**Original Task ID:** TASK-061 (`ALP-RTM_TASKS.md`, Module 18 — Monitoring (ALP))
**Source:** UAT · **Source Requirement ID(s):** UAT-022
**Original Status:** REVIEW REQUIRED · **Original Priority:** HIGH

Reproduce UAT-022 in a running environment to confirm TASK-060 is the actual root cause (currently "Likely", not "Confirmed")
TASK_DESC_79,
                        'status' => 'in_review',
                        'priority' => 'high',
                        'task_type' => 'Research',
                        'labels' => ['Source: UAT Feedback', 'Needs Review'],
                        'crossrefs' => ['TASK-060'],
                    ],
                    [
                        'orig_task_id' => 'TASK-062',
                        'title' => 'Confirm whether the web dashboard is intentionally edit-only for Monitoring (with creation restricted to the mobile app) — if not intentional, add a web creation flow',
                        'description' => <<<'TASK_DESC_80'
**Original Task ID:** TASK-062 (`ALP-RTM_TASKS.md`, Module 18 — Monitoring (ALP))
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-021
**Original Status:** REVIEW REQUIRED · **Original Priority:** MEDIUM

Confirm whether the web dashboard is intentionally edit-only for Monitoring (with creation restricted to the mobile app) — if not intentional, add a web creation flow
TASK_DESC_80,
                        'status' => 'in_review',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-063',
                        'title' => 'Integrate Competency Standard into the Monitoring framework, per UAT-013 (currently zero references to CompetencyTask/CompetencySkill anywhere in the monitoring model/service/form)',
                        'description' => <<<'TASK_DESC_81'
**Original Task ID:** TASK-063 (`ALP-RTM_TASKS.md`, Module 18 — Monitoring (ALP))
**Source:** UAT · **Source Requirement ID(s):** UAT-013
**Original Status:** TODO · **Original Priority:** MEDIUM

Integrate Competency Standard into the Monitoring framework, per UAT-013 (currently zero references to CompetencyTask/CompetencySkill anywhere in the monitoring model/service/form)
TASK_DESC_81,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 19,
                'name' => '19 — Monitoring (Skilfo / Cluster Monitoring / Field Monitoring Assistant)',
                'description' => <<<'MODULE_DESC_82'
**Requirement Sources:** NEW: NEW-010

**Current Implementation:**
IMPLEMENTED. Confirmed as a genuinely distinct workflow (`SkilfoMonitoring`/`SkilfoMonitoringDetail` models, separate controller, per-trade/trainer/learner detail structure) rather than a thin duplicate of ALP Monitoring — directly satisfies NEW-010.

_Imported from `ALP-RTM_TASKS.md`, Module 19 — Monitoring (Skilfo / Cluster Monitoring / Field Monitoring Assistant). See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_82,
                'status' => 'completed',
                'priority' => 'low',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-064',
                        'title' => 'No action needed — mark NEW-010 as verified complete; consider documenting the ALP-vs-Skilfo Monitoring distinction for future maintainers',
                        'description' => <<<'TASK_DESC_83'
**Original Task ID:** TASK-064 (`ALP-RTM_TASKS.md`, Module 19 — Monitoring (Skilfo / Cluster Monitoring / Field Monitoring Assistant))
**Source:** NEW · **Source Requirement ID(s):** NEW-010
**Original Status:** NOT APPLICABLE · **Original Priority:** LOW

No action needed — mark NEW-010 as verified complete; consider documenting the ALP-vs-Skilfo Monitoring distinction for future maintainers
TASK_DESC_83,
                        'status' => 'cancelled',
                        'priority' => 'low',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 20,
                'name' => '20 — Feedback',
                'description' => <<<'MODULE_DESC_84'
**Requirement Sources:** COMMITMENT: COM-022 · REVIEW: REV-006

**Current Implementation:**
IMPLEMENTED. Full field coverage, polymorphic linkage confirmed working from Implementing Partner, Trainer, and Training pages, matching COM-022.

_Imported from `ALP-RTM_TASKS.md`, Module 20 — Feedback. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_84,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-065',
                        'title' => 'Cross-ref TASK-007 (verify/add sanitization on Feedback description before `v-html` rendering)',
                        'description' => <<<'TASK_DESC_85'
**Original Task ID:** TASK-065 (`ALP-RTM_TASKS.md`, Module 20 — Feedback)
**Source:** REVIEW · **Source Requirement ID(s):** REV-006
**Original Status:** TODO · **Original Priority:** MEDIUM

Cross-ref TASK-007 (verify/add sanitization on Feedback description before `v-html` rendering)
TASK_DESC_85,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => ['TASK-007'],
                    ],
                    [
                        'orig_task_id' => 'TASK-066',
                        'title' => 'Confirm Feedback is also linkable from Stakeholder/MCP/Learner pages if that\'s an intended parity item (only Partner/Trainer/Training were confirmed)',
                        'description' => <<<'TASK_DESC_86'
**Original Task ID:** TASK-066 (`ALP-RTM_TASKS.md`, Module 20 — Feedback)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-022
**Original Status:** REVIEW REQUIRED · **Original Priority:** LOW

Confirm Feedback is also linkable from Stakeholder/MCP/Learner pages if that's an intended parity item (only Partner/Trainer/Training were confirmed)
TASK_DESC_86,
                        'status' => 'in_review',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 21,
                'name' => '21 — Assessment & Certification (Skilfo)',
                'description' => <<<'MODULE_DESC_87'
**Requirement Sources:** NEW: NEW-003, NEW-004, NEW-005

**Current Implementation:**
IMPLEMENTED for NEW-003 (assessor mass-assignment of training-completed learners, assessor-side assignment list, occupation-based competency gating, RPL certificate PDF generation via `barryvdh/laravel-dompdf`/`carlos-meneses/laravel-mpdf`) — genuinely more built-out than the Skilfo comparison document assumed. NEW-004's certificate-generation piece exists; NEW-005 does not.

**Requirement Gap:**
No "Assessment Scheduling and assessor allocation matrix," no "Self-Assessment Checklists," no "Competency Assessment Result Sheets" model/controller anywhere — these three NEW-004 sub-items are genuinely missing, not just under-verified. NEW-005's structured Pre/Post-Course assessment SCORING (distinct from Monitoring) is entirely absent.

_Imported from `ALP-RTM_TASKS.md`, Module 21 — Assessment & Certification (Skilfo). See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_87,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-067',
                        'title' => 'Design and build "Assessment Scheduling & Assessor Allocation Matrix" per NEW-004',
                        'description' => <<<'TASK_DESC_88'
**Original Task ID:** TASK-067 (`ALP-RTM_TASKS.md`, Module 21 — Assessment & Certification (Skilfo))
**Source:** NEW · **Source Requirement ID(s):** NEW-004
**Original Status:** TODO · **Original Priority:** MEDIUM

Design and build "Assessment Scheduling & Assessor Allocation Matrix" per NEW-004
TASK_DESC_88,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-068',
                        'title' => 'Design and build "Self-Assessment Checklists" (learner self-evaluation) per NEW-004',
                        'description' => <<<'TASK_DESC_89'
**Original Task ID:** TASK-068 (`ALP-RTM_TASKS.md`, Module 21 — Assessment & Certification (Skilfo))
**Source:** NEW · **Source Requirement ID(s):** NEW-004
**Original Status:** TODO · **Original Priority:** MEDIUM

Design and build "Self-Assessment Checklists" (learner self-evaluation) per NEW-004
TASK_DESC_89,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-069',
                        'title' => 'Design and build "Competency Assessment Result Sheets" per NEW-004',
                        'description' => <<<'TASK_DESC_90'
**Original Task ID:** TASK-069 (`ALP-RTM_TASKS.md`, Module 21 — Assessment & Certification (Skilfo))
**Source:** NEW · **Source Requirement ID(s):** NEW-004
**Original Status:** TODO · **Original Priority:** MEDIUM

Design and build "Competency Assessment Result Sheets" per NEW-004
TASK_DESC_90,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-070',
                        'title' => 'Design and build structured Pre-/Post-Course Assessment SCORING (literacy & occupational competency), distinct from the existing Monitoring module, per NEW-005',
                        'description' => <<<'TASK_DESC_91'
**Original Task ID:** TASK-070 (`ALP-RTM_TASKS.md`, Module 21 — Assessment & Certification (Skilfo))
**Source:** NEW · **Source Requirement ID(s):** NEW-005
**Original Status:** TODO · **Original Priority:** HIGH

Design and build structured Pre-/Post-Course Assessment SCORING (literacy & occupational competency), distinct from the existing Monitoring module, per NEW-005
TASK_DESC_91,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 22,
                'name' => '22 — Job Linkage & Post-Training Progression',
                'description' => <<<'MODULE_DESC_92'
**Requirement Sources:** NEW: NEW-006

_Imported from `ALP-RTM_TASKS.md`, Module 22 — Job Linkage & Post-Training Progression. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_92,
                'status' => 'on_hold',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                ],
            ],
            [
                'module_num' => 23,
                'name' => '23 — Attribute Setup',
                'description' => <<<'MODULE_DESC_93'
**Requirement Sources:** COMMITMENT: COM-023 · NEW: NEW-012 · UAT: UAT-015

**Current Implementation:**
IMPLEMENTED. All nine committed attribute types (Modality/Intervention, Disability, Ethnicity, Qualification, Occupation/Trade, Workplace Size, Activity Purpose, Competency Skill, Competency Task) have full bilingual CRUD, permission-gated, with none found to be English-only.

**Requirement Gap:**
UAT-015's "Auto Mechanics" trade is genuinely missing from seed data (a real gap); "Shantiganj" upazila and "Electrical Installation and Maintenance" trade already exist in seed data — the client's complaint is most likely explained by a stale/partial deployment rather than a code defect.

_Imported from `ALP-RTM_TASKS.md`, Module 23 — Attribute Setup. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_93,
                'status' => 'in_progress',
                'priority' => 'low',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-074',
                        'title' => 'Add missing "Auto Mechanics" trade/occupation to seed data',
                        'description' => <<<'TASK_DESC_94'
**Original Task ID:** TASK-074 (`ALP-RTM_TASKS.md`, Module 23 — Attribute Setup)
**Source:** UAT · **Source Requirement ID(s):** UAT-015
**Original Status:** TODO · **Original Priority:** LOW

Add missing "Auto Mechanics" trade/occupation to seed data
TASK_DESC_94,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-075',
                        'title' => 'Verify the production database has the current `UpazilasTableSeeder`/`TradeSeeder` fully applied (the client\'s environment may be missing seed updates present in the repo)',
                        'description' => <<<'TASK_DESC_95'
**Original Task ID:** TASK-075 (`ALP-RTM_TASKS.md`, Module 23 — Attribute Setup)
**Source:** UAT · **Source Requirement ID(s):** UAT-015
**Original Status:** REVIEW REQUIRED · **Original Priority:** LOW

Verify the production database has the current `UpazilasTableSeeder`/`TradeSeeder` fully applied (the client's environment may be missing seed updates present in the repo)
TASK_DESC_95,
                        'status' => 'in_review',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: UAT Feedback', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-076',
                        'title' => 'Resolve the duplicate "Shantiganj" upazila seed entry (appears twice, under two different districts — id 556 under Habiganj and id 559 under Sunamganj) to prevent ambiguous location selection',
                        'description' => <<<'TASK_DESC_96'
**Original Task ID:** TASK-076 (`ALP-RTM_TASKS.md`, Module 23 — Attribute Setup)
**Source:** REVIEW · **Source Requirement ID(s):** —
**Original Status:** TODO · **Original Priority:** LOW

Resolve the duplicate "Shantiganj" upazila seed entry (appears twice, under two different districts — id 556 under Habiganj and id 559 under Sunamganj) to prevent ambiguous location selection
TASK_DESC_96,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 24,
                'name' => '24 — Settings',
                'description' => <<<'MODULE_DESC_97'
**Requirement Sources:** COMMITMENT: COM-024

**Current Implementation:**
IMPLEMENTED, and exceeds spec in places (e.g. Mobile App settings include per-tenant monitoring form version counters beyond what SRS described). All committed sub-sections (CMS, Application, Contact, Media, Mobile App, Social Links) are present for both ALP and Skilfo tenants via `spatie/laravel-settings`.

_Imported from `ALP-RTM_TASKS.md`, Module 24 — Settings. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_97,
                'status' => 'completed',
                'priority' => 'low',
                'scope' => 'Admin Panel',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-077',
                        'title' => 'No action needed — mark COM-024 as verified complete',
                        'description' => <<<'TASK_DESC_98'
**Original Task ID:** TASK-077 (`ALP-RTM_TASKS.md`, Module 24 — Settings)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-024
**Original Status:** NOT APPLICABLE · **Original Priority:** LOW

No action needed — mark COM-024 as verified complete
TASK_DESC_98,
                        'status' => 'cancelled',
                        'priority' => 'low',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: SRS Commitment'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 25,
                'name' => '25 — Locations',
                'description' => <<<'MODULE_DESC_99'
**Requirement Sources:** UAT: UAT-015

**Current Implementation:**
IMPLEMENTED. Full Division/District/Upazila/Union CRUD with cascading endpoints.

_Imported from `ALP-RTM_TASKS.md`, Module 25 — Locations. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_99,
                'status' => 'in_progress',
                'priority' => 'low',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-078',
                        'title' => 'Cross-ref TASK-075/076 (Shantiganj data issue)',
                        'description' => <<<'TASK_DESC_100'
**Original Task ID:** TASK-078 (`ALP-RTM_TASKS.md`, Module 25 — Locations)
**Source:** UAT · **Source Requirement ID(s):** UAT-015
**Original Status:** TODO · **Original Priority:** LOW

Cross-ref TASK-075/076 (Shantiganj data issue)
TASK_DESC_100,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => ['TASK-075'],
                    ],
                    [
                        'orig_task_id' => 'TASK-079',
                        'title' => 'No further action needed beyond the seed-data fixes already tracked',
                        'description' => <<<'TASK_DESC_101'
**Original Task ID:** TASK-079 (`ALP-RTM_TASKS.md`, Module 25 — Locations)
**Source:** UAT · **Source Requirement ID(s):** UAT-015
**Original Status:** NOT APPLICABLE · **Original Priority:** LOW

No further action needed beyond the seed-data fixes already tracked
TASK_DESC_101,
                        'status' => 'cancelled',
                        'priority' => 'low',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 26,
                'name' => '26 — Dashboard & Analytics',
                'description' => <<<'MODULE_DESC_102'
**Requirement Sources:** COMMITMENT: COM-006 · UAT: UAT-001, UAT-002, UAT-004

**Current Implementation:**
IMPLEMENTED, with UAT-relevant gaps. All 5 tabs present with matching filters and the great majority of committed charts/stat cards, including a genuine monthly time-series for the Monitoring tab (this had been a suspected gap going in — confirmed NOT missing).

**Requirement Gap:**
UAT-001 (cards need graphical representation): confirmed not implemented — `StatCard.vue` shows only an icon/number/percentage, no embedded chart. UAT-002 (clickable cards): only the single "primary" card per tab is clickable; the majority of cards across all 5 tabs have no navigation at all. UAT-004 (icon consistency): largely consistent already, minor outliers only.

_Imported from `ALP-RTM_TASKS.md`, Module 26 — Dashboard & Analytics. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_102,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Reporting',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-080',
                        'title' => 'Add a small embedded chart/sparkline to `StatCard.vue` per UAT-001',
                        'description' => <<<'TASK_DESC_103'
**Original Task ID:** TASK-080 (`ALP-RTM_TASKS.md`, Module 26 — Dashboard & Analytics)
**Source:** UAT · **Source Requirement ID(s):** UAT-001
**Original Status:** TODO · **Original Priority:** MEDIUM

Add a small embedded chart/sparkline to `StatCard.vue` per UAT-001
TASK_DESC_103,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-081',
                        'title' => 'Make all dashboard stat cards clickable/linked to their corresponding list pages, not just the single primary card per tab, per UAT-002',
                        'description' => <<<'TASK_DESC_104'
**Original Task ID:** TASK-081 (`ALP-RTM_TASKS.md`, Module 26 — Dashboard & Analytics)
**Source:** UAT · **Source Requirement ID(s):** UAT-002
**Original Status:** TODO · **Original Priority:** MEDIUM

Make all dashboard stat cards clickable/linked to their corresponding list pages, not just the single primary card per tab, per UAT-002
TASK_DESC_104,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-082',
                        'title' => 'Standardize the handful of outlier icons (`pi pi-filter`, `mage:`, `ion:`) to the dominant `mdi:`/Iconify set used elsewhere, per UAT-004',
                        'description' => <<<'TASK_DESC_105'
**Original Task ID:** TASK-082 (`ALP-RTM_TASKS.md`, Module 26 — Dashboard & Analytics)
**Source:** UAT · **Source Requirement ID(s):** UAT-004
**Original Status:** TODO · **Original Priority:** LOW

Standardize the handful of outlier icons (`pi pi-filter`, `mage:`, `ion:`) to the dominant `mdi:`/Iconify set used elsewhere, per UAT-004
TASK_DESC_105,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-083',
                        'title' => 'Confirm the "animated overview diagram" section on the landing page (COM-001) exists as a distinct component, or clarify it was satisfied by the confirmed hover-card + AOS-animation implementation',
                        'description' => <<<'TASK_DESC_106'
**Original Task ID:** TASK-083 (`ALP-RTM_TASKS.md`, Module 26 — Dashboard & Analytics)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-001
**Original Status:** REVIEW REQUIRED · **Original Priority:** LOW

Confirm the "animated overview diagram" section on the landing page (COM-001) exists as a distinct component, or clarify it was satisfied by the confirmed hover-card + AOS-animation implementation
TASK_DESC_106,
                        'status' => 'in_review',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-084',
                        'title' => 'Add `Cache::` usage to `DashboardController`/`StatisticsController`/`GraphController` for expensive aggregate/chart queries — currently only one report metric is cached repo-wide',
                        'description' => <<<'TASK_DESC_107'
**Original Task ID:** TASK-084 (`ALP-RTM_TASKS.md`, Module 26 — Dashboard & Analytics)
**Source:** REVIEW · **Source Requirement ID(s):** REV-017
**Original Status:** TODO · **Original Priority:** MEDIUM

Add `Cache::` usage to `DashboardController`/`StatisticsController`/`GraphController` for expensive aggregate/chart queries — currently only one report metric is cached repo-wide
TASK_DESC_107,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 27,
                'name' => '27 — Reports',
                'description' => <<<'MODULE_DESC_108'
**Requirement Sources:** COMMITMENT: COM-025 · UAT: UAT-005 · NEW: NEW-011, NEW-018

**Current Implementation:**
IMPLEMENTED. All three committed report types (Outcomes and Output, Activity, Stakeholder/Partnership) exist with PDF (mPDF) and Excel export.

**Requirement Gap:**
Activity Report has a Female sub-column per metric but **no independent Male column** (UAT-005) — Male must be manually derived by subtraction. `donor_id`/`upazila_id` filters in `ReportService::activityReport()` are commented-out dead code — filtering by donor or upazila silently does nothing even if the UI offers it. Monthly Progress Reports (NEW-011) don't exist at all. None of the three Reports-module exports support a Bengali/English toggle (NEW-018), even though that exact pattern already exists elsewhere in the codebase (MCP/Assessor exports).

_Imported from `ALP-RTM_TASKS.md`, Module 27 — Reports. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_108,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Reporting',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-085',
                        'title' => 'Add an independent "Male" column (not just derived) alongside the existing Female sub-columns in the Activity Report, per UAT-005',
                        'description' => <<<'TASK_DESC_109'
**Original Task ID:** TASK-085 (`ALP-RTM_TASKS.md`, Module 27 — Reports)
**Source:** UAT · **Source Requirement ID(s):** UAT-005
**Original Status:** TODO · **Original Priority:** MEDIUM

Add an independent "Male" column (not just derived) alongside the existing Female sub-columns in the Activity Report, per UAT-005
TASK_DESC_109,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-086',
                        'title' => 'Re-enable/fix the commented-out `donor_id`/`upazila_id` filters in `ReportService::activityReport()` — currently non-functional dead code despite (presumably) being offered in the UI',
                        'description' => <<<'TASK_DESC_110'
**Original Task ID:** TASK-086 (`ALP-RTM_TASKS.md`, Module 27 — Reports)
**Source:** REVIEW · **Source Requirement ID(s):** REV-015
**Original Status:** TODO · **Original Priority:** HIGH

Re-enable/fix the commented-out `donor_id`/`upazila_id` filters in `ReportService::activityReport()` — currently non-functional dead code despite (presumably) being offered in the UI

#### Re-enable dead filters in Activity Report

- **Type:** [BUG]
- **Source:** REVIEW · **Source ID:** REV-015 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** COM-025 commits to filtering reports by location/donor/trade.
- **Current State:** `app/Services/Reports/ReportService.php:232-234,241-243` has `donor_id` and `upazila_id` filter logic commented out inside `activityReport()`.
- **Gap:** If the Activity Report UI still presents donor/upazila filter controls, selecting them currently has no effect on the results — a confirmed functional defect, not a design choice (the commit history/comments suggest it was disabled, not intentionally removed).
- **Required Change:** Restore the filter logic (verify it's compatible with the current query structure first, in case it was disabled due to a bug rather than by oversight) or remove the corresponding UI controls if intentionally descoped — confirm with the team which is correct before re-enabling blindly.
- **Dependencies:** None.
- **Acceptance Criteria:** Selecting a Donor or Upazila filter on the Activity Report actually narrows the results.
TASK_DESC_110,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-087',
                        'title' => 'Get client clarification on the structure/purpose of "Monthly Progress Reports (CA-1 to CA-4)" before building — the client itself does not have a clear definition (see Open Questions OQ-001)',
                        'description' => <<<'TASK_DESC_111'
**Original Task ID:** TASK-087 (`ALP-RTM_TASKS.md`, Module 27 — Reports)
**Source:** NEW · **Source Requirement ID(s):** NEW-011
**Original Status:** BLOCKED · **Original Priority:** MEDIUM

Get client clarification on the structure/purpose of "Monthly Progress Reports (CA-1 to CA-4)" before building — the client itself does not have a clear definition (see Open Questions OQ-001)

**Related Open Question(s):**
- OQ-001: What exactly are "CA-1" through "CA-4" in the Monthly Progress Report, and how often are they filled? Are they assessment-, attendance-, or performance-related?
TASK_DESC_111,
                        'status' => 'blocked',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Skilfo New Requirement', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-088',
                        'title' => 'Apply the existing bilingual-export pattern (already used in `MCPExport`/`AssessorExport`, with a `$language` constructor param and `trans(\'labels\', ..., \'bn\')`) to `ActivityExport`, `OutcomeAndOutputExport`, and `PartnershipExport`, per NEW-018',
                        'description' => <<<'TASK_DESC_112'
**Original Task ID:** TASK-088 (`ALP-RTM_TASKS.md`, Module 27 — Reports)
**Source:** NEW · **Source Requirement ID(s):** NEW-018
**Original Status:** TODO · **Original Priority:** MEDIUM

Apply the existing bilingual-export pattern (already used in `MCPExport`/`AssessorExport`, with a `$language` constructor param and `trans('labels', ..., 'bn')`) to `ActivityExport`, `OutcomeAndOutputExport`, and `PartnershipExport`, per NEW-018
TASK_DESC_112,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-089',
                        'title' => 'Apply the same bilingual pattern to the equivalent PDF Blade views used by Reports',
                        'description' => <<<'TASK_DESC_113'
**Original Task ID:** TASK-089 (`ALP-RTM_TASKS.md`, Module 27 — Reports)
**Source:** NEW · **Source Requirement ID(s):** NEW-018
**Original Status:** TODO · **Original Priority:** MEDIUM

Apply the same bilingual pattern to the equivalent PDF Blade views used by Reports
TASK_DESC_113,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 28,
                'name' => '28 — Public Frontend, Landing Page & CMS',
                'description' => <<<'MODULE_DESC_114'
**Requirement Sources:** COMMITMENT: COM-001, COM-002, COM-003, COM-004, COM-005 · NEW: NEW-016

**Current Implementation:**
IMPLEMENTED for COM-001/002/003 (banner hover-cards, real Google-Maps-based geolocation feature with clickable per-location counts, landing charts, partner slider — all confirmed working with real implementations, not stubs). COM-005 (contact form) is implemented via a settings-driven notification path, though the direct `Mail::send()` call is commented out in favor of that path — worth confirming it actually delivers mail. NEW-016's subdomain routing and BNFE "About Us" content are real.

**Requirement Gap:**
COM-004's CMS "preview feature before publishing" does not exist at all — no draft/published state column exists on the `cms` table, so content goes live immediately on save; the only "preview" hit in the codebase is an unrelated image-thumbnail widget. NEW-016's post-login role/tenant-based redirect does not exist — all users land on the same fixed `/dashboard` route regardless of tenant, with role-based branching happening only in in-page content, not the redirect itself.

_Imported from `ALP-RTM_TASKS.md`, Module 28 — Public Frontend, Landing Page & CMS. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_114,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-090',
                        'title' => 'Add a draft/published state to the `cms` table and a preview route/view, per COM-004\'s explicit "preview feature" commitment',
                        'description' => <<<'TASK_DESC_115'
**Original Task ID:** TASK-090 (`ALP-RTM_TASKS.md`, Module 28 — Public Frontend, Landing Page & CMS)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-004
**Original Status:** TODO · **Original Priority:** MEDIUM

Add a draft/published state to the `cms` table and a preview route/view, per COM-004's explicit "preview feature" commitment
TASK_DESC_115,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: SRS Commitment'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-091',
                        'title' => 'Confirm `AlpContactSettings::notifyReceiver()` / `SkilfoContactSettings::notifyReceiver()` actually sends email (not a silent no-op) now that the direct `Mail::send()` call is commented out in both `FrontendController` and `TenantFrontendController`',
                        'description' => <<<'TASK_DESC_116'
**Original Task ID:** TASK-091 (`ALP-RTM_TASKS.md`, Module 28 — Public Frontend, Landing Page & CMS)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-005
**Original Status:** REVIEW REQUIRED · **Original Priority:** MEDIUM

Confirm `AlpContactSettings::notifyReceiver()` / `SkilfoContactSettings::notifyReceiver()` actually sends email (not a silent no-op) now that the direct `Mail::send()` call is commented out in both `FrontendController` and `TenantFrontendController`
TASK_DESC_116,
                        'status' => 'in_review',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-092',
                        'title' => 'Implement tenant/role-based post-login redirect logic, per NEW-016 (currently all users redirect to the same fixed `/dashboard`, with role branching only in page content)',
                        'description' => <<<'TASK_DESC_117'
**Original Task ID:** TASK-092 (`ALP-RTM_TASKS.md`, Module 28 — Public Frontend, Landing Page & CMS)
**Source:** NEW · **Source Requirement ID(s):** NEW-016
**Original Status:** TODO · **Original Priority:** MEDIUM

Implement tenant/role-based post-login redirect logic, per NEW-016 (currently all users redirect to the same fixed `/dashboard`, with role branching only in page content)
TASK_DESC_117,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Skilfo New Requirement'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-093',
                        'title' => 'Add an in-app locale-switch control on the authenticated dashboard (currently bilingual switching exists only via the public-site `?locale=` route/session, with no visible in-dashboard toggle)',
                        'description' => <<<'TASK_DESC_118'
**Original Task ID:** TASK-093 (`ALP-RTM_TASKS.md`, Module 28 — Public Frontend, Landing Page & CMS)
**Source:** REVIEW · **Source Requirement ID(s):** —
**Original Status:** TODO · **Original Priority:** LOW

Add an in-app locale-switch control on the authenticated dashboard (currently bilingual switching exists only via the public-site `?locale=` route/session, with no visible in-dashboard toggle)
TASK_DESC_118,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 29,
                'name' => '29 — Notifications',
                'description' => <<<'MODULE_DESC_119'
**Requirement Sources:** UAT: UAT-011

**Current Implementation:**
PARTIALLY IMPLEMENTED. Real email-capable notification infrastructure exists but is narrowly applied (Trainer assignment, task assignment, import complete/fail, contact form, registration-no update) and is gated behind an env flag (`NOTIFY_VIA_MAIL`) that defaults OFF for at least two of those notification types. In-app notification models (`Notification`/`NotificationUser`) are database/push-only, not email.

**Requirement Gap:**
No generic "notify on any dashboard action" infrastructure exists; broader modules (Monitoring, CMS, Reports) fire no notifications at all. No literal "Event" model/notification exists (Event/Activity creation doesn't trigger anything directly; the closest analog, Training/course trainer-assignment, is email-capable but off by default).

_Imported from `ALP-RTM_TASKS.md`, Module 29 — Notifications. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_119,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-094',
                        'title' => 'Confirm with UNICEF/client whether `NOTIFY_VIA_MAIL`/`NOTIFY_VIA_SMS` are enabled in production — if the intent is for these notifications to actually reach users by email/SMS, the current default-off configuration silently defeats that intent',
                        'description' => <<<'TASK_DESC_120'
**Original Task ID:** TASK-094 (`ALP-RTM_TASKS.md`, Module 29 — Notifications)
**Source:** UAT · **Source Requirement ID(s):** UAT-011
**Original Status:** REVIEW REQUIRED · **Original Priority:** HIGH

Confirm with UNICEF/client whether `NOTIFY_VIA_MAIL`/`NOTIFY_VIA_SMS` are enabled in production — if the intent is for these notifications to actually reach users by email/SMS, the current default-off configuration silently defeats that intent
TASK_DESC_120,
                        'status' => 'in_review',
                        'priority' => 'high',
                        'task_type' => 'Research',
                        'labels' => ['Source: UAT Feedback', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-095',
                        'title' => 'Wire an email notification specifically to Event/Activity creation, per UAT-011\'s explicit callout ("specially event creation")',
                        'description' => <<<'TASK_DESC_121'
**Original Task ID:** TASK-095 (`ALP-RTM_TASKS.md`, Module 29 — Notifications)
**Source:** UAT · **Source Requirement ID(s):** UAT-011
**Original Status:** TODO · **Original Priority:** MEDIUM

Wire an email notification specifically to Event/Activity creation, per UAT-011's explicit callout ("specially event creation")
TASK_DESC_121,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Feature',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-096',
                        'title' => 'Scope which additional modules (Monitoring, CMS changes, Feedback resolution) should trigger notifications, rather than building a fully generic "any action" system (avoid overengineering — pick the specific, valuable trigger points the client…',
                        'description' => <<<'TASK_DESC_122'
**Original Task ID:** TASK-096 (`ALP-RTM_TASKS.md`, Module 29 — Notifications)
**Source:** UAT · **Source Requirement ID(s):** UAT-011
**Original Status:** TODO · **Original Priority:** LOW

Scope which additional modules (Monitoring, CMS changes, Feedback resolution) should trigger notifications, rather than building a fully generic "any action" system (avoid overengineering — pick the specific, valuable trigger points the client actually needs)
TASK_DESC_122,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: UAT Feedback'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 30,
                'name' => '30 — BNFE Integration (Sync & Webhooks)',
                'description' => <<<'MODULE_DESC_123'
**Requirement Sources:** REVIEW: REV-001, REV-014

**Current Implementation:**
IMPLEMENTED, functional but rough-edged. Real bidirectional sync: ALP→BNFE outbound (learner/training-center/occupation/program sync via queued jobs) and BNFE→ALP inbound webhook (`certificate-issued`, authenticated via per-partner API keys). This is a working integration, not a stub, and is documented in `docs/BNFE_WEBHOOK_API.md`.

**Requirement Gap:**
The inbound webhook's entire try/catch error-handling block is commented out — any runtime error will surface as an unhandled 500 instead of the documented graceful JSON error response. BNFE dashboard routes have no permission gating (tracked as TASK-001).

_Imported from `ALP-RTM_TASKS.md`, Module 30 — BNFE Integration (Sync & Webhooks). See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_123,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'API Integration',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-097',
                        'title' => 'Restore/fix the commented-out try/catch error handling in `BNFEWebhookController::certificateIssued`, so it matches its own documented error-response contract',
                        'description' => <<<'TASK_DESC_124'
**Original Task ID:** TASK-097 (`ALP-RTM_TASKS.md`, Module 30 — BNFE Integration (Sync & Webhooks))
**Source:** REVIEW · **Source Requirement ID(s):** REV-014
**Original Status:** TODO · **Original Priority:** HIGH

Restore/fix the commented-out try/catch error handling in `BNFEWebhookController::certificateIssued`, so it matches its own documented error-response contract
TASK_DESC_124,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-098',
                        'title' => 'Cross-ref TASK-001 (BNFE dashboard routes need permission gating)',
                        'description' => <<<'TASK_DESC_125'
**Original Task ID:** TASK-098 (`ALP-RTM_TASKS.md`, Module 30 — BNFE Integration (Sync & Webhooks))
**Source:** REVIEW · **Source Requirement ID(s):** REV-001
**Original Status:** TODO · **Original Priority:** HIGH

Cross-ref TASK-001 (BNFE dashboard routes need permission gating)
TASK_DESC_125,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Bug',
                        'labels' => ['Source: Code Review', 'Security'],
                        'crossrefs' => ['TASK-001'],
                    ],
                    [
                        'orig_task_id' => 'TASK-099',
                        'title' => 'Document the BNFE integration\'s data-flow and failure modes for the ops team, since it\'s a genuinely load-bearing external dependency',
                        'description' => <<<'TASK_DESC_126'
**Original Task ID:** TASK-099 (`ALP-RTM_TASKS.md`, Module 30 — BNFE Integration (Sync & Webhooks))
**Source:** REVIEW · **Source Requirement ID(s):** —
**Original Status:** TODO · **Original Priority:** LOW

Document the BNFE integration's data-flow and failure modes for the ops team, since it's a genuinely load-bearing external dependency
TASK_DESC_126,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 31,
                'name' => '31 — Mobile App & Offline Sync',
                'description' => <<<'MODULE_DESC_127'
**Requirement Sources:** CLIENT: CR-008, CR-011, CR-012 · COMMITMENT: COM-026, COM-028 · REVIEW: REV-019 to REV-023

**Current Implementation:**
No mobile app source exists in this repository (confirmed by exhaustive search) — the Flutter app committed to in the ToR/SRS lives in a separate repository, so its completeness cannot be verified from this audit. The backend API surface (V1 + V2, both actively maintained) is what a mobile app would consume.

**Requirement Gap:**
No evidence of a genuine offline-first sync design (idempotency keys, conflict resolution, last-synced cursors) in the API — the one candidate mechanism found (`ProgramTrainingSnapshot`) is a simple single-blob autosave/overwrite, not a real sync engine. No SSO implementation exists despite COM-028's commitment. Maintaining two parallel, independently-evolving API versions (V1 and V2) is a confirmed ongoing engineering cost.

_Imported from `ALP-RTM_TASKS.md`, Module 31 — Mobile App & Offline Sync. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_127,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Mobile Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-100',
                        'title' => 'Obtain/audit the separate mobile app repository to actually verify CR-008/CR-011/CR-012/COM-026 (bilingual UI, accessibility, offline capability, mobile sign-up/sync) — cannot be assessed from this codebase alone',
                        'description' => <<<'TASK_DESC_128'
**Original Task ID:** TASK-100 (`ALP-RTM_TASKS.md`, Module 31 — Mobile App & Offline Sync)
**Source:** CLIENT · **Source Requirement ID(s):** CR-008, CR-011, CR-012
**Original Status:** BLOCKED · **Original Priority:** HIGH

Obtain/audit the separate mobile app repository to actually verify CR-008/CR-011/CR-012/COM-026 (bilingual UI, accessibility, offline capability, mobile sign-up/sync) — cannot be assessed from this codebase alone
TASK_DESC_128,
                        'status' => 'blocked',
                        'priority' => 'high',
                        'task_type' => 'Research',
                        'labels' => ['Source: Client Requirement (ToR)', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-101',
                        'title' => 'Design a genuine offline-sync mechanism (versioned records, per-entity idempotency keys, conflict resolution) if CR-008/CR-012\'s "offline data collection with automatic sync" is still a live requirement — the current `ProgramTrainingSnapshot`…',
                        'description' => <<<'TASK_DESC_129'
**Original Task ID:** TASK-101 (`ALP-RTM_TASKS.md`, Module 31 — Mobile App & Offline Sync)
**Source:** CLIENT · **Source Requirement ID(s):** CR-008, CR-012
**Original Status:** TODO · **Original Priority:** HIGH

Design a genuine offline-sync mechanism (versioned records, per-entity idempotency keys, conflict resolution) if CR-008/CR-012's "offline data collection with automatic sync" is still a live requirement — the current `ProgramTrainingSnapshot` mechanism does not meet this bar
TASK_DESC_129,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Feature',
                        'labels' => ['Source: Client Requirement (ToR)'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-102',
                        'title' => 'Decide and execute a plan to consolidate or formally deprecate API V1 in favor of V2 once the mobile app fully migrates, to stop the ongoing duplicate-maintenance cost',
                        'description' => <<<'TASK_DESC_130'
**Original Task ID:** TASK-102 (`ALP-RTM_TASKS.md`, Module 31 — Mobile App & Offline Sync)
**Source:** REVIEW · **Source Requirement ID(s):** REV-021
**Original Status:** TODO · **Original Priority:** MEDIUM

Decide and execute a plan to consolidate or formally deprecate API V1 in favor of V2 once the mobile app fully migrates, to stop the ongoing duplicate-maintenance cost
TASK_DESC_130,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 32,
                'name' => '32 — Undocumented / Scope-Creep Modules',
                'description' => <<<'MODULE_DESC_131'
**Requirement Sources:** REVIEW: REV-027, REV-028, REV-029

**Current Implementation:**
The codebase contains several fully-built modules that are **not mentioned anywhere in the ToR, SRS, UAT feedback, or Skilfo comparison documents**:
- **Task Tracking** (`UserTask` + related models) — live, fully wired, but gated to the Skilfo tenant only.
- **Form Builder** — live, appears to be load-bearing for the dynamic Monitoring forms (not itself a documented requirement, but likely necessary infrastructure for COM-021).
- **Support ticketing** (`Support`/`SupportRequest`) — live, fully wired with its own notification listener.
- **Backup UI** — live, but only supports a manual/admin-triggered backup (index/store/destroy), not the "automated backup procedures" CR-018/COM-031 actually commit to.
- **Audit logging** (`owen-it/laravel-auditing`) — live and genuinely wired to many domain models; a positive finding, not a gap.
- **Firebase Push** — infrastructure present (device-token storage, frontend client config) but the controller has zero methods, no route references it, and the Firebase credentials file is empty — effectively dead/unfinished.
- **Translations/i18n** — live and is the actual backbone of the site's bilingual behavior; should be credited against COM-023/NEW-012 rather than treated as scope creep.

**Requirement Gap:**
Task Tracking, Form Builder, and Support ticketing represent real, functioning scope that was never named in any contractual document — this needs product/commercial clarification (is it billable scope creep, a freebie, or actually out-of-scope and should be hidden/removed?). The Backup feature only partially satisfies CR-018/COM-031 since it has no scheduled/automated trigger. Firebase Push should either be finished or explicitly descoped/removed.

_Imported from `ALP-RTM_TASKS.md`, Module 32 — Undocumented / Scope-Creep Modules. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_131,
                'status' => 'in_progress',
                'priority' => 'high',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-103',
                        'title' => 'Get product/commercial clarification from UNICEF on the Task Tracking module — confirm it\'s an intentional, approved deliverable (currently Skilfo-tenant-only) rather than unapproved scope creep',
                        'description' => <<<'TASK_DESC_132'
**Original Task ID:** TASK-103 (`ALP-RTM_TASKS.md`, Module 32 — Undocumented / Scope-Creep Modules)
**Source:** REVIEW · **Source Requirement ID(s):** REV-027
**Original Status:** REVIEW REQUIRED · **Original Priority:** MEDIUM

Get product/commercial clarification from UNICEF on the Task Tracking module — confirm it's an intentional, approved deliverable (currently Skilfo-tenant-only) rather than unapproved scope creep
TASK_DESC_132,
                        'status' => 'in_review',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-104',
                        'title' => 'Get product clarification on the Form Builder module — confirm its relationship to the Monitoring module\'s dynamic forms and whether it should be documented as part of COM-021',
                        'description' => <<<'TASK_DESC_133'
**Original Task ID:** TASK-104 (`ALP-RTM_TASKS.md`, Module 32 — Undocumented / Scope-Creep Modules)
**Source:** REVIEW · **Source Requirement ID(s):** REV-027
**Original Status:** REVIEW REQUIRED · **Original Priority:** MEDIUM

Get product clarification on the Form Builder module — confirm its relationship to the Monitoring module's dynamic forms and whether it should be documented as part of COM-021
TASK_DESC_133,
                        'status' => 'in_review',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-105',
                        'title' => 'Get product/commercial clarification on the Support ticketing module',
                        'description' => <<<'TASK_DESC_134'
**Original Task ID:** TASK-105 (`ALP-RTM_TASKS.md`, Module 32 — Undocumented / Scope-Creep Modules)
**Source:** REVIEW · **Source Requirement ID(s):** REV-027
**Original Status:** REVIEW REQUIRED · **Original Priority:** LOW

Get product/commercial clarification on the Support ticketing module
TASK_DESC_134,
                        'status' => 'in_review',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-106',
                        'title' => 'Add a scheduled/automated trigger to the Backup feature (currently manual-only via index/store/destroy) to actually satisfy CR-018/COM-031\'s "automated backup procedures" commitment',
                        'description' => <<<'TASK_DESC_135'
**Original Task ID:** TASK-106 (`ALP-RTM_TASKS.md`, Module 32 — Undocumented / Scope-Creep Modules)
**Source:** CLIENT · **Source Requirement ID(s):** CR-018
**Original Status:** TODO · **Original Priority:** HIGH

Add a scheduled/automated trigger to the Backup feature (currently manual-only via index/store/destroy) to actually satisfy CR-018/COM-031's "automated backup procedures" commitment
TASK_DESC_135,
                        'status' => 'todo',
                        'priority' => 'high',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Client Requirement (ToR)'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-107',
                        'title' => 'Verify the Backup feature performs a full DB+file backup, not just a manual DB dump',
                        'description' => <<<'TASK_DESC_136'
**Original Task ID:** TASK-107 (`ALP-RTM_TASKS.md`, Module 32 — Undocumented / Scope-Creep Modules)
**Source:** CLIENT · **Source Requirement ID(s):** CR-018
**Original Status:** REVIEW REQUIRED · **Original Priority:** MEDIUM

Verify the Backup feature performs a full DB+file backup, not just a manual DB dump
TASK_DESC_136,
                        'status' => 'in_review',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Client Requirement (ToR)', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-108',
                        'title' => 'Decide whether to finish (configure real Firebase credentials, wire up `FirebasePushController` methods) or formally remove the Firebase Push scaffolding — currently dead code with a 0-byte credentials file',
                        'description' => <<<'TASK_DESC_137'
**Original Task ID:** TASK-108 (`ALP-RTM_TASKS.md`, Module 32 — Undocumented / Scope-Creep Modules)
**Source:** REVIEW · **Source Requirement ID(s):** REV-028
**Original Status:** TODO · **Original Priority:** LOW

Decide whether to finish (configure real Firebase credentials, wire up `FirebasePushController` methods) or formally remove the Firebase Push scaffolding — currently dead code with a 0-byte credentials file
TASK_DESC_137,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-109',
                        'title' => 'No action needed for Translations/i18n or Audit Logging — both are genuine positive findings; document them as satisfying part of COM-023/NEW-012 (bilingual) and CR-013 (data integrity/traceability) respectively',
                        'description' => <<<'TASK_DESC_138'
**Original Task ID:** TASK-109 (`ALP-RTM_TASKS.md`, Module 32 — Undocumented / Scope-Creep Modules)
**Source:** REVIEW · **Source Requirement ID(s):** —
**Original Status:** NOT APPLICABLE · **Original Priority:** LOW

No action needed for Translations/i18n or Audit Logging — both are genuine positive findings; document them as satisfying part of COM-023/NEW-012 (bilingual) and CR-013 (data integrity/traceability) respectively
TASK_DESC_138,
                        'status' => 'cancelled',
                        'priority' => 'low',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 33,
                'name' => '33 — Database & Data Integrity',
                'description' => <<<'MODULE_DESC_139'
**Requirement Sources:** REVIEW: REV-007, REV-008

**Current Implementation:**
Generally solid. Foreign-key constraints (`constrained()`/`foreign()`) are the dominant pattern (66 of 148 migration files sampled use them explicitly), with unique constraints correctly applied to `uuid`/`mobile`/`email` on key tables. Soft-delete + versioning ("restore" feature) confirmed genuinely implemented (not a stub) for Profile/Trainer/Mcp.

**Requirement Gap:**
A small number of migrations (5 in the sample) use unconstrained `_id` integer columns rather than proper FKs — worth a full pass rather than the sample already done. `SoftDeletes`/`Versionable` are not applied to `Enterprise`/`Assessor`, an inconsistency with the rest of the profile types (already tracked as TASK-012/044).

_Imported from `ALP-RTM_TASKS.md`, Module 33 — Database & Data Integrity. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_139,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-110',
                        'title' => 'Do a full pass (not just the sample already done) across all 148 migrations for unconstrained `_id` columns that should have proper foreign keys',
                        'description' => <<<'TASK_DESC_140'
**Original Task ID:** TASK-110 (`ALP-RTM_TASKS.md`, Module 33 — Database & Data Integrity)
**Source:** REVIEW · **Source Requirement ID(s):** —
**Original Status:** TODO · **Original Priority:** MEDIUM

Do a full pass (not just the sample already done) across all 148 migrations for unconstrained `_id` columns that should have proper foreign keys
TASK_DESC_140,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-111',
                        'title' => 'Cross-ref TASK-012/TASK-044 (SoftDeletes/Versionable parity for Enterprise/Assessor)',
                        'description' => <<<'TASK_DESC_141'
**Original Task ID:** TASK-111 (`ALP-RTM_TASKS.md`, Module 33 — Database & Data Integrity)
**Source:** REVIEW · **Source Requirement ID(s):** REV-007
**Original Status:** TODO · **Original Priority:** MEDIUM

Cross-ref TASK-012/TASK-044 (SoftDeletes/Versionable parity for Enterprise/Assessor)
TASK_DESC_141,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => ['TASK-012', 'TASK-044'],
                    ],
                    [
                        'orig_task_id' => 'TASK-112',
                        'title' => 'Document the dual tenancy-scoping mechanism as a known architectural debt item (cross-ref TASK-013) so it\'s tracked for eventual consolidation rather than silently accumulating more call sites',
                        'description' => <<<'TASK_DESC_142'
**Original Task ID:** TASK-112 (`ALP-RTM_TASKS.md`, Module 33 — Database & Data Integrity)
**Source:** REVIEW · **Source Requirement ID(s):** REV-008
**Original Status:** TODO · **Original Priority:** LOW

Document the dual tenancy-scoping mechanism as a known architectural debt item (cross-ref TASK-013) so it's tracked for eventual consolidation rather than silently accumulating more call sites
TASK_DESC_142,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Documentation',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => ['TASK-013'],
                    ],
                ],
            ],
            [
                'module_num' => 34,
                'name' => '34 — Laravel Architecture & Backend Code Quality',
                'description' => <<<'MODULE_DESC_143'
**Requirement Sources:** COMMITMENT: COM-030 · REVIEW: REV-017 to REV-021

**Current Implementation:**
Generally pragmatic and appropriate for the scale — no evidence of overengineering (no Repository layer, minimal/justified use of Contracts, no CQRS/microservices). Form Requests are the dominant validation mechanism (162 classes); Policies (27) are registered and actively invoked (97 call sites); Events/Listeners are fully wired, not orphaned; no N+1 pattern found in the one high-traffic path spot-checked (`ProfileService`).

**Requirement Gap:**
Caching is sparse — only one dashboard-adjacent query is cached repo-wide, despite the chart/stat-heavy dashboard requirements (tracked as TASK-084). `QUEUE_CONNECTION=sync` in `.env.example` risks running 18 `ShouldQueue` job classes (imports/exports/BNFE sync) inline in production if not overridden. Exception handling only special-cases one exception type, with no differentiated JSON-API vs. Inertia-web handling for the common cases (validation, not-found, unauthenticated).

_Imported from `ALP-RTM_TASKS.md`, Module 34 — Laravel Architecture & Backend Code Quality. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_143,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-113',
                        'title' => 'Confirm production `.env` overrides `QUEUE_CONNECTION` away from `sync`, given the number of I/O-heavy queued jobs (imports/exports/BNFE sync)',
                        'description' => <<<'TASK_DESC_144'
**Original Task ID:** TASK-113 (`ALP-RTM_TASKS.md`, Module 34 — Laravel Architecture & Backend Code Quality)
**Source:** REVIEW · **Source Requirement ID(s):** REV-019
**Original Status:** REVIEW REQUIRED · **Original Priority:** MEDIUM

Confirm production `.env` overrides `QUEUE_CONNECTION` away from `sync`, given the number of I/O-heavy queued jobs (imports/exports/BNFE sync)
TASK_DESC_144,
                        'status' => 'in_review',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: Code Review', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-114',
                        'title' => 'Expand `app/Exceptions/Handler.php` to explicitly differentiate JSON-API vs. Inertia-web responses for `ValidationException`, `ModelNotFoundException`, and `AuthenticationException`; resolve the currently commented-out `unauthenticated()`…',
                        'description' => <<<'TASK_DESC_145'
**Original Task ID:** TASK-114 (`ALP-RTM_TASKS.md`, Module 34 — Laravel Architecture & Backend Code Quality)
**Source:** REVIEW · **Source Requirement ID(s):** REV-018
**Original Status:** TODO · **Original Priority:** MEDIUM

Expand `app/Exceptions/Handler.php` to explicitly differentiate JSON-API vs. Inertia-web responses for `ValidationException`, `ModelNotFoundException`, and `AuthenticationException`; resolve the currently commented-out `unauthenticated()` override one way or the other
TASK_DESC_145,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-115',
                        'title' => 'Cross-ref TASK-084 (dashboard/report caching)',
                        'description' => <<<'TASK_DESC_146'
**Original Task ID:** TASK-115 (`ALP-RTM_TASKS.md`, Module 34 — Laravel Architecture & Backend Code Quality)
**Source:** REVIEW · **Source Requirement ID(s):** REV-017
**Original Status:** TODO · **Original Priority:** MEDIUM

Cross-ref TASK-084 (dashboard/report caching)
TASK_DESC_146,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => ['TASK-084'],
                    ],
                    [
                        'orig_task_id' => 'TASK-116',
                        'title' => 'Confirm test coverage exists/is adequate per COM-030\'s testing commitment (feature/integration/UAT tests) — not independently assessed in this audit pass, recommend a dedicated test-coverage review',
                        'description' => <<<'TASK_DESC_147'
**Original Task ID:** TASK-116 (`ALP-RTM_TASKS.md`, Module 34 — Laravel Architecture & Backend Code Quality)
**Source:** COMMITMENT · **Source Requirement ID(s):** COM-030
**Original Status:** REVIEW REQUIRED · **Original Priority:** MEDIUM

Confirm test coverage exists/is adequate per COM-030's testing commitment (feature/integration/UAT tests) — not independently assessed in this audit pass, recommend a dedicated test-coverage review
TASK_DESC_147,
                        'status' => 'in_review',
                        'priority' => 'medium',
                        'task_type' => 'Research',
                        'labels' => ['Source: SRS Commitment', 'Needs Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-117',
                        'title' => 'Cross-ref TASK-102 (API V1/V2 consolidation)',
                        'description' => <<<'TASK_DESC_148'
**Original Task ID:** TASK-117 (`ALP-RTM_TASKS.md`, Module 34 — Laravel Architecture & Backend Code Quality)
**Source:** REVIEW · **Source Requirement ID(s):** REV-021
**Original Status:** TODO · **Original Priority:** MEDIUM

Cross-ref TASK-102 (API V1/V2 consolidation)
TASK_DESC_148,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => ['TASK-102'],
                    ],
                    [
                        'orig_task_id' => 'TASK-118',
                        'title' => 'Clean up the `UserDevice` model\'s inconsistent timestamp handling (`$timestamps = false` but `created_at` manually kept in `$fillable`, no `updated_at`/last-seen tracking) if device state is meant to be tracked reliably for push targeting',
                        'description' => <<<'TASK_DESC_149'
**Original Task ID:** TASK-118 (`ALP-RTM_TASKS.md`, Module 34 — Laravel Architecture & Backend Code Quality)
**Source:** REVIEW · **Source Requirement ID(s):** —
**Original Status:** TODO · **Original Priority:** LOW

Clean up the `UserDevice` model's inconsistent timestamp handling (`$timestamps = false` but `created_at` manually kept in `$fillable`, no `updated_at`/last-seen tracking) if device state is meant to be tracked reliably for push targeting
TASK_DESC_149,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                ],
            ],
            [
                'module_num' => 35,
                'name' => '35 — Inertia + Vue Frontend Patterns',
                'description' => <<<'MODULE_DESC_150'
**Requirement Sources:** REVIEW: REV-024, REV-025, REV-026

**Current Implementation:**
Authorization-aware UI (`hasPermission()` used 94 times) is a legitimate UX-hardening layer backed by real server-side enforcement, not a security gap. Inertia's shared error bag (`useForm()`/`form.errors`) is used consistently across sampled forms. A shared `AppTable.vue` component and PrimeVue `DataTable` underlie the majority of list pages.

**Requirement Gap:**
`Profile/Learner/Index.vue`, `Trainer/Index.vue`, and `MCP/Index.vue` (~2,800 combined lines) each hand-roll their own table/filter/bulk-action/pagination logic instead of using the existing shared `AppTable.vue` — a legitimate, non-speculative consolidation opportunity. Duplicate-cased `Components`/`components` and `Composables`/`composables` directories risk ambiguous imports.

_Imported from `ALP-RTM_TASKS.md`, Module 35 — Inertia + Vue Frontend Patterns. See that file in the repository root for the complete, unabridged source analysis._
MODULE_DESC_150,
                'status' => 'in_progress',
                'priority' => 'medium',
                'scope' => 'Web Application',
                'tasks' => [
                    [
                        'orig_task_id' => 'TASK-119',
                        'title' => 'Consolidate `Profile/Learner/Index.vue`, `Trainer/Index.vue`, `MCP/Index.vue` onto the existing shared `AppTable.vue`/composable pattern to eliminate ~2,800 lines of duplicated list/filter/bulk-action code',
                        'description' => <<<'TASK_DESC_151'
**Original Task ID:** TASK-119 (`ALP-RTM_TASKS.md`, Module 35 — Inertia + Vue Frontend Patterns)
**Source:** REVIEW · **Source Requirement ID(s):** REV-024
**Original Status:** TODO · **Original Priority:** MEDIUM

Consolidate `Profile/Learner/Index.vue`, `Trainer/Index.vue`, `MCP/Index.vue` onto the existing shared `AppTable.vue`/composable pattern to eliminate ~2,800 lines of duplicated list/filter/bulk-action code
TASK_DESC_151,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-120',
                        'title' => 'Reconcile the duplicate-cased `resources/js/Components`/`components` and `Composables`/`composables` directories',
                        'description' => <<<'TASK_DESC_152'
**Original Task ID:** TASK-120 (`ALP-RTM_TASKS.md`, Module 35 — Inertia + Vue Frontend Patterns)
**Source:** REVIEW · **Source Requirement ID(s):** REV-025
**Original Status:** TODO · **Original Priority:** LOW

Reconcile the duplicate-cased `resources/js/Components`/`components` and `Composables`/`composables` directories
TASK_DESC_152,
                        'status' => 'todo',
                        'priority' => 'low',
                        'task_type' => 'Maintenance',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => [],
                    ],
                    [
                        'orig_task_id' => 'TASK-121',
                        'title' => 'Cross-ref TASK-081 (most dashboard stat cards not clickable)',
                        'description' => <<<'TASK_DESC_153'
**Original Task ID:** TASK-121 (`ALP-RTM_TASKS.md`, Module 35 — Inertia + Vue Frontend Patterns)
**Source:** REVIEW · **Source Requirement ID(s):** REV-026
**Original Status:** TODO · **Original Priority:** MEDIUM

Cross-ref TASK-081 (most dashboard stat cards not clickable)
TASK_DESC_153,
                        'status' => 'todo',
                        'priority' => 'medium',
                        'task_type' => 'Enhancement',
                        'labels' => ['Source: Code Review'],
                        'crossrefs' => ['TASK-081'],
                    ],
                ],
            ],
        ];
    }
}
