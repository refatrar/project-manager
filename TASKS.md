# Tasks

Delivery backlog. Requirement identifiers refer to `RD.md`.

Status key: `[x]` done, `[~]` in progress, `[ ]` not started.

---

## Phase 0 — Data Layer

### 0.1 Schema
- [x] Add `team_id` to `projects` and scope it to the tenant (FR-1.1)
- [x] Add `code`, `slug`, priority, health, budget and rollup columns to `projects` (FR-1.2, FR-1.4)
- [x] Rename `project_modules.project_module_id` to `parent_id`, add `position` and dates (FR-1.3)
- [x] Key and constrain the `project_module_scope` pivot
- [x] Add a unique constraint and capacity columns to `project_members` (FR-1.6, FR-8.3)
- [x] Replace `project_module_tasks` with `tasks`, with `project_id` required and module optional (FR-2.1)
- [x] Split the conflated task status into `status`, `review_status` and `deployment_stage` (FR-2.3)
- [x] Add `task_assignments` with roles, per-assignment status and allocated hours (FR-3.1 – FR-3.6)
- [x] Add `task_dependencies` (FR-2.2)
- [x] Add `task_status_histories` (FR-2.4)
- [x] Add `milestones` and `sprints` (FR-2.6)
- [x] Add `labels` and the `label_task` pivot (FR-2.6)
- [x] Add `meetings`, `meeting_attendees`, `meeting_agenda_items` (FR-6.1 – FR-6.4)
- [x] Add `todo_lists` and `todo_items` covering personal, daily, checklist and meeting-action use (FR-5.1 – FR-5.6)
- [x] Add the Git tables: identities, repositories, branches, commits, commit-task pivot, pull requests, events (FR-7.1 – FR-7.8)
- [x] Add `user_work_schedules`, `time_off_requests`, `resource_allocations`, `time_logs` (FR-8.1 – FR-8.7)
- [x] Add `comments`, `comment_mentions`, `attachments`, `activities` (FR-9.1 – FR-9.3)
- [x] Add `project_progress_snapshots` (FR-4.2)
- [x] Fix the pre-existing `down()` in `2026_09_18_091312_alter_users_tabel_add_new_columns`

### 0.2 Models and enums
- [x] Create the 31 domain enums with the shared `HasOptions` trait
- [x] Create the `HasAuditUsers` model concern
- [x] Create the `HasProjectWork` user concern and wire it into `User`
- [x] Create all delivery, meeting, to-do, time and collaboration models with typed relationships and casts
- [x] Create the Git models, with `encrypted` casts on the token and webhook secret

### 0.3 Factories and verification
- [x] Factories for the 17 models that tests need, with meaningful states
- [x] Verify every migration runs and rolls back on MySQL 8
- [x] Feature tests for task numbering, assignment, scopes, availability arithmetic and Git linking
- [x] Larastan level 7 clean, Pint clean

### 0.4 Remaining data-layer work
- [x] **Apply the schema to the development database.** Confirmed via `migrate:status` against `project_manager`: all 21 migrations have run.
- [ ] Drop the scratch verification database when no longer needed: `DROP DATABASE pm_schema_check;` (confirmed still present as of this audit)
- [ ] Install `php8.3-sqlite3` so the configured test connection works and all 110 pre-existing tests can run
- [ ] Factories for the remaining models: `TaskDependency`, `TaskStatusHistory`, `ProjectProgressSnapshot`, `MeetingAttendee`, `MeetingAgendaItem`, `Comment`, `Attachment`, `Activity`, `GitBranch`, `GitPullRequest`, `GitEvent`, `GitIdentity`
- [ ] A demo seeder that builds one team with two projects, members, a sprint, tasks, assignments, time logs, a meeting with minutes, and a connected repository
- [ ] A `LabelSeeder` for default team labels, invoked when a team is created rather than globally (labels are team-scoped)
- [ ] Model observers enforcing the invariants the database cannot: `todo_lists.type`-to-context rules, module belongs to the task's project, no dependency cycles

---

## Phase 1 — Project and Task Management

