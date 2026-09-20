# Memory

Durable context for anyone — human or agent — picking this project up. Records decisions, traps and environment facts that are expensive to rediscover. Append; do not rewrite history.

---

## Environment Facts

**MySQL listens on a Unix socket only.** `127.0.0.1:3306` refuses connections and `systemctl start mysql` times out, but the server is running. Every Artisan command that touches the database needs:

```bash
DB_SOCKET=/var/run/mysqld/mysqld.sock php artisan <command>
```

**`pdo_sqlite` is not installed.** `phpunit.xml` configures `sqlite` / `:memory:`, so all 110 pre-existing tests fail with "could not find driver". This predates the current work. Two options: install `php8.3-sqlite3` (needs a sudo password, which is not available in an agent session), or run the suite against MySQL:

```bash
DB_SOCKET=/var/run/mysqld/mysqld.sock DB_CONNECTION=mysql DB_DATABASE=pm_schema_check vendor/bin/phpunit tests/Feature/OMS
```

PHPUnit's `<env>` entries do not force-override real environment variables, which is why the override above works.

**`pm_schema_check` is a scratch database** created to verify migrations without touching `project_manager`. Safe to drop.

---

## 2026-09-20 — Database redesign

### What changed

The four original project tables were reshaped, `project_module_tasks` was replaced, and 29 tables were added across delivery, meetings, to-dos, Git, time tracking, collaboration and reporting. Full reasoning is in `DESIGN.md`.

### Decisions that are settled

**`project_module_tasks` became `tasks`; its pivot became `task_assignments`.** The old name forced `project_module_task_id` foreign keys and asserted that a task is a child of a module — the exact constraint that had to be removed so work could be captured before a breakdown exists. `tasks.project_id` is required, `project_module_id` is nullable. The `ProjectModuleTask` model was an empty stub with no dependents, so nothing broke. **If this naming is ever revisited, the module-optional requirement must survive the change.**

**The old task status column was three things at once.** `not_assigned, assigned, in_progress, completed, reassigned, in_review, approved, staging_done, live_done, rejected, cancelled` mixed assignment state, review outcome and deployment stage. A task could not be "in review" and "on staging" simultaneously. Now `status`, `review_status` and `deployment_stage` are separate, and assignment state lives in `task_assignments`. **Do not merge them back.**

**Status columns are `string` plus a PHP enum cast, never MySQL `ENUM`.** Adding an `ENUM` value needs a locking `ALTER TABLE` for what is a product decision. `scopes` and `task_types` still use MySQL `ENUM` and were left alone deliberately, so the codebase contains both patterns — new tables follow the string rule.

**Migrations were edited in place rather than layered.** The four original migrations were two days old and unreleased, and the user asked for them to be updated. **This means the development database must be rebuilt** with `migrate:fresh --seed`; a plain `migrate` will not pick up the reshaped tables.

**`ProjectModule::deliveryScopes()` is not called `scopes()`.** A relationship named `scopes` shadows Eloquent's passthrough to `Builder::scopes()`. The pivot table is still `project_module_scope`.

### Trap: `deleted_at` in a unique key does not work on MySQL

The first draft used `unique(project_id, user_id, deleted_at)` on `project_members`, intending to allow duplicates among soft-deleted rows while keeping live rows unique. **MySQL treats `NULL` in a unique index as distinct**, so two live rows with `deleted_at IS NULL` both pass. The constraint would have silently permitted the exact duplicate it was written to prevent.

Corrected to plain unique keys on `project_members`, `projects` and `git_repositories`. Consequence: re-adding a removed project member must **restore the soft-deleted row**, not insert a new one. A soft-deleted project keeps its `code` reserved, which is desirable — task references like `ALPHA-12` should never be reassigned.

### Trap: MySQL rejects index names over 64 characters

`project_progress_snapshots(project_id, sprint_id, snapshot_on)` generated a 66-character name and failed at migration time with error 1059. It now passes an explicit name, `progress_snapshots_project_sprint_date_unique`. Reading the migration would not have caught this; running it did. **Run new migrations against MySQL before considering them done.**

### Pre-existing bug found and fixed

