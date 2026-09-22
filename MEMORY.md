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

## 2026-09-21 — Phase 1 Projects vertical slice

### What changed

Built the first Phase 1 vertical: `App\Policies\OMS\ProjectPolicy`, `App\Http\Requests\OMS\SaveProjectRequest`, `App\Actions\OMS\CreateProject`, `App\Http\Controllers\OMS\ProjectController` (index, store, show, update, destroy, archive), `Project::toListArray()`/`toDetailArray()`, and the matching React pages (`resources/js/pages/projects/index.tsx`, `show.tsx`) and modal components. Decisions behind the authorization and single-page UX shape are recorded as ADR-008 through ADR-013 in `docs/architecture-decisions.md`; do not re-derive them from scratch.

### Trap: `now()` returns `CarbonImmutable`, but every model's `@property` docblock says `Carbon`

`AppServiceProvider::configureDefaults()` calls `Date::use(CarbonImmutable::class)`. This makes the global `now()` helper — and Eloquent's `'datetime'` cast on every model — return `Carbon\CarbonImmutable`. Every model in this codebase (`Project`, `Task`, `ProjectMember`, etc.) nonetheless declares its date/datetime properties as `Illuminate\Support\Carbon` (mutable) in its `@property` docblock, which Larastan level 7 relies on.

This is latent and harmless as long as nothing explicitly assigns `now()` to one of those properties — reading a cast value or filling from a validated date string never triggers a type check. It surfaces the moment code does `$model->some_datetime_column = now();`: Larastan reports `assign.propertyType` because `CarbonImmutable` doesn't satisfy the declared `Carbon` type.

**The fix is not a docblock change or a suppression.** `Illuminate\Support\Carbon::now()` is unaffected by `Date::use()` and returns a real, mutable `Illuminate\Support\Carbon` matching every model's existing docblock. Verified in tinker: `get_class(now())` is `CarbonImmutable`; `get_class(\Illuminate\Support\Carbon::now())` is `Carbon`. **When assigning "now" directly to a model property (e.g. `archived_at`), import and call `Carbon::now()` explicitly — never the bare `now()` helper.** This was hit in `ProjectController::archive()`.

### Trap: don't start a second dev server pair

This machine's dev environment (`php artisan serve` + `vite dev`) is normally already running before an agent session starts — check `ps aux | grep -E "artisan serve|vite"` before launching your own. A duplicate `php artisan serve` on another port works but is pointless; a duplicate `vite dev` competes for port 5173 and just shifts to 5174, which is easy to miss and leads to testing against the wrong Vite instance. Use the existing server (default `http://127.0.0.1:8000`) instead of starting new ones.

### Trap: `php artisan wayfinder:generate` alone drops `.form` bindings

Running `php artisan wayfinder:generate` directly (e.g. after adding a new controller, to get its TS routes before a full frontend build) regenerates **every** route/action file in `resources/js/routes` and `resources/js/actions` — not just the new controller's — and it does so **without** the `formVariants: true` option. That option is only passed by `vite.config.ts`'s `wayfinder({ formVariants: true })` plugin call, so it only applies when Vite itself triggers generation (`npm run dev` or `npm run build`). The raw artisan command silently strips the `.form` property every existing page that uses Inertia's `<Form>` component depends on (`login.tsx`, `register.tsx`, `profile.tsx`, `security.tsx`, `two-factor-*`, `teams/edit.tsx`, and others), breaking `npx tsc --noEmit` across the whole app.

**After running `php artisan wayfinder:generate` directly, always follow it with `npm run build` (or let `npm run dev` pick up the change) before trusting `tsc` output** — the Vite-triggered regeneration restores the `.form` variants. Do not "fix" the resulting TS errors by editing the affected pages; they're consequences of a stale generation, not a real regression.

### Trap: `Gate::authorize('create', $parentModel)` resolves the parent's policy, not the child's

`TaskController::store()` originally called `Gate::authorize('create', $project)` intending to invoke `TaskPolicy::create(User $user, Project $project)` — a task doesn't exist yet, so the only context available is its parent project. Laravel's policy resolution is based on the **actual class of the value passed**, not on which policy method signature you have in mind: passing a `Project` instance resolves to `ProjectPolicy::create`, which has a different signature (`create(User $user, Team $team)`), and blew up with a `TypeError` on the second argument — caught immediately by the feature test, not by static analysis.

**When authorizing a "create" ability for a model that doesn't exist yet, using its parent as context, always use the explicit two-element array form: `Gate::authorize('create', [Task::class, $project])`.** This tells Laravel which policy to resolve (`Task::class`) independently of what type of object you're passing as context. This is exactly the pattern `ProjectController::store()` already used for `Gate::authorize('create', [Project::class, $current_team])` — the trap is forgetting to do the same one level down, where the child's controller is tempted to just pass the parent object directly since that's all it has in scope.