### 1.1 Setup screens
- [ ] `Setup\LabelController` with index, store, update and destroy, following the `TaskTypeController` pattern
- [ ] Label management page, form modal and delete modal
- [ ] `Label` TypeScript types in `resources/js/types/setup.ts`

### 1.2 Projects
- [ ] `ProjectPolicy` deriving permissions from the team role and `ProjectMemberRole`, per `docs/architecture-decisions.md` ADR-008 and ADR-011
- [ ] `OMS\ProjectController` index, store, update, destroy, archive — route with `->except(['create', 'edit'])`; `store`/`update` return JSON for a modal per ADR-013, `show` renders the project detail shell
- [ ] `CreateProject` action allocating `code` and `slug` and adding the creator as owner
- [ ] Project list page with status, priority and health filters
- [ ] Project detail shell with tabs for overview, board, modules, members and activity — a single page; creating or editing anything below (modules, members, tasks, milestones, sprints) happens in a modal on this page, never a separate route (ADR-013)

### 1.3 Modules
- [ ] `OMS\ProjectModuleController` with nesting and reordering — route with `->except(['create', 'show', 'edit'])`, modal-driven (ADR-013)
- [ ] Module tree component with drag-to-reorder writing `position`, create/edit via modal on the project detail page

### 1.4 Members
- [ ] `OMS\ProjectMemberController`, restoring a soft-deleted membership rather than inserting a duplicate — route with `->except(['create', 'show', 'edit'])`, modal-driven (ADR-013)
- [ ] Member management surface (modal/panel on the project detail page, not a separate page) with role and allocation editing

### 1.5 Tasks
- [ ] `TaskPolicy`
- [ ] `OMS\TaskController` index, store, show, update, destroy — route with `->except(['create', 'edit'])`; `store`/`update` return JSON for a modal per ADR-013, `show` is the task detail page (a legitimate deep-linkable exception, not a create/edit route)
- [ ] `CreateTask` action allocating `number` from `projects.next_task_number` inside a transaction
- [ ] `ChangeTaskStatus` action writing `task_status_histories` and stamping `started_at` / `completed_at`
- [ ] `AssignTask` action supporting multiple users and roles, and preserving history on reassignment
- [ ] Kanban board grouped by `status`, persisting `position` — the default view on the project detail page's board tab; task creation from the board opens the create-task modal in place, no navigation (ADR-013)
- [ ] Task list view with filters for assignee, label, milestone, sprint and due date
- [ ] Task detail page: description, subtasks, dependencies, checklist, comments, attachments, linked commits — all edits happen in place or via modal, no separate `/edit` route
- [ ] Dependency editor with cycle rejection
- [ ] `tasks.logged_hours` recalculation on time log write

### 1.6 Milestones and sprints
- [ ] `OMS\MilestoneController` and `OMS\SprintController` — route with `->except(['create', 'show', 'edit'])`, modal-driven (ADR-013)
- [ ] Sprint planning screen showing committed hours against capacity; sprint/milestone creation and editing via modal on the project detail page
- [ ] Milestone timeline view

---

## Phase 2 — Progress Monitoring

- [ ] `CalculateProjectProgress` action rolling task completion up to module, milestone and project (FR-4.1)
- [ ] `SnapshotProjectProgress` scheduled job writing `project_progress_snapshots` nightly (FR-4.2)
- [ ] `DetermineProjectHealth` rule set driving `projects.health` from schedule and hours variance
- [ ] Project dashboard: completion, burndown from snapshots, overdue and blocked counts (FR-4.3)
- [ ] Portfolio dashboard across all team projects
- [ ] `RecordActivity` action plus model observers populating `activities` (FR-4.4)
- [ ] Activity feed component with project and user filters
- [ ] Cycle-time and lead-time report from `task_status_histories`
- [ ] Reconciliation command for `logged_hours` and the progress percentages

---

## Phase 3 — To-Do Lists

- [ ] `OMS\TodoListController` and `OMS\TodoItemController` (FR-5.1 – FR-5.5)
- [ ] `GenerateDailyTodoList` action building a dated list from open assignments due soon (FR-5.6)
- [ ] `PromoteTodoItemToTask` action, and the reverse for task checklists
- [ ] "My day" page combining personal items, task checklists and meeting actions
- [ ] Task checklist component on the task detail page
- [ ] Optional scheduled job generating each user's daily list

