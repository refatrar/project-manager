# Database Design

This document records the schema, the reasoning behind each decision, and the queries the design exists to serve.

## 1. Review of the Original Four Tables

The design started from `projects`, `project_modules`, `project_members` and `project_module_tasks`. The following problems were found and fixed.

### `projects`

| Problem | Consequence | Fix |
| --- | --- | --- |
| No `team_id` | Every route is prefixed `{current_team}`, but projects were global. Any team could read any project. | Added `team_id` with a cascading constraint. |
| `description` was `NOT NULL` | A project could not be created before its brief was written. | Made nullable. |
| `start_date` and `end_date` were `NOT NULL` | A project could not be created before dates were agreed. | Made nullable, and added `actual_start_date` / `actual_end_date` so plan and reality are separate. |
| No project identifier | No way to build a short task reference such as `ALPHA-114`. | Added `code`, unique per team, plus `slug` for URLs. |
| `title` here, `name` on modules | Two names for one concept. | Standardised on `name`. |
| No priority, budget, health or progress | Cannot rank or report on a portfolio. | Added `priority`, `budget`, `currency`, `health`, `progress_percentage`, `estimated_hours`, `owner_id`, `archived_at`. |
| `down()` dropped `project_members` | Rolling back one migration silently destroyed a table owned by another. | `down()` now drops only what `up()` created. |

### `project_modules`

| Problem | Consequence | Fix |
| --- | --- | --- |
| Self-reference named `project_module_id` | Indistinguishable from a foreign reference to the same table. | Renamed to `parent_id`. |
| `status` was a bare `string` with no default | Every insert had to remember to set it, and the valid set was undocumented. | `string` column with a default, cast to `ProjectModuleStatus`. |
| No ordering column | A module tree cannot be presented in a stable order. | Added `position`. |
| `project_module_scope` had no key | Duplicate rows were possible and the table could not be deduplicated. | Composite primary key on both columns. |
| `project_module_scope` was created in `up()` but never dropped in `down()` | Rollback then re-migrate failed on an existing table. | Dropped in `down()`. |

### `project_members`

| Problem | Consequence | Fix |
| --- | --- | --- |
| No unique constraint | The same user could be added to a project repeatedly, double-counting capacity. | `unique(project_id, user_id)`. |
| `role` was a free string | Any typo became a new role, and no authorization could depend on it. | Cast to `ProjectMemberRole`, which carries `canManageProject()`. |
| No capacity data | "Who is free next week" was unanswerable. | Added `allocation_percentage`, `hourly_rate`, `joined_on`, `left_on`. |

### `project_module_tasks`

This table had the most serious problems.

**A task could not exist without a module.** `project_module_id` was required, so nothing could be captured before the breakdown existed, and every "tasks in this project" query needed a join.

**The status column conflated three independent things.** The original set was `not_assigned, assigned, in_progress, completed, reassigned, in_review, approved, staging_done, live_done, rejected, cancelled`. That mixes:

- *assignment state* (`not_assigned`, `assigned`, `reassigned`), which is derivable from the assignment table and becomes wrong the moment a second person joins the task;
- *review outcome* (`in_review`, `approved`, `rejected`);
- *deployment stage* (`staging_done`, `live_done`), which is orthogonal to both — code can be live and still under review after a hotfix.

Because they shared one column, a task that was in review **and** already on staging could not be represented, and "how many tasks are in progress" could not be answered without listing deployment states.

These are now three columns: `status` for the lifecycle, `review_status` for the outcome, `deployment_stage` for where the code is. Assignment state lives in `task_assignments` where it belongs.

**The pivot could not support the requirements.** `project_module_task_user` had no primary key, no unique constraint, a free-text `status`, no role, no `assigned_by` and no hours. It could not express "two developers and one reviewer", could not prevent duplicates, and could not feed capacity planning.

**Soft deletes were half-applied.** Modules had `deleted_by` and `deleted_at`; tasks did not.

**Missing fields for the stated goals.** No estimates, no logged hours, no dates, no ordering, no progress, no dependencies, no history.

### Naming decision