### Trap: every optional-relation `<Select>` sends the literal string `"none"`, not empty

Every optional-relation picker in this codebase's forms (`ModuleForm`'s parent module, `TaskForm`'s module and parent task) defaults its `useHttp` field to the string `'none'` rather than `''` or `null`, because shadcn's `<Select>` (built on Radix) cannot represent an empty string as an item value — `SelectItem value=""` is invalid. The corresponding `SaveXRequest::prepareForValidation()` **must** explicitly normalize `'none'` (and, separately, `''` for text inputs) to `null` before validation runs, or a plain `exists`/`nullable` rule rejects it and every "create" submission that doesn't touch that field 422s silently.

This was missed once already: `SaveTaskRequest` normalized `project_module_id` but not `parent_id` when both were added in the same request class, and it went undetected by 14 passing feature tests because every test constructed its request payload directly in PHP (omitting the untouched field entirely, which validates as absent/nullable) rather than reproducing the literal string the JS form actually sends. **When a form field defaults to a placeholder sentinel value, write at least one feature test that sends that exact sentinel string, not just tests that omit the field** — that's the only way to catch this class of bug before it reaches the browser. Caught here only by manually driving the real form in a browser and watching the modal fail to close.

### Verifying UI changes without Playwright pre-installed

No browser automation tool is installed in this environment, but `npx --yes -p playwright@1.48 node script.js` works without touching `package.json` (temp install via npx cache). `npx playwright install --with-deps` fails (needs `sudo`, no password available) — instead launch with `{ channel: 'chrome', args: ['--no-sandbox'] }` to drive the system's already-installed `/usr/bin/google-chrome` directly, no browser download needed. `page.waitForLoadState('networkidle')` is **not** a reliable sync point after clicking an Inertia `<Link>` or a `router.visit()` call — Inertia's SPA transitions don't behave like a traditional navigation, so screenshots taken right after can catch a mid-transition frame. Prefer `page.waitForSelector(...)` for an element that only appears in the destination state.

---

## 2026-09-21 — Phase 2 Activity feed

### Trap: a partial `router.reload({ only: [...] })` silently starves any prop left out of the list