---

## Phase 4 — Meetings and Minutes

- [ ] `OMS\MeetingController` with scheduling and cancellation (FR-6.1)
- [ ] `OMS\MeetingAttendeeController` for invitations, RSVP and attendance marking (FR-6.2)
- [ ] Agenda builder with ordering and task linking (FR-6.3)
- [ ] Minutes editor with a publish step stamping `minutes_published_at` (FR-6.4)
- [ ] `CaptureMeetingActionItems` action creating a `meeting_actions` to-do list (FR-6.5)
- [ ] Meeting timer writing a `time_logs` row against the meeting (FR-6.6)
- [ ] Minutes published notification to attendees
- [ ] Meeting list and calendar views

---

## Phase 5 — Git Integration

### 5.1 Connection
- [ ] `config/git.php` holding per-provider OAuth and API configuration
- [ ] `GitProviderClient` contract with GitHub, GitLab, Bitbucket and Gitea implementations (FR-7.1)
- [ ] `Git\RepositoryController` for connect, link to project, sync and disconnect (FR-7.2)
- [ ] `RegisterRepositoryWebhook` action storing `webhook_external_id`
- [ ] `Git\IdentityController` for linking a provider account to the signed-in user (FR-7.7)

### 5.2 Ingest
- [ ] `Git\WebhookController`: verify signature, persist `git_events`, dispatch, return (FR-7.4)
- [ ] `ProcessGitEvent` queued job, idempotent on redelivery
- [ ] `IngestCommits`, `IngestBranches`, `IngestPullRequests` actions (FR-7.5)
- [ ] `LinkCommitToTasks` action parsing task references from the commit message and branch name (FR-7.6)
- [ ] `ResolveGitAuthor` action mapping an author email to a user through `git_identities`
- [ ] `BackfillRepository` job for initial history on connect
- [ ] Failure handling: `sync_status`, `last_sync_error`, and a retry path for failed events

### 5.3 Reporting
- [ ] Commit and push history page, filterable by repository, project, user and date range (FR-7.8)
- [ ] Linked commits, branches and pull requests panel on the task detail page
- [ ] Per-developer contribution report
- [ ] Repository health panel: open pull requests, stale branches, last sync

---

## Phase 6 — Availability and Time

- [ ] `UserWorkScheduleController` for the weekly template, versioned by effective date (FR-8.1)
- [ ] `TimeOffRequestController` with an approval workflow (FR-8.2)
- [ ] `ResourceAllocationController` for booking hours (FR-8.3)
- [ ] `CalculateUserAvailability` action returning capacity, occupied and free hours per day (FR-8.4)
- [ ] `FindAvailableUsers` query object answering "who has N free hours between two dates" (FR-8.5)
- [ ] `TimeLogController` with start, stop, manual entry and edit (FR-8.6, FR-8.7)
- [ ] Timer widget surfacing the running entry, enforcing one per user
- [ ] Timesheet page with weekly grid and submission
- [ ] Timesheet approval screen for managers
- [ ] Team capacity heatmap over a date range
- [ ] Over-allocation warning when a booking exceeds remaining capacity
- [ ] Estimated versus actual report comparing `resource_allocations` with `time_logs`

---

## Phase 7 — Collaboration

- [ ] `CommentController` with threading (FR-9.1)
- [ ] `ParseCommentMentions` action populating `comment_mentions` and notifying
- [ ] Internal comment visibility enforced against the client role (FR-9.2)
- [ ] `AttachmentController` with validation and signed downloads (FR-9.3)
- [ ] Comment thread and attachment list components
- [ ] Notification preferences per user

---

## Cross-Cutting

- [ ] Policies for every domain model, with cross-team access covered by tests
- [ ] `EnsureTeamMembership` coverage extended over all new routes
- [ ] Wayfinder regeneration wired into the build
- [ ] Queue worker and scheduler configuration for deployment
- [ ] Seed data large enough to profile the availability and progress queries
- [ ] Query-plan review of every composite index in `DESIGN.md` against realistic volumes