`project_module_tasks` was renamed to `tasks`, and its pivot to `task_assignments`. The old name forced `project_module_task_id` foreign keys and a `project_module_task_user` pivot, and it asserted that a task is a child of a module — which is exactly the constraint that had to be removed. A task belongs to a project; a module is an optional grouping.

The `ProjectModuleTask` model was an empty stub with no controllers, tests or frontend references, so nothing depended on the old name.

## 2. Table Inventory

29 tables were added and 4 were reshaped.

### Setup
| Table | Purpose |
| --- | --- |
| `scopes` | Existing. Delivery scopes assignable to modules. |
| `task_types` | Existing. Bug, feature, chore and so on. |
| `labels` | **New.** Team-scoped tags for tasks. |

### Delivery
| Table | Purpose |
| --- | --- |
| `projects` | Reshaped. Team-scoped project with plan, actuals and rollups. |
| `project_modules` | Reshaped. Nestable breakdown of a project. |
| `project_module_scope` | Reshaped. Module-to-scope pivot. |
| `project_members` | Reshaped. Project roles and per-project capacity. |
| `milestones` | **New.** Dated, optionally billable delivery checkpoints. |
| `sprints` | **New.** Time-boxed iterations with capacity. |
| `tasks` | **New.** Replaces `project_module_tasks`. |
| `task_assignments` | **New.** Replaces `project_module_task_user`. |
| `task_dependencies` | **New.** Blocking and relating links between tasks. |
| `task_status_histories` | **New.** Status transition trail for cycle-time reporting. |
| `label_task` | **New.** Task-to-label pivot. |

### Meetings
| Table | Purpose |
| --- | --- |
| `meetings` | **New.** Schedule, agenda, minutes and decisions. |
| `meeting_attendees` | **New.** Users or external guests, with attendance outcome. |
| `meeting_agenda_items` | **New.** Ordered agenda, each optionally tied to a task. |

### To-dos
| Table | Purpose |
| --- | --- |
| `todo_lists` | **New.** Personal, daily, task-checklist, meeting-action and generated lists. |
| `todo_items` | **New.** Individual items, optionally linked to a task and assigned to a user. |

### Git
| Table | Purpose |
| --- | --- |
| `git_identities` | **New.** Maps a provider account to an application user. |
| `git_repositories` | **New.** Connected repository with encrypted credentials. |
| `git_branches` | **New.** Tracked branches, optionally linked to a task. |
| `git_commits` | **New.** Ingested commits with authorship and change statistics. |
| `git_commit_task` | **New.** Many-to-many commit-to-task links with their detection source. |
| `git_pull_requests` | **New.** Pull request state and review counts. |
| `git_events` | **New.** Verbatim webhook payloads, replayable. |

### Time and availability
| Table | Purpose |
| --- | --- |
| `user_work_schedules` | **New.** Recurring weekly capacity, versioned by effective date. |
| `time_off_requests` | **New.** Leave that reduces capacity once approved. |
| `resource_allocations` | **New.** Forward bookings of hours — the source of "occupied". |
| `time_logs` | **New.** Actual effort, with timer support and approval. |

### Collaboration and reporting
| Table | Purpose |
| --- | --- |
| `comments` | **New.** Polymorphic threaded discussion. |
| `comment_mentions` | **New.** Comment-to-user mention pivot. |
| `attachments` | **New.** Polymorphic file metadata. |
| `activities` | **New.** Append-only audit trail and activity feed. |
| `project_progress_snapshots` | **New.** Daily rollups for burndown and trend charts. |

## 3. Key Design Decisions

### 3.1 Tasks reference both project and module

`tasks.project_id` is required; `tasks.project_module_id` is nullable with `ON DELETE SET NULL`. A task survives the deletion of its module, work can be captured before the breakdown exists, and the common "all tasks in this project" query needs no join. The cost is that application code must keep the two consistent when a task moves between modules.

### 3.2 Task references are computed, not stored

`tasks.number` is unique per project, and the display reference is built in `Task::reference()` as `{project.code}-{number}`. Storing the rendered string would duplicate `projects.code` and go stale if a code were ever corrected. `projects.next_task_number` supplies the next value so allocation needs no `MAX()` scan.

### 3.3 Assignment is a first-class record