`resources/js/pages/projects/show.tsx` gives every mutating tab (Board, Modules, Members, Milestones, Sprints) its own `onChanged={() => router.reload({ only: [...] })}` callback, so creating a task only re-fetches `tasks`, creating a module only re-fetches `modules`, and so on — deliberately, to avoid a full-page reload on every modal save. When the `activities` prop (the Activity tab's feed) was added, it wasn't added to any of those `only` lists, so newly created modules/tasks/members never showed up in the Activity tab until the whole page was reloaded — even though the underlying `activities` row was written correctly and a direct visit to the page showed it immediately. This is invisible to the PHP test suite (it only tests that the row is written, and that the Inertia payload contains it on a fresh `GET`), and only surfaced by actually creating things in the browser and switching to the Activity tab afterward. **Whenever a new prop is added to a page that already uses selective partial reloads, check every existing `only: [...]` array on that page for whether the new prop should be included, not just the one for the feature being built.** This recurred immediately with the `cycleTime` prop added for the cycle-time report below — moving a task's status on the Board only reloaded `['tasks', 'activities']`, so the Overview tab's new Cycle Time card stayed stale until `'cycleTime'` was added to that same call too.

### Scoping decision: model observers only for `created`/`deleted`, direct `RecordActivity` calls for everything with real before/after semantics

`RecordActivity` (`App\Actions\OMS\RecordActivity`) is the single writer for the `activities` table. `App\Observers\OMS\*` (registered in `AppServiceProvider::boot()`) call it from `created()`/`deleted()` on `Project`, `ProjectModule`, `ProjectMember`, `Task`, `Milestone` and `Sprint` — but deliberately have no `updated()` hook, because a generic dirty-attribute diff can't produce an accurate human-readable description ("Task moved from Backlog to In Progress" vs. a dozen other things `updated()` could mean). Events with real narrative content instead get a direct `RecordActivity` call from the action/controller that already knows the story: `ChangeTaskStatus`, `AssignTask::assign()`/`unassign()`, `AddTaskDependency`, `TaskDependencyController::destroy()`, `ProjectController::archive()`.

---

## 2026-09-21 — Phase 2 Cycle-time report

### Trap: a model with `$timestamps = false` gets its "timestamp" column from MySQL's clock, not PHP's — and the two can disagree

`TaskStatusHistory` sets `public $timestamps = false;` (it manages its own `changed_at` column instead of Eloquent's usual `created_at`/`updated_at`), and the migration gives that column `->useCurrent()` as a DB-level default. Every other append-only model in this app (`Activity`, `TaskDependency`) instead leaves `$timestamps` at its default `true` and only overrides `const UPDATED_AT = null` — which means Eloquent still stamps their `created_at` itself, in PHP, using `config('app.timezone')` (`UTC` here). `TaskStatusHistory` is the one exception, so when a row was inserted without explicitly passing `changed_at`, MySQL's own `CURRENT_TIMESTAMP` filled it in — using the **database server's** clock, not the app's. In this environment those two clocks disagree by 6 hours (`SELECT NOW()` on MySQL vs. PHP's `now()`), because MySQL's session time zone is `SYSTEM` rather than UTC. This was invisible until `ChangeTaskStatus` started computing `duration_minutes` (minutes since the previous status change) by diffing a freshly-fetched `changed_at` against `Carbon::now()`: the DB-stamped timestamp came back 6 hours "ahead" of PHP's clock, producing a negative minute count that overflowed the `unsignedInteger` column and 500'd on every status change. Fixed by explicitly setting `'changed_at' => Carbon::now()` on every insert in `ChangeTaskStatus`, so the column is always PHP-stamped like every other timestamp in the app, never left to the database's default. **A `$timestamps = false` model's manually-managed date column is a silent trap: unless every write path explicitly sets it in PHP, it can end up on a different clock than the rest of the app, and the mismatch stays invisible until something finally does arithmetic on it.** Caught only by driving real status changes through the browser and reading the 500 out of `storage/logs/laravel.log` — the PHP test suite never triggers it because `RefreshDatabase`'s test transaction and `Carbon::setTestNow()` keep both clocks in the test process, with no real MySQL `CURRENT_TIMESTAMP` fallback ever exercised.

### `task_status_histories.duration_minutes` was schema-only until this step

The column existed since the original migration but nothing wrote to it. `ChangeTaskStatus` now populates it on every transition, as the minutes spent in the status being *left* (keyed by `from_status`, not `to_status`) — the source for the cycle-time report's "average time spent per status" breakdown.

---

## 2026-09-21 — Phase 3 To-Do Lists

### Test-writing trap: `$team->members()->attach($user, [...])` does not change `$user->currentTeam`

Attaching a second user to a team (`$owner->currentTeam->members()->attach($member, ['role' => ...])`) makes `$member` a member of that team, but does **not** touch `$member->current_team_id` — `$member` still has their own separate team from `User::factory()->create()`'s `afterCreating` hook, and `$member->currentTeam` keeps pointing at that unrelated team. A test that builds a route with `route($name, ['current_team' => $member->currentTeam->slug, ...])` after attaching `$member` to `$owner`'s team is silently building a URL for the *wrong* team — `EnsureTeamMembership` still passes (the member does belong to their own team), but the resource under test (a project, a checklist, anything scoped to `$owner`'s team) 404s there, since it doesn't exist on `$member`'s own team. The failure looks exactly like an authorization test working correctly (a non-owner being denied), so a `404` where you expected `403` is easy to misread as "the policy correctly rejected this" rather than "the request never reached the policy — it 404'd on the team-scoping check first." **When a test attaches a second user to the primary user's team to check cross-user authorization, always build that second user's request URL with the primary user's team explicitly (`itemRoute($member, $name, $resource, null, $owner->currentTeam)`), never by defaulting to `$member->currentTeam`.**

### `AssertableInertia`/`AssertableJson`'s `where()` accepts a closure, not just a literal value

`->where('lists.0.id', $ownList->id)` is brittle whenever a test can't control or predict exact array ordering (e.g. `TodoListController::index()` now returns the user's hand-written lists interleaved with an auto-generated one, and MySQL's tie-break for equal `created_at` values isn't something a test should depend on). Rather than hand-rolling ordering assumptions, `where($key, $expected)` accepts `$expected` as a `Closure`, invoked with the actual value and asserted truthy: `->where('lists', fn (\Illuminate\Support\Collection $lists): bool => $lists->pluck('id')->contains($ownList->id))`. Prefer this whenever asserting "this collection contains X" rather than "item at this exact position is X."

### Design decision: reuse the generic `TodoItemController` for task checklists, no new routes

A task's checklist is just a `TodoList` with `type=task_checklist` and `task_id` set instead of `owner_id` — `TodoItemController`'s store/update/toggle/destroy routes only ever look at which `TodoList` an item belongs to, never its `type`, so the exact same `{current_team}/todo-lists/{todo_list}/items/...` routes built for personal lists work unchanged for checklists. The only code that had to change was `TodoListPolicy::view`/`update`, which now also authorizes any active member of the checklist's project (checklist work is collaborative, not personal — unlike a private to-do list, there's no `owner_id` to check against). The checklist itself is get-or-created lazily, the first time `TaskController::show()` runs for that task (`GetOrCreateTaskChecklist`), not at task-creation time — so a task nobody ever checklists never accumulates an empty, unused `todo_lists` row.

---

## 2026-09-22 — Phase 4 Meetings

### Another instance of the DB-default-vs-PHP-set trap: a column with an enum cast and no explicit PHP value throws, not just goes stale

`MeetingController::store()` built a new `Meeting` without setting `status`, relying on the column's `->default('scheduled')` the same way `ChangeTaskStatus` used to rely on `changed_at`'s `->useCurrent()` (see the Phase 2 cycle-time entry above). The failure mode is worse here: `changed_at` is a plain datetime, so the stale value just silently held the wrong clock until something did arithmetic on it. `status` is cast to `MeetingStatus` (a backed enum), and Eloquent has no in-memory value for an attribute the DB default fills in only at INSERT time — so `$meeting->status` returns `null`, and the very next line that reads `$meeting->status->value` (inside `toListArray()`, called immediately by the create response) throws `Attempt to read property "value" on null`, a 500 on every single meeting creation. Fixed by setting `$meeting->status = MeetingStatus::Scheduled;` explicitly before `save()`. **Any column with a DB-level default that a model also casts to an enum must have that default mirrored in PHP at creation, not left to MySQL — for a plain scalar column this merely goes stale until read back from a fresh query, but for an enum-cast column it's an immediate crash on the same request that created the row.**

### Trap: a restricted `->get([...])` column list breaks a lazy-loaded relation used later in the same request

`MeetingController::show()` built the agenda's task-link options with `Task::query()->where('project_id', ...)->get(['id', 'number', 'title', 'status'])`, then called `$task->reference()` on each row — which internally does `$this->project->code`. Restricting the `get()` column list to only what looked needed silently excluded `project_id`, the foreign key `Task::project()` (a `belongsTo`) needs to lazy-load its target; with it missing, `$task->project` resolved to `null` and `->code` on that threw `Attempt to read property "code" on null`. This is invisible in the PHP test suite unless a test specifically visits the page with a project-linked meeting that has real tasks — none of the `MeetingControllerTest`/`MeetingAgendaItemControllerTest` tests written alongside `MeetingController::store()`/`MeetingAgendaItemController` happened to do that, since they call the agenda-item endpoints directly rather than rendering the workspace page first. Caught only by a live-browser walkthrough; fixed by using the already-eager-loaded `$meeting->project->code` directly instead of the row's own lazy relation, which is also one fewer N+1 query. **Whenever a `->get([...])`/`->select([...])` column list is trimmed for a query, check every relation and computed method (like `reference()`) that will be called on the resulting rows later in the same request — trimming a column that isn't obviously used in the immediate `map()` can still be a foreign key a lazy-loaded relation silently needs.**

### Trap: a single-sided "swap" (copy the target's position onto the moved item) can silently fail to reorder anything

The established move-up/move-down pattern in this app (`kanban-board.tsx`'s `moveWithinColumn`, copied when building the agenda's reorder buttons) sets only the *moved* item's `position` to the *target*'s current position, leaving the target's own position untouched. When the two end up tied at the same value, which one sorts first under a plain `orderBy('position')` is whatever the database's tie-break happens to be (in practice, ascending by primary key on this dataset) — so moving item B "up" past item A (a lower id) sets both to the same position, and the list renders completely unchanged, because A's lower id still wins the tie. This reproduced immediately in the very first live-browser check of the new agenda reorder buttons (two items, moving the second one up did nothing visible) — DB inspection confirmed both rows shared `position: 1`. Fixed **for the agenda builder only** by making the move a genuine two-sided swap: two sequential requests (second one fired from the first's `onSuccess`, reusing the same `useHttp` form instance safely since they can't race), each item taking the other's *original* position. **Task and Module reordering elsewhere in this app use the original single-sided version and most likely have the same latent bug** — not fixed here, since fixing an already-shipped, previously-verified feature was out of scope for the task at hand, but worth knowing before trusting "move up/down" anywhere in this codebase looks right just because it compiled and no test caught it.

---

## 2026-09-23 — Phase 6 Availability and Time

### Trap: `nullable` silently swallows `required_if` (and every other non-implicit rule) whenever the wildcard comparison value doesn't strictly match

Building `SaveUserWorkScheduleRequest`'s per-day validation, `'days.*.start_time' => ['nullable', 'date_format:H:i', 'required_if:days.*.is_working_day,1']` looked correct and even passed a first manual read-through, but a work-day row submitted with `start_time: null` sailed through validation uncaught. Two compounding issues: first, Laravel's `required_if` parameter values only auto-convert the literal strings `'true'`/`'false'` to real booleans (`convertValuesToBoolean`) — the parameter `1` stays the string `'1'`, and since the sibling `is_working_day` field is itself `boolean`-cast (so its resolved value is a real PHP `true`), the comparison `in_array(true, ['1'], strict: true)` fails, because `required_if`'s strict-mode flag is derived from `is_bool($other)`. **The fix is `required_if:days.*.is_working_day,true` (the literal string `true`), never `,1`, whenever the referenced sibling field is validated as `boolean`.** Second and more subtly: even with that fixed, a value of exactly `null` combined with a `nullable` rule on the *same* field makes Laravel skip every other non-implicit rule for that field entirely (`Validator::isNotNullIfMarkedAsNullable`) — `required_if` only survives this because it's registered as one of Laravel's `$implicitRules`, so this second trap didn't end up biting here, but it's the reason the fix must be verified by an actual failing-then-passing `Validator::make(...)->fails()` check in tinker, not by reasoning about the rule array — nested wildcard cross-references are easy to get subtly wrong in a way that fails open (validation silently passes) rather than throwing, so nothing short of actually running the validator against a bad payload catches it.

### Trap: formatting a `datetime-local` field's value to minute precision can make an untouched edit form fail its own "end after start" validation

`TimeLogForm`'s edit mode pre-fills `started_at`/`ended_at` from the entry's actual timestamps via a `toLocalInput()` helper that originally formatted down to `HH:MM`, dropping seconds — reasonable, since the `<input type="datetime-local">` picker only displays minute granularity anyway. This broke the very first live check: editing a timer entry that had run for under a minute (`started_at` and `ended_at` a few seconds apart) opened the edit modal with both fields showing the *same* minute, and submitting without touching either field re-sent two identical timestamps, which `SaveTimeLogRequest`'s `after:started_at` rule correctly rejected — a 422 on a form the user never touched. The fix is to keep seconds in the formatted value (`HH:MM:SS`) even though the picker's UI never shows them: browsers accept and preserve a `datetime-local` value with seconds regardless of the input's `step`, so an unedited field round-trips its exact original timestamp, and only a field the user actually interacts with gets truncated to the minute the browser's control offers. **Any time a duration this short is plausible (a stopped timer, not just a multi-hour manual entry), pre-filling a `datetime-local` edit field from a stored timestamp must preserve seconds, or two distinct instants can collide into the same displayed value and break validation on a save the user never intended to change.**

### A nonexistent `{current_team}` slug 404s, not 403 — implicit route-model binding runs before `EnsureTeamMembership`

Writing a dedicated test for `EnsureTeamMembership` (previously untested directly, only exercised incidentally through every feature test), a bad team slug was expected to produce the middleware's own 403 ("you don't belong to this team"). It actually 404s. Every controller in this app type-hints `Team $current_team` in its method signature, which triggers Laravel's implicit route-model binding (resolved by `Team::getRouteKeyName()` returning `slug`) — and that binding resolution happens before `EnsureTeamMembership::handle()` ever runs, so a slug matching no row throws Laravel's own `ModelNotFoundException` (404) without the middleware getting a chance to look at it at all. `EnsureTeamMembership::team()` does have its own `Team::where('slug', $team)->first()` fallback for when the route parameter is still a plain string, but that path is dead for every route in this app specifically because the implicit binding already resolved (or failed to resolve) it first. **A 403 from this middleware only ever means "the team exists and you're not on it" — a nonexistent team is always a 404, and a test (or any future debugging) expecting otherwise for a route with a `Team`-typed controller parameter will be surprised.**

### Trap: `Collection::push()` mutates the receiver in place, not just the returned value

`ProfilingSeeder` built a "members" collection via `User::factory(150)->create()`, then wrote `$allUsers = $members->push($owner);` intending to build a *separate* "everyone including the owner" list while keeping `$members` as just the 150 plain members for a later bulk `team_members` insert. `Collection::push()` appends to `$this->items` and returns `$this` — the same instance — so `$members` and `$allUsers` became the same 151-item collection immediately; the later `$members->map(...)` (meant to build 150 membership rows) silently produced 151, including a duplicate for the owner, who already had an explicit membership row inserted separately. The result wasn't a visibly wrong value anywhere in application logic — it surfaced only as a `UniqueConstraintViolationException` several lines later, on an unrelated-looking bulk insert. **Any Collection method whose name doesn't obviously say "new" (`push`, `prepend`, `shift`, `pop`, `forget`, `splice` — as opposed to `concat`, `merge`, `map`, `filter`, which return a new instance) should be assumed to mutate the receiver; use `concat()` when the goal is "the original plus one more, without touching the original."**

### Trap: `WithoutModelEvents` in a seeder silently breaks any action that depends on a model's `creating`/`saving` hook

`ProfilingSeeder` (needs `WithoutModelEvents` to skip the Activity-logging observers that would otherwise fire once per bulk-inserted row) called `App\Actions\Teams\CreateTeam::handle()`, the same action used by real registration flow, to set up its one demo team — exactly like several earlier tinker sessions in this project successfully did. It failed with "Field 'slug' doesn't have a default value", because `CreateTeam` calls `Team::create(['name' => ..., 'is_personal' => ...])` and relies on `Team`'s own `static::creating()` hook to generate `slug` — a hook `WithoutModelEvents` disables for the *entire* seeder run, not just the bulk-insert calls that motivated adding it. The trait's suppression is a global flag on Eloquent's event dispatcher for the duration of the closure, not scoped to specific models or specific `create()` calls. `Team::factory()->create([...])` worked fine in the same seeder because `TeamFactory::definition()` sets `slug` directly and never depends on the event at all — which is also why `User::factory()->create()` kept working throughout, since its `afterCreating` hook builds the personal team via `Team::factory()`, not `CreateTeam`. **In any context where model events are suppressed (a seeder using `WithoutModelEvents`, or any manual `Model::withoutEvents(...)` wrapper), never call an app-level create action that relies on a model's own `creating`/`saving` hook to fill in a required column — use the model's factory (if its `definition()` sets that column explicitly) or set the column explicitly by hand instead.**

## 2026-09-22 — Team Capacity heatmap, Design/UX pass

### Trap: `new Date(dateString).toISOString().slice(0, 10)` silently shifts the date in any browser timezone ahead of UTC

Building the Team Capacity heatmap's Previous/Next paging (`shiftDate` in `team-capacity/index.tsx`), the obvious way to add N days to a `YYYY-MM-DD` string was `new Date(`${date}T00:00:00`)`, `.setDate(d.getDate() + n)`, then `.toISOString().slice(0, 10)` to get a string back — the exact pattern already shipped in `timesheet/index.tsx`'s `shiftWeek`. Live-testing in a real browser (this sandbox's local timezone is UTC+6; the app runs in UTC) caught it immediately: clicking "Next" on a two-week window starting `2026-09-21` produced `from=2026-10-04` (13 days later, not 14), because `new Date(\`${date}T00:00:00\`)` parses the string as **local** midnight, but `.toISOString()` converts back to **UTC** — in any timezone ahead of UTC, local midnight is still the *previous* UTC day, so the round-trip silently loses a day. The same bug was already live in the shipped Timesheet page's Previous/Next week buttons, unnoticed because prior verification apparently never checked the actual date value after a click, only that navigation happened. **Fixed both by building the shifted date with `Date.UTC(year, month - 1, day + n)` from the string's own components instead of parsing-then-reading-back through local time** — this never touches the browser's local timezone, so it's correct regardless of where the browser runs. Also stopped independently deriving a date-range array from `from`/`to` on the client in the heatmap component itself (which had the identical bug in a loop) — the server already sends the authoritative per-day date strings on every member row, so the frontend now reads column dates from `members[0].days.map(d => d.date)` instead of recomputing them. **Any client-side "add N days to a YYYY-MM-DD string" logic in this codebase must use `Date.UTC(...)` from the parsed components, never `new Date(dateString)` followed by `.toISOString()`** — and it's worth grepping for `new Date(` + `.toISOString().slice(0, 10)` together before trusting any other date-math helper found by search, since this exact pattern is now known to exist more than once.

### Trap: incrementing a day-by-day loop with `$date->addDay()` alone silently never advances, because model date attributes are `CarbonImmutable`

Writing `DetectOverAllocatedBookings`'s per-day scan (RD.md FR-8.9), the first draft copied a `for` loop shape that reads correctly at a glance: `for ($date = $allocation->starts_on->copy(); $date->lte($allocation->ends_on); $date->addDay())`. Every model date attribute in this app is `CarbonImmutable` (the same `Date::use(CarbonImmutable::class)` default behind the `now()`-typehint trap logged in the Phase 1 entry above), and `CarbonImmutable::addDay()` — like every mutation method on an immutable class — returns a *new* instance and leaves the object it was called on completely unchanged. Used as a `for` loop's increment clause, `$date->addDay()`'s return value is simply discarded each pass, so `$date` never advances and `$date->lte($allocation->ends_on)` stays true forever: an infinite loop, not a wrong-but-terminating one. This particular instance was caught by code review before ever running (by noticing `CalculateUserAvailability::handle()`'s own day-by-day loop already gets this right — `for ($date = $from->copy(); $date->lte($to); $date = $date->copy()->addDay())`, reassigning `$date` in the increment rather than relying on mutation), not by a failing test — a type-checking bug earlier in the same method (passing `CarbonImmutable` where the `Carbon` typehint required conversion via `Carbon::parse()`) happened to throw before execution ever reached the loop, so the infinite loop was never actually exercised by the test suite that ran first. **Any day-by-day (or unit-by-unit) loop over a `CarbonImmutable` value must reassign in the increment clause — `$date = $date->copy()->addDay()`, never bare `$date->addDay()` — and this can't be caught by only running the tests once; if the increment clause silently doesn't advance, the tests either hang or never reach that code path at all, so the fix has to come from reading the loop, not from a red-then-green test run.**

## 2026-09-22 — Phase 7 Platform Administration (admin guard, RBAC)

### Trap: `Authenticate::redirectUsing()` / `RedirectIfAuthenticated::redirectUsing()` registered from a service provider's `boot()` works over a real HTTP request but silently loses under PHPUnit — because Laravel registers its *own* default from the same hook, at a point that lands on opposite sides of provider boot in the two contexts

Adding a second auth guard (`admin`, Phase 7) needed the guest/auth redirects to be guard-aware — an unauthenticated visit to `/admin/*` should go to `/admin/login`, not the regular `/login`, and vice versa. The obvious place felt like `AppServiceProvider::boot()`: `Authenticate::redirectUsing($closure); AuthenticationException::redirectUsing($closure);`. This worked flawlessly in a real browser (verified repeatedly with Playwright) and failed identically every time under PHPUnit — `/admin` while logged out redirected to `/login`, not `/admin/login` — with the registered closure demonstrably *never invoked* (confirmed by making it throw; the exception never surfaced). Root cause, found only by temporarily instrumenting the vendor middleware itself with `file_put_contents` debug lines (`Log::info` calls were being silently skipped too, until traced to the same cause) and reading `Illuminate\Foundation\Configuration\ApplicationBuilder::withMiddleware()`: that method registers Laravel's *own* default — `(new Middleware)->redirectGuestsTo(fn () => route('login'))` — itself, unconditionally, *inside* the very `$this->app->afterResolving(HttpKernel::class, function ($kernel) use ($callback) { ...; $callback($middleware); ... })` closure that then invokes `bootstrap/app.php`'s own `withMiddleware()` callback. `afterResolving(HttpKernel::class, ...)` fires whenever `HttpKernel::class` is first resolved from the container — in `public/index.php` for a real request, which happens *before* `$kernel->handle($request)` triggers the `BootProviders` bootstrapper (so a provider's `boot()`-time override runs *after* Laravel's default and wins), but under `Illuminate\Foundation\Testing\TestCase`'s bootstrap, the kernel is resolved *after* providers have already booted (so the same provider code runs *before* Laravel's default and silently loses). Both `Authenticate` and `AuthenticationException` have their own, separate `$redirectToCallback` static slots that must be kept in sync (`AuthenticationException`'s is a fallback used only when `Authenticate::unauthenticated()`'s own `expectsJson()` check short-circuits its call — an Inertia/JSON-Accept request skips `Authenticate`'s slot entirely), and `Middleware::redirectTo(guests: ..., users: ...)`/`redirectGuestsTo()` already register both together correctly — this is the fix, not a manual `Authenticate::redirectUsing()` call. **Any guard-mismatch redirect customization belongs inside `bootstrap/app.php`'s `->withMiddleware(function (Middleware $middleware) {...})` closure, using `$middleware->redirectTo(guests: ..., users: ...)` — never inside a service provider's `boot()`, however natural that placement feels — because that's the one place guaranteed to run *after* Laravel's own default registration in every context, HTTP and testing alike.** A closure placed there also must reproduce the original default (`route('login')` for guests) on its non-matching branch, not return `null` — `null` looks like "no override, fall through to the old behavior" but the base `Illuminate\Foundation\Exceptions\Handler::unauthenticated()` actually returns a bare `response()->noContent(401)` when the resolved redirect is falsy, not a redirect to `/login` at all; the `/login` fallback everyone is used to seeing *is* Laravel's injected default, not a handler-level default, and a naive override silently deletes it for every other guard.

### Trap: `useHttp().post()` against a controller that unconditionally redirects throws inside the hook before `onSuccess` ever runs — the "harmless console error" from an earlier verification pass was a real bug

Stage 7.4's live Playwright verification (create/edit/delete a role, create an admin account) kept timing out waiting for the new row to appear, even though the record was genuinely created in the database every time. Network inspection showed why: `useHttp()`'s `post()`/`patch()` is a plain `fetch()` call (`Accept: application/json`, no `X-Inertia` header), and when the controller responded with an unconditional `to_route(...)` redirect, the browser's `fetch()` followed that 302 itself and landed on the full HTML `admin.roles.index` page — which `useHttp` then tried to `.json()`-parse, throwing `SyntaxError: Unexpected token '<', "<!DOCTYPE "... is not valid JSON` as an uncaught promise rejection. `onSuccess` never fires, so nothing tells the SPA to refresh its list, and the row only appears after a manual reload. This is exactly the "`SyntaxError: ... is not valid JSON`" console error the prior Stage-7.3 verification pass dismissed as pagination-probing noise (logged in this file's now-superseded Phase 7 entry) — it was not noise, it was this bug, present in `Admin\TeamController::store()`/`assignLeader()` since Stage 7.3 and then copied into `Admin\RoleController` and `Admin\AdminController` for Stage 7.4. Fixed all three by branching on `$request->header('X-Inertia')`: a real Inertia visit (guest fallback, no-JS) still gets the flash-toast-and-redirect; every other caller — i.e. every `useHttp()` form in this app — gets a plain `response()->json(['message' => ...])`, matching the dual-response convention already noted below (`Setup\TaskTypeController` / `UserWorkScheduleController::store()` are the pre-existing references; this was simply not applied consistently to the three new admin controllers on first pass). The frontend side also needed `router.reload({ only: [...] })` inside each `onSuccess` — `useHttp`'s own fetch never updates Inertia's page props, only a real Inertia visit (`router.*`, or a classic Inertia-visit redirect) does, so a JSON-returning endpoint still needs an explicit reload to make the change visible without a manual refresh. **Any new form built with `useHttp()` must be checked end-to-end in a real browser for the row/list actually updating after submit, not just that the record landed in the database — a passing PHPUnit feature test and a "successful" `onSuccess` callback are both fully consistent with this bug being present, since neither observes the live DOM.**

### Environment gap: `pdo_sqlite` is not installed in this sandbox, so `php artisan test` fails every test with "could not find driver" — run against MySQL instead by overriding env vars, not by editing `phpunit.xml`

`phpunit.xml` hard-codes `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` (no `force="true"` on those `<env>` tags), and this container has no `pdo_sqlite` extension and no passwordless `sudo` to install one. PHPUnit's `<env>` only sets a variable when it isn't already present in the process environment, so exporting the DB vars before invoking the test runner overrides it cleanly without touching version-controlled config: `DB_CONNECTION=mysql DB_SOCKET=/var/run/mysqld/mysqld.sock DB_DATABASE=project_manager_test DB_HOST= DB_PORT= php artisan test` (the empty `DB_HOST=`/`DB_PORT=` are required — otherwise Laravel's mysql connector prefers host/port over the socket). The `project_manager_test` database itself doesn't pre-exist and must be created once per environment reset (`mysql -u root -ppassword -S /var/run/mysqld/mysqld.sock -e "CREATE DATABASE IF NOT EXISTS project_manager_test;"`) — `RefreshDatabase` migrates it fresh on first use, so nothing beyond the empty schema is needed going in.

### The `admins`/`roles`/`permissions` RBAC is deliberately scoped, not a replacement for `TeamRole`/`TeamPermission`

Confirmed with the user before building anything: Phase 7's "role permission module" governs only the new admin-panel process (team creation, team-leader assignment, admin-account/role management) — it does not touch the existing enum-based `TeamRole`/`TeamPermission` system that already powers every OMS feature built in Phases 1-6. A `belongsToMany` RBAC (`admins`↔`roles`↔`permissions`, both pivots many-to-many) was built fresh rather than trying to unify it with the team-level enums, specifically to keep the blast radius contained — unifying them would have meant touching the authorization surface of nearly every existing controller for a request that only asked for a scoped provisioning workflow.

---

## Conventions Worth Remembering

- Controllers respond twice: Inertia redirect with a flashed toast for page visits, JSON for modal forms. `Setup\TaskTypeController` is the reference.
- Frontend payloads come from explicit methods such as `toSetupArray()`, never from serialising a model.
- `App\Enums\Concerns\HasOptions` supplies `label()`, `options()` and `values()`. Override `label()` only where `Str::headline()` is wrong: `ReadyForQa` → "Ready for QA", `GitHub`, `QaEngineer`, `DevOps`, `Todo` → "To Do".
- `App\Models\Concerns\HasAuditUsers` supplies `creator`, `updater` and `deleter`.
- `App\Concerns\HasProjectWork` gives `User` its cross-project work and capacity relationships, mirroring how `HasTeams` is organised.
- The task display reference is computed by `Task::reference()` as `{project.code}-{number}`. It is not stored.