`2026_09_18_091312_alter_users_tabel_add_new_columns` had a `down()` that dropped `created_by`, `updated_by` and `deleted_by` with `dropColumn()`. MySQL refuses this while the self-referencing foreign keys exist, so a full rollback failed with error 1553. Changed to `dropConstrainedForeignId()`. This was unrelated to the redesign; it surfaced only because the whole stack was rolled back as a check.

### Redundant indexes removed

`$table->morphs()` already creates an index on the type and id pair. `comments` and `attachments` initially declared their own on top of it. The columns are now declared explicitly so only the intended index exists. The same duplication exists throughout the original migrations, which paired `->index()` with `->references()->on()` on every audit column — MySQL creates an index for a foreign key anyway. New migrations use `->constrained()`.

### Availability model

There is no stored "available hours" column, because availability is a question about a date range rather than a property of a user:

```
available(user, day) = user_work_schedules.capacity_hours
                     − sum(resource_allocations.hours_per_day where status in planned, confirmed)
                     − approved time_off_requests covering that day
```

`resource_allocations` is the **plan**, `time_logs` is the **actuals**, and they are separate tables so estimated-versus-actual reporting is possible. `user_work_schedules` is versioned by `effective_from` / `effective_until` so a contract change does not rewrite history.

### One engine for all to-do items

Task checklists, personal lists, daily plans and meeting action items are all `todo_lists` plus `todo_items`, discriminated by `type`. A separate `meeting_action_items` table was considered and rejected: it would have duplicated assignment, due dates, completion and task linking, and "everything assigned to me" would have needed a union.

The cost is four nullable context keys (`owner_id`, `project_id`, `task_id`, `meeting_id`) whose valid combinations are governed by `type` rather than by the database. **Those invariants need an observer or form request; the schema cannot enforce them.**

### Git ingest durability

`git_events` stores the raw webhook payload before anything is parsed, so a failed ingest is replayable. `unique(git_repository_id, external_event_id)` makes provider redelivery idempotent — without it, a retried webhook would double-count commits. `git_commit_task` is many-to-many with a `link_source` column because one commit can touch several tasks, and a parsed link is a different fact from a manual one.

### Verification performed

- All 22 migrations run and roll back cleanly on MySQL 8.
- 24 new feature tests pass, covering task numbering, multi-project and multi-user assignment, the role uniqueness constraint, the `open` / `overdue` / `assignedTo` / `active` / `forMember` scopes, availability overlap arithmetic, commit-to-task linking, SHA uniqueness per repository, and token encryption.
- A runtime smoke check created a full graph — team, users, two projects, tasks with matching numbers, cross-project assignments, an allocation, a work schedule, a repository, a commit linked to a task, a daily to-do list, a meeting with minutes, and a time log — and every relationship, cast and enum label resolved.
- Larastan level 7: no errors. Pint: no changes.

### Known gaps carried forward

- `tasks.logged_hours`, `projects.progress_percentage` and `project_modules.progress_percentage` are caches with no writer yet. Each needs exactly one owner plus a reconciliation command.
- `projects.next_task_number` must be incremented in the same transaction that inserts a task, or references will collide.
- Nothing prevents a task's module belonging to a different project, or a cycle in `task_dependencies`. Both need application-level checks.
- Factories exist for 17 models; the remaining ones are listed in `TASKS.md`.

---

## Conventions Worth Remembering

- Controllers respond twice: Inertia redirect with a flashed toast for page visits, JSON for modal forms. `Setup\TaskTypeController` is the reference.
- Frontend payloads come from explicit methods such as `toSetupArray()`, never from serialising a model.
- `App\Enums\Concerns\HasOptions` supplies `label()`, `options()` and `values()`. Override `label()` only where `Str::headline()` is wrong: `ReadyForQa` → "Ready for QA", `GitHub`, `QaEngineer`, `DevOps`, `Todo` → "To Do".
- `App\Models\Concerns\HasAuditUsers` supplies `creator`, `updater` and `deleter`.
- `App\Concerns\HasProjectWork` gives `User` its cross-project work and capacity relationships, mirroring how `HasTeams` is organised.
- The task display reference is computed by `Task::reference()` as `{project.code}-{number}`. It is not stored.