`task_assignments` has a surrogate key, `unique(task_id, user_id, role)`, its own status, `allocated_hours`, `assigned_by` and five timestamps. This is what makes FR-3 work: several users on one task in different roles, one user across many projects, and reassignment that preserves history because `unassigned_at` is set rather than the row deleted.

### 3.4 Unique keys deliberately exclude `deleted_at`

A common pattern is `unique(a, b, deleted_at)` to allow duplicates among soft-deleted rows. **In MySQL this does not work as intended.** MySQL treats `NULL` values in a unique index as distinct, so two live rows with `deleted_at IS NULL` both pass. The constraint would silently permit the exact duplicate it was written to prevent.

`project_members`, `projects` and `git_repositories` therefore use plain unique keys on their business columns. Re-adding a previously removed project member restores the existing soft-deleted row instead of inserting a second one, which also preserves that member's history. A soft-deleted project keeps its `code` reserved, which is correct — references such as `ALPHA-12` should never be reassigned.

### 3.5 One engine for all to-do items

Task checklists, personal lists, daily plans and meeting action items are all `todo_lists` plus `todo_items`, discriminated by `todo_lists.type` and pointed at context by nullable `project_id`, `task_id`, `meeting_id` and `owner_id`.

The alternative was a separate `meeting_action_items` table. That would duplicate assignment, due dates, completion tracking and task linking. One engine means "everything assigned to me" is a single query across checklists, personal items and meeting actions.

The cost is four nullable foreign keys whose valid combinations are governed by `type` rather than by the database. Those invariants belong in the application layer:

| `type` | Expected context |
| --- | --- |
| `task_checklist` | `task_id` set, `owner_id` null |
| `meeting_actions` | `meeting_id` set |
| `daily` | `owner_id` and `scheduled_for` set |
| `custom` / `generated` | `owner_id` or `project_id` set |

### 3.6 Availability is computed from three tables

No column stores "available hours", because availability is a question about a date range, not a property of a user.

```
capacity(user, day)   = user_work_schedules.capacity_hours
                        for that weekday, where the row is effective on that day

occupied(user, day)   = sum(resource_allocations.hours_per_day)
                        where status in (planned, confirmed)
                        and the day falls in [starts_on, ends_on]

unavailable(user, day)= approved time_off_requests covering that day

available(user, day)  = capacity − occupied − unavailable
```

`user_work_schedules` is versioned by `effective_from` / `effective_until` so a contract change does not rewrite history. `resource_allocations` holds the *plan*; `time_logs` holds the *actuals*. Keeping them apart is what allows estimated-versus-actual reporting.

Supporting indexes: `user_work_schedules(user_id, effective_from, effective_until)`, `resource_allocations(user_id, starts_on, ends_on)`, `time_off_requests(user_id, starts_on, ends_on)`, `time_logs(user_id, logged_on)`.

### 3.7 Git ingest is durable before it is correct

`git_events` stores the raw payload, the provider event id, and a processing status. The webhook endpoint validates the signature, writes the row, and returns. Parsing happens on the queue.

`unique(git_repository_id, external_event_id)` makes redelivery idempotent — every provider retries webhooks, and without this a retry would double-count commits. `unique(git_repository_id, sha)` does the same for commits, allowing the same SHA to exist in a fork.

`git_commit_task` is many-to-many with a `link_source` column, because one commit legitimately touches several tasks and because a link found by parsing a commit message should be distinguishable from one a person made by hand.

`git_identities` is what turns commit history into per-person reporting: a commit arrives with an author email, which resolves to a user, which makes `user->commits` and per-developer contribution reports possible.

### 3.8 Progress is snapshotted, not recomputed

`project_progress_snapshots` stores one row per project per day with task counts, hours, commit activity and a health rating. A burndown chart over a quarter is then a 90-row read.

The alternative — recomputing from `tasks` and `task_status_histories` on each chart render — gets slower as the project grows and cannot reconstruct counts as they stood at a past date once tasks are deleted. `task_status_histories` remains the audit record for cycle-time analysis.

### 3.9 Audit columns are consistent

Domain tables carry `created_by`, `created_at`, `updated_by`, `updated_at`, `deleted_by`, `deleted_at`, following the convention already present in `scopes` and `task_types`. `App\Models\Concerns\HasAuditUsers` supplies the `creator`, `updater` and `deleter` relationships so 20 models do not each declare them.

Append-only tables (`activities`, `task_dependencies`) set `const UPDATED_AT = null`. `task_status_histories` uses its own `changed_at` and disables timestamps entirely. Pure pivots carry only what they need.

### 3.10 Foreign keys use `constrained()`

New and reshaped migrations use `->constrained()->cascadeOnDelete()` and `->nullOnDelete()` rather than the longhand `->index()->references('id')->on(...)` in the original files. The longhand added an explicit index on top of the one MySQL creates for the foreign key, producing a redundant duplicate on every audit column.

Delete behaviour follows ownership:

| Relationship | Behaviour | Reasoning |
| --- | --- | --- |
| Child of a project | `cascade` | A module or task has no meaning without its project. |
| Optional grouping (`project_module_id`, `milestone_id`, `sprint_id`) | `set null` | The task survives; only its grouping is lost. |
| `task_type_id` | `restrict` | A type in use must not be removable. |
| Audit and actor columns | `set null` | Deleting a user must not delete their work. |
| `time_logs.project_id` / `task_id` | `set null` | Billing history outlives the task. |
| `resource_allocations.task_id` | `cascade` | A booking for a deleted task is meaningless. |

### 3.11 Index choices

Composite indexes are ordered by selectivity for the query that justifies them, rather than added per column.

| Index | Query it serves |
| --- | --- |
| `projects(team_id, status, end_date)` | The team's project list, filtered by status, ordered by deadline. |
| `tasks(project_id, status, priority)` | A project board grouped by status. |
| `tasks(project_id, project_module_id, position)` | A module's tasks in board order. |
| `tasks(status, due_at)` | Cross-project overdue sweeps and digests. |
| `tasks(sprint_id, status)` | Sprint burndown. |
| `task_assignments(user_id, status)` | "My open work" across every project. |
| `time_logs(user_id, logged_on)` | A user's timesheet for a period. |
| `git_commits(author_id, committed_at)` | Per-developer commit history. |
| `git_commits(git_repository_id, committed_at)` | Repository push history. |
| `git_events(status, occurred_at)` | The unprocessed-event queue. |
| `todo_items(assigned_to, is_completed, due_at)` | "My to-dos due soon." |

`project_progress_snapshots` names its unique key explicitly (`progress_snapshots_project_sprint_date_unique`) because the generated name would have been 66 characters, exceeding the MySQL identifier limit of 64. This was caught by running the migration rather than by reading it.

**Query-plan review against realistic volumes.** Run with `EXPLAIN` against `database/seeders/ProfilingSeeder.php`'s data (151 users, 30 projects, 1,200 tasks, ~3,300 time logs, 180 resource allocations, one task assignment per task) — not production traffic, but enough rows for MySQL's optimizer to make a real cost-based choice rather than trivially picking any index on an near-empty table:

- `projects(team_id, status, end_date)`, `tasks(project_id, status, priority)`, `task_assignments(user_id, status)`, `resource_allocations(user_id, starts_on, ends_on)`, `time_off_requests(user_id, starts_on, ends_on)` all get used exactly as intended (`EXPLAIN` shows `range`/`ref` access on the composite key, not a table scan).
- `tasks(status, due_at)` — the index exists, but MySQL does **not** use it for the actual query that justifies it, `Task::overdue()` (`whereNotIn('status', [Done, Cancelled])->whereNotNull('due_at')->where('due_at', '<', now())`). At realistic volume this WHERE clause matches most of the table (5 of 7 statuses are "open"; a sweep is inherently a low-selectivity, most-of-the-table query by its own nature), so a full table scan is the objectively cheaper plan — confirmed by forcing the index (still ~78% of rows, plus a filesort) and by trying the opposite column order `(due_at, status)` (still a full scan). This isn't a broken index — a "sweep or digest" query is never going to be selective — but it does mean the index's real justification is a *narrower*, single- or few-status equality query (e.g. `status = 'in_progress' AND due_at < now()`, confirmed to correctly use `range` access), not the broad `Task::overdue()` scope DESIGN.md originally cited it for.
- `time_logs(user_id, logged_on)` — MySQL picks whichever of `(user_id, logged_on)` or `(user_id, ended_at)` it happens to prefer, using only the shared `user_id` prefix either way (`key_len` matches a single `bigint` column) and filtering the date range as a post-fetch condition rather than an index bound. At this data's per-user row count (~40 logs/user) both plans are equally cheap, so this is noise, not a defect — it would be worth re-checking at a materially higher per-user log volume (thousands of entries/user) where an actual index range bound on `logged_on` would start to matter.
- Not profiled: `tasks(project_id, project_module_id, position)`, `tasks(sprint_id, status)`, `todo_items(assigned_to, is_completed, due_at)` (the seeder doesn't populate `project_modules`, `sprints`, or `todo_items` — out of scope for this pass) and the three `git_*` indexes (Phase 5 isn't built; there are zero rows and no query to run).

## 4. Representative Queries

**Tasks assigned to a user across all projects, overdue first**

```php
Task::query()
    ->assignedTo($user)
    ->with(['project:id,code,name', 'taskType:id,name'])
    ->withCount('subtasks')
    ->open()
    ->orderByRaw('due_at IS NULL, due_at')
    ->paginate();
```

**A user's free hours for a week**

```php
$capacity = $user->workSchedules()->effectiveOn($monday)->sum('capacity_hours');
$occupied = $user->resourceAllocations()->reservingBetween($monday, $friday)->sum('hours_per_day');
$away     = $user->timeOffRequests()->approvedBetween($monday, $friday)->sum('total_hours');
```

**Commit history for a project**

```php
GitCommit::query()
    ->whereRelation('repository', 'project_id', $project->id)
    ->committedBetween($from, $to)
    ->with(['author:id,name', 'tasks:id,number,title'])
    ->latest('committed_at')
    ->paginate();
```

**Open action items from a project's meetings**

```php
TodoItem::query()
    ->where('is_completed', false)
    ->whereRelation('list', fn ($list) => $list
        ->where('type', TodoListType::MeetingActions->value)
        ->where('project_id', $project->id))
    ->with(['assignee:id,name', 'list.meeting:id,title,scheduled_start'])
    ->orderBy('due_at')
    ->get();
```

## 5. Known Limitations

| Limitation | Rationale | Mitigation |
| --- | --- | --- |
| `todo_lists` has four nullable context keys | A single to-do engine is worth more than strict column-level typing. | Enforce the `type`-to-context rules in a form request and a model observer. |
| `git_pull_requests.task_id` is a single link | A pull request almost always delivers one task. | Add a `git_pull_request_task` pivot if that stops being true. |
| `tasks.logged_hours` can drift from `time_logs` | Reading the aggregate on every board render is too expensive. | One writer only, plus a scheduled reconciliation command. |
| No database-level check that a task's module belongs to the task's project | MySQL check constraints on cross-table state are not available. | Validate in the form request; cover with a feature test. |
| `task_dependencies` permits a cycle | Cycle detection is not expressible as a constraint. | Detect on write in the action that creates the dependency. |
| Comment and attachment polymorphs have no foreign keys | The cost of polymorphism. | Clean up in the deleting hooks of each commentable model. |

## 6. Verification Performed

- All 22 migrations run clean on MySQL 8, and `migrate:rollback` unwinds all of them.
- Rolling back exposed a pre-existing bug in `2026_09_18_091312_alter_users_tabel_add_new_columns`: `down()` dropped `created_by`, `updated_by` and `deleted_by` with `dropColumn()`, which MySQL refuses while the self-referencing foreign keys still exist. Changed to `dropConstrainedForeignId()`.
- 24 feature tests cover task numbering, multi-project and multi-user assignment, the assignment role uniqueness constraint, the `open`, `overdue`, `assignedTo`, `active` and `forMember` scopes, availability overlap arithmetic, commit-to-task linking, SHA uniqueness per repository, and encryption of repository access tokens.
- Larastan level 7 reports no errors. Pint reports no changes.
