# Architecture Decision Log

Decisions already made and implemented are recorded here as ADRs so future contributors don't have to reverse-engineer them from `DESIGN.md`/`MEMORY.md` prose. New ADRs are added only when a decision is actually settled — pending choices live in `open-decisions.md` instead.

---

## ADR-001: Frontend framework is React, not Vue

### Context
The committed stack (`ARCHITECTURE.md`, `package.json`, `composer.json`) uses Inertia v3 with `@inertiajs/react` and React 19, plus a full `resources/js` tree of React pages, Wayfinder-generated actions, and shadcn/Radix components. A separate planning brief for this audit described the stack as "Inertia.js, Vue.js," which conflicts with what is actually built.

### Decision
Treat React as authoritative. `ARCHITECTURE.md` (the committed, load-bearing document) and the installed dependencies agree with each other; only the ad-hoc audit brief disagrees with both.

### Alternatives
- Migrate to Vue: rejected for this phase. It would discard the existing Wayfinder React actions, the shadcn/Radix component set, and all working pages (`resources/js/pages/**`) for no product benefit, and nothing in `RD.md` depends on Vue specifically.

### Consequences
This audit's recommendations assume React + Inertia v3 throughout. The contradiction is flagged to the user in `open-decisions.md` (OD-5) in case the Vue mention was intentional and the codebase is what's wrong, but no code or architecture changes are made on the strength of a one-line brief when 12KB of committed architecture documentation and a working build say otherwise.

### Status
Accepted (pending user confirmation — see OD-5).

---

## ADR-002: Multi-tenancy is team-based, roles are two-tier

### Context
`RD.md` §1 requires every record to belong to a team and states a user "holds a role per team and a separate role per project." The codebase already implements this: `TeamRole` (owner/admin/member, 3 levels, permission-gated) governs team-level actions (billing, membership, invitations), and `ProjectMemberRole` (9 values: owner, manager, lead, developer, designer, qa_engineer, devops, viewer, client) governs project-level actions via `canManageProject()`.

### Decision
Keep the two-tier model. Team role answers "can this user manage the team and its membership." Project role answers "what can this user do inside a specific project." Neither role is inferred from the other; a team `Member` can hold `Owner` on one project and `Viewer` on another.

### Alternatives
- Single flat role across the whole team: rejected — `RD.md` FR-1.6 explicitly requires per-project visibility narrower than team-wide, which a flat role can't express.
- Full RBAC/permission package (e.g., spatie/laravel-permission): rejected for now under the "don't over-engineer" constraint — two closed enums with `hasPermission()`/`canManageProject()` methods cover the currently stated requirements without a new dependency.

### Consequences
Every policy must consult both roles. The mapping from `RD.md`'s five named actors (team owner, project manager, team lead, contributor, client) onto these two enums is not yet written down explicitly — tracked as OD-1.

### Status
Accepted.

---

## ADR-003: Task status is three independent axes, not one column

### Context
The original `project_module_tasks.status` column conflated assignment state, review outcome and deployment stage into one 11-value enum, making states like "in review and already on staging" unrepresentable.

### Decision
Split into `tasks.status` (lifecycle), `tasks.review_status` (outcome), `tasks.deployment_stage` (where the code is). Assignment state lives in `task_assignments`, not on the task.

### Alternatives
- Single status with a larger enum: rejected — it's the bug being fixed, not a design.
- A generic workflow-state-machine table: rejected as over-engineering; nothing in `RD.md` asks for user-configurable workflows.

### Consequences
UI and queries that used to filter on one column now filter on up to three. The kanban board (TASKS.md 1.5) is grouped by `status` only, and `review_status`/`deployment_stage` become badges — that split needs to be reflected in the board component when it's built.

### Status
Accepted and implemented (`DESIGN.md` §1, `MEMORY.md`).

---

## ADR-004: Status columns are `string` + PHP enum, never MySQL `ENUM`

### Context
`scopes` and `task_types` (pre-existing tables) use MySQL `ENUM`, which requires a locking `ALTER TABLE` to add a value — turning a product decision into a schema migration.

### Decision
Every new status column is `string` with a length and default, cast to a backed PHP enum. The old tables are left as `ENUM` deliberately rather than migrated, since nothing depends on changing them and a migration would only add risk.

### Alternatives
- Migrate `scopes`/`task_types` to match: rejected — no functional benefit, adds migration risk to working code, violates "don't refactor beyond what the task requires."

### Consequences
The codebase permanently contains both patterns. Any new engineer must know this is intentional, not inconsistency — `RULES.md` §2 already states the rule inline, so this ADR just gives it a permanent home.

### Status
Accepted and implemented.

---

## ADR-005: One `todo_lists`/`todo_items` engine for all list types

### Context
Personal lists, daily lists, task checklists, and meeting action items could have been four separate tables or one discriminated pair.

### Decision
One `todo_lists` + `todo_items` pair, discriminated by `todo_lists.type`, pointed at context by four nullable foreign keys (`owner_id`, `project_id`, `task_id`, `meeting_id`).

### Alternatives
- Separate `meeting_action_items` table: rejected — would duplicate assignment, due-date and completion tracking, and "everything assigned to me" would need a UNION across tables.

### Consequences
The valid combinations of the four nullable keys are governed by `type` in application code, not by the database (no CHECK constraint enforces it). `TASKS.md` 0.4 already lists the model observer needed to enforce this before Phase 3 ships — this ADR doesn't change that plan, it just records why the schema looks the way it does.

### Status
Accepted and implemented. Enforcement (observer) is still outstanding — tracked as GAP-002.

---

## ADR-006: Git ingest durability precedes correctness

### Context
Webhook payloads must not be lost to a parsing bug or a transient failure, and providers retry deliveries.

### Decision
The webhook endpoint only verifies the signature and writes the raw payload to `git_events`; parsing happens asynchronously from `GitEvent::unprocessed()`. `unique(git_repository_id, external_event_id)` and `unique(git_repository_id, sha)` make redelivery and re-ingest idempotent.

### Alternatives
- Parse inline during the webhook request: rejected — `NFR-3` explicitly requires a provider outage not to block a web request, and parsing failures would otherwise lose data.

### Consequences
A processing failure is always recoverable by replaying `git_events`, at the cost of an extra table and a queue worker being a hard dependency for Git integration to function at all (tracked implicitly by NFR-3; no separate action needed).

### Status
Accepted and implemented (schema only; the queue job itself is Phase 5, not yet built).

---

## ADR-007: Availability is computed, not stored

### Context
"How many free hours does a user have" is a question about a date range, not a static property.

### Decision
No `available_hours` column anywhere. `available(user, day) = capacity(user_work_schedules) − occupied(resource_allocations) − unavailable(approved time_off_requests)`, computed at query time from three tables kept deliberately separate (plan vs. actuals).

### Alternatives
- A materialized/cached availability column updated on every write: rejected — every one of `user_work_schedules`, `resource_allocations`, and `time_off_requests` would need to invalidate it, multiplying write paths for a number that's cheap to compute per range (NFR-6 already sets the performance bar this must hit).

### Consequences
Every availability read is a 3-table query rather than a column read. `DESIGN.md` §3.11 lists the supporting composite indexes; NFR-6 (90-day window, 50 users, no full scan) is the acceptance bar this design must clear, and it has not yet been load-tested (tracked in `gap-analysis.md`).

### Status
Accepted and implemented (schema; the `CalculateUserAvailability` action is Phase 6, not yet built).

---

## ADR-008: Client access is project-scoped only, never team-scoped

### Context
`RD.md` §1 names "Client" as an actor with "read access to progress on a specific project." `ProjectMemberRole::Client` already exists; whether a Client should also hold a `TeamRole` was open (OD-1).

### Decision
A Client never holds a `TeamRole`. Client access is granted exclusively through `ProjectMemberRole::Client` on the specific projects they're invited to. Clients do not appear in team membership, billing, or invitation-management screens — only in the member list of the projects they belong to.

### Alternatives
- Grant a minimal `TeamRole::Member` alongside the project role: rejected — it would surface clients in team-wide member lists and invitation flows that have nothing to do with their access, and would require every team-level screen to filter clients back out.

### Consequences
`ProjectPolicy` and `TaskPolicy` must check `ProjectMemberRole` directly for client-gated behavior (e.g., FR-9.2's internal-comment visibility) rather than assuming every project member also has a `TeamRole`. Team invitation flows (`TeamInvitationController`) are unaffected — client invitations are a project-level concern, not a team-level one, and are not yet built (Phase 1, `OMS\ProjectMemberController`).

### Status
Accepted.

---

## ADR-013: The project workspace is a single page — no route navigation to create or edit an item

### Context
`Setup\ScopeController` and `Setup\TaskTypeController` already establish the precedent: `Route::resource(...)->except(['create', 'show', 'edit'])`, with `store`/`update` returning JSON for a modal on the same page rather than redirecting to a separate create/edit route (`RULES.md` §5, `ARCHITECTURE.md`'s Request Flow section). The user has now stated this explicitly as a product requirement for the project workspace: tasks, modules, members, milestones, and sprints must all be creatable and editable from the project detail page — including the Kanban board — without navigating to another URL.

The Kanban board itself was already planned (`RD.md` FR-2.6 "ordered on a board"; `TASKS.md` Phase 1.5 "Kanban board grouped by `status`, persisting `position`") — this decision does not add the board, it fixes how every create/edit interaction around it behaves.

### Decision
Every domain controller reachable from the project workspace (`OMS\ProjectController`, `ProjectModuleController`, `ProjectMemberController`, `TaskController`, `MilestoneController`, `SprintController`) follows the existing dual-response pattern and registers routes with `->except(['create', 'show', 'edit'])`. Creating a task, adding a member, adding a module, or creating a milestone/sprint opens a modal on the project detail page and posts to the existing `store`/`update` endpoint; the page never navigates away. `show` is only excluded where a dedicated detail view isn't needed — the task detail page (`TASKS.md` 1.5) is a legitimate exception since it's a distinct, deep-linkable view of a single task, not a create/edit surface; it should still perform all *editing* in-place or via modals rather than a separate `/edit` route.

### Alternatives
- Traditional multi-page CRUD (separate `/create` and `/edit` routes): rejected — contradicts the explicit product requirement and the pattern already established for Setup screens; would also mean two different UX patterns coexisting in the same app for no reason.

### Consequences
`TASKS.md` Phase 1 controller and page line items are updated to state this explicitly (see below) so it isn't left to per-developer interpretation. The task detail page remains a real, navigable URL (deep-linkable, shareable) — "single page" means no navigation *for create/edit*, not that the entire application collapses into one literal URL.

### Status
Accepted.

---

## ADR-009: Attachments use local disk storage for now

### Context
`attachments` table exists as polymorphic file metadata (RD.md FR-9.3); no storage disk, size limit, or MIME policy was documented (OD-2).

### Decision
Use the local filesystem disk (`FILESYSTEM_DISK=local`, already the `.env` default) for attachment storage through Phase 7 and MVP. Revisit before a production deployment, or when Laravel Cloud deployment is planned — object storage provisioning is covered by the `deploying-to-cloud` skill at that point.

### Alternatives
- S3-compatible storage from the start: rejected for now — adds a dependency and configuration surface (bucket, credentials, signed URL generation) before any code exists that needs it, contrary to the "don't over-engineer" constraint. Local disk is trivially swappable later since Laravel's filesystem abstraction is already the access layer.

### Consequences
Size limits, a MIME allowlist, and a signed-URL download policy remain undecided and must be settled when `AttachmentController` is actually built (Phase 7) — this ADR only fixes the disk, not the validation policy. Local disk storage does not survive a multi-server deployment without a shared filesystem or migration to object storage; this is an accepted, explicitly time-boxed limitation, not an oversight.

### Status
Accepted.

---

## ADR-010: No public REST API — Inertia-only application

### Context
`RD.md` defines only Inertia page routes and inbound Git provider webhooks; the audit brief's mention of "REST/API where appropriate" left open whether a public, versioned API for third-party or mobile consumers was actually in scope (OD-3).

### Decision
No public API. This remains an Inertia-only web application. Controllers respond with `Inertia::render()` or a redirect for page visits, and JSON only for modal forms — the existing dual-response pattern (`RULES.md` §5, following `Setup\TaskTypeController`) — not Eloquent API Resources or versioned endpoints.

### Alternatives
- Build with API Resources and versioning from Phase 1 onward "just in case": rejected — nothing consumes it, and it adds structure (Resource classes, version prefixes, token auth) that has no current requirement to satisfy.

### Consequences
If a public API becomes a real requirement later, it is a deliberate, separately-scoped addition — not a retrofit of assumptions already baked into every controller. Inbound Git webhooks remain the one exception, already scoped in `RD.md` FR-7 and unaffected by this decision.

### Status
Accepted.

---

## ADR-011: `TeamRole::Owner` and `TeamRole::Admin` see all projects; `Member` does not

### Context
`RD.md` FR-1.6: a user sees only projects they're a member of, "unless the team role grants wider visibility." No role was named (OD-4).

### Decision
`TeamRole::Owner` and `TeamRole::Admin` bypass project membership for read access to every project in the team. `TeamRole::Member` sees only projects where they hold an explicit `ProjectMemberRole`. No separate per-project visibility flag exists on `projects` — visibility is derived entirely from team role plus project membership.

### Alternatives
- Owner-only bypass, Admin still requires membership: rejected — `TeamRole::Admin` already holds `TeamPermission::UpdateTeam` and other team-wide management permissions (`TeamRole::permissions()`); restricting project visibility more tightly than team management privileges would be an inconsistent trust boundary.
- A separate per-project "private" flag: not requested by `RD.md` and not added — would be speculative scope until a concrete need for project-level privacy (independent of team role) is raised.

### Consequences
`Project::forMember()` (referenced in `ARCHITECTURE.md`'s Multi-Tenancy section) needs a companion path for Owner/Admin that skips the membership join — implement as a second local scope (e.g., `Project::visibleTo($user)`) rather than branching inside every call site, consistent with `RULES.md` §4's scope-attribute convention.

### Status
Accepted.

---

## ADR-012: Frontend framework is confirmed as React (supersedes any Vue references)

### Context
ADR-001 treated React as authoritative based on the committed codebase, while flagging the audit brief's mention of Vue as an open confirmation item (OD-5).

### Decision
React is confirmed correct. The Vue mention in the original audit brief was a copy-paste artifact from a generic template, not an intended technology pivot.

### Alternatives
N/A — this ADR only closes the confirmation loop opened by ADR-001; no new alternative was evaluated.

### Consequences
None beyond removing the ambiguity. No code or documentation changes are required beyond what ADR-001 already stated.

### Status
Accepted.

---

## ADR-014: FR-8.9's over-allocation warning is a boolean flag, computed from the same all-projects occupancy FR-8.4 already exposes

### Context
`RD.md` FR-8.9: "If a member is booked or over-committed elsewhere at a time a manager is trying to schedule them on the manager's own project, the manager must see a warning that a conflict exists, even though FR-8.8 forbids showing the confidential detail behind it." Left open: what counts as "over-committed" (any overlapping booking at all, or specifically over 100% capacity), and how the warning is computed without leaking which other project or task is responsible — the same cross-project privacy boundary FR-8.8 draws for schedule *visibility*, applied here to a *derived signal* instead.

### Decision
"Over-committed" means the FR-8.4 formula's own numbers going negative before the floor: on at least one day, `occupied_hours + unavailable_hours > capacity_hours`, using `CalculateUserAvailability`'s existing all-projects aggregation — not a separate threshold, and not "any overlap" (a lightly-booked day elsewhere is not a conflict). The check runs over each booking's own date range, across every project the person is booked on, and surfaces as a single `over_allocated: bool` per booking (`App\Actions\OMS\DetectOverAllocatedBookings`) — no count, no project name, no task, nothing beyond "yes/no, on this date range." Shown as a small "Over capacity" badge on the project's Allocations tab, generic-worded ("possibly from a commitment on another project") rather than naming anything.

### Alternatives
- Any overlapping booking at all (not just over-capacity) counts as a conflict: rejected — RD.md's own FR-8.4 language treats capacity as the meaningful boundary (`available = capacity − occupied − unavailable`, floored at zero); a second booking that still fits inside remaining capacity is not a real conflict, and flagging it would make the warning noisy enough to be ignored.
- A live "check before you book" preview as the manager fills in the allocation form: deferred — the always-current badge on the list (recomputed on every page load from whatever's in the database right now) already satisfies "the manager must see a warning," and is simpler than adding a new endpoint plus debounced client-side validation for a first pass. A live preview is a reasonable future enhancement, not a requirement this ADR blocks.
- Compute and store the flag as a column on `resource_allocations`: rejected for the same reason `ARCHITECTURE.md`'s Derived Values table keeps availability itself uncached (ADR-007) — the set of "other" bookings that make someone over-allocated changes independently of the booking being flagged, so a stored flag would drift the moment any other booking on any other project is added, edited or removed, with nothing to invalidate it.

### Consequences
The flag is recomputed on every load of a project's Allocations tab, one `CalculateUserAvailability` call per unique booked user on that project (not per booking) — acceptable at a single project's scale, same reasoning `ProjectController::show` already applies to its unpaginated activity feed and task list. Nothing about this consequence is specific to FR-8.9 that the project-scoped work schedule (FR-8.8, still unimplemented) can't reuse unchanged, since `DetectOverAllocatedBookings` never touches which project the "other" hours came from.

### Status
Accepted.

---

## ADR-015: Phase 7's platform-admin RBAC is a separate system from `TeamRole`/`TeamPermission`, not a replacement

### Context
Phase 7 (added 2026-09-22, direct user request) moved team creation out of self-service and into a super-admin-controlled process: an admin creates a team, assigns an existing user as its leader, and manages other admins — and the user asked for "a role permission module... to manage this process." The app already has a working, enum-based authorization system (`TeamRole` — Owner/Admin/Member — and `TeamPermission`, wired through `TeamPolicy` and used directly in most OMS controllers) covering everything built in Phases 1-6. Left open by the request's own wording: does "a role permission module" mean extending that existing system, or building something new — and if new, does it replace the old one or sit beside it?

### Decision
A new, separate, database-backed RBAC — `admins`, `roles`, `permissions` tables, `permission_role` and `admin_role` many-to-many pivots — scoped strictly to the platform-admin process Phase 7 introduces: who can create a team, assign or reassign a team leader, and manage other admin accounts and roles. `App\Models\Admin` is a completely separate `Authenticatable` from `App\Models\User`, authenticated through its own `admin` guard, and never becomes a team member. `TeamRole`/`TeamPermission` are untouched and keep governing every existing OMS feature exactly as before; nothing in Phases 1-6 changes. This was confirmed directly with the user before any code was written (see TASKS.md's Phase 7 preamble), specifically because the alternative — folding admin-panel permissions into the existing team-level system, or migrating `TeamRole` itself onto database-backed roles — would have meant touching the authorization surface of nearly every OMS controller (`teamRole()`, `isAtLeast()`, `hasTeamPermission()` are called directly throughout `ProjectPolicy`, `TeamPolicy`, and ~15 OMS controllers) for a request that only asked for a scoped provisioning workflow.

### Alternatives
- Extend `TeamRole`/`TeamPermission` with a new "super admin" case and a platform-level flag on `users`: rejected — conflates "runs the whole platform, no team membership at all" with "the highest role within one team," which are genuinely different concepts (a super admin's authority has nothing to do with any team's `team_members` row), and the user explicitly chose "a separate admin guard" over a flag on the existing `User` model when asked directly.
- Replace `TeamRole`/`TeamPermission` outright with the new database-backed `roles`/`permissions` tables, migrating `team_members.role` to a foreign key: rejected as disproportionate scope for what was asked — a full rewrite of an already-shipped, already-tested authorization system, not "a role permission module to manage this process." Left as a possible future direction if the product genuinely needs configurable *team-level* roles later, but not assumed here.
- A single unified `roles`/`permissions` system serving both admins and team members via a polymorphic "holder" relation: rejected for the same reason as the previous option, plus added complexity (polymorphic pivots, a permission catalogue that would need to distinguish admin-panel capabilities from team capabilities anyway) for no immediate requirement.

### Consequences
Two authorization systems now coexist in the codebase with different shapes — one fixed/enum-based (`TeamRole`), one configurable/database-based (the new `roles`/`permissions` tables) — and future contributors need to know which one governs which surface: `admin/*` routes check `Admin::hasPermission()`; every `{current_team}/*` route keeps checking `TeamRole`/`TeamPermission` exactly as documented in `ARCHITECTURE.md`. If team-level roles ever need to become genuinely configurable (not just admin-panel-assignable), that is a separate, later decision, not an extension of this one.

### Status
Accepted. Partially superseded by ADR-016: the admin-panel RBAC's *storage* moved from Phase 7's hand-rolled tables onto `spatie/laravel-permission`, but the scope boundary this ADR establishes — separate from `TeamRole`/`TeamPermission`, `Admin` never a team member — is unchanged and still governs.

---

## ADR-016: Admin-panel RBAC moves onto `spatie/laravel-permission`, scoped only to the `admin` guard

### Context
TASKS.md 7.8 asked for "module-wise custom permissions" in the admin panel — an admin defining new permission keys at runtime, not just assigning the 4 `AdminPermission` enum cases ADR-015's hand-rolled `roles`/`permissions` tables were built around. Three shapes were possible: (a) extend the existing DB-backed tables with a `module` column and a permission-creation UI, (b) adopt `spatie/laravel-permission` and reconcile or retire the Phase 7 tables, or (c) stay narrowly scoped to finer-grained toggles within the existing 2 permission groups. Asked and confirmed directly with the user: adopt spatie.

### Decision
`spatie/laravel-permission:^8.3`, applied **only to the `admin` guard**. `App\Models\Admin` gained `Spatie\Permission\Traits\HasRoles` and an explicit `guard_name = 'admin'`; `App\Models\User`/the `web` guard gets nothing from this package — `TeamRole`/`TeamPermission`/`ProjectMemberRole` and every OMS Policy are untouched, so ADR-002's original rejection of spatie for that system still holds exactly as written. Phase 7's `roles`/`permissions`/`permission_role`/`admin_role` tables were dropped outright (no production data existed) and replaced by spatie's own `roles`/`permissions`/`model_has_roles`/`model_has_permissions`/`role_has_permissions`, with a follow-up migration adding the columns spatie doesn't ship but this app's admin panel already relied on: `roles.is_system`/`slug`/`description`, and `permissions.module`/`label` — the last two being the actual "module-wise" capability requested. `App\Models\Role`/`Permission` extend spatie's own model classes (via `config/permission.php`'s `models.*`) rather than replacing them, adding only these extra columns. `Admin::hasPermission(string $key): bool` was kept as a stable wrapper delegating to spatie's `hasPermissionTo()` — every pre-existing `Admin::hasPermission(AdminPermission::X->value)` call site across `Admin\TeamController`/`WorkScheduleController`/`RoleController`/`AdminController`/`SaveWorkScheduleRequest` needed zero changes, and it also catches spatie's `PermissionDoesNotExist` (thrown for a name with no matching row at all) to preserve this method's original "just return false" contract. The net-new capability — a real, admin-created custom permission, not just assignment of the 4 fixed ones — is `Admin\PermissionController`, guarded so the 4 `AdminPermission` keys can never be renamed or deleted through it (mirrors `Role::is_system`'s existing protection).

### Alternatives
- Extend the existing hand-rolled tables in place (add `module`, keep raw pivot `sync()`/`attach()`): rejected — the user was offered this as the lowest-risk, no-new-dependency option and chose spatie instead, for its battle-tested permission-checking, wildcard support, and caching over continued hand-rolling.
- Adopt spatie for `TeamRole`/`TeamPermission` too, unifying both systems: not asked for and explicitly out of scope — ADR-002's reasoning (two closed enums cover the OMS side's stated requirements without a new dependency) was never revisited, and touching `TeamPolicy`/the ~15 OMS controllers that call `hasTeamPermission()`/`teamRole()` directly was exactly the blast radius ADR-015 was written to avoid.
- Rewrite every `Admin::hasPermission()` call site to spatie's own `$admin->can(...)`/`hasPermissionTo(...)` directly, dropping the wrapper: rejected as unnecessary churn — the wrapper is the one-line reason 3 of the 5 gated controllers needed no diff at all for this migration.

### Consequences
`permissions.name` is spatie's raw dot-notation key (`teams.manage`); `label`/`module` are this app's own additions for display/grouping, nullable so spatie's internal permission-creation helpers that don't know about them don't break. Permission checks are now cached (spatie auto-invalidates on any `assignRole`/`syncPermissions`/etc. call) rather than always freshly queried — a behavior improvement, contingent on every mutation going through spatie's own methods rather than a raw `DB::table()` write (true everywhere in this codebase as of this ADR). `Role`/`Permission` model instances are now `Spatie\Permission\Models\*` subclasses; anything that previously constructed the old hand-rolled shape (e.g. test fixtures using `key`/`group` column names) needed rewriting to spatie's `name`/`guard_name` shape, done across all affected Feature tests in the same change.

### Status
Accepted for the admin guard. The decision that the `web` guard gets nothing from this package is superseded by ADR-017.

---

## ADR-017: Team-module permissions are assigned in the admin panel

### Context
The team panel authorized every module with two closed enums: `TeamRole` (owner / admin / member) and `TeamPermission` (team settings only). Project, meeting, time, and capacity screens then repeated `isAtLeast(TeamRole::Admin)`. The platform admin needs to decide what those roles can do. Team accounts then run under that decision; a team owner does not edit the permission matrix.

### Decision
Keep `spatie/laravel-permission` and add a `web`-guard catalogue, `App\Enums\TeamModulePermission`, covering every team module. Roles are global (`guard_name=web`, `team_id` null): one Owner, one Admin, one Member, plus any custom role the admin creates. `App\Services\Teams\TeamAccessControl` seeds that catalogue and answers permission checks. The admin panel's Team roles screen assigns permissions to those roles. A membership still stores the role slug, and team owners still choose which role a person has, but they cannot change what the role allows. Until the catalogue exists, checks fall back to the previous Owner / Admin / Member matrix. `ProjectMemberRole` is unchanged. Platform-admin roles stay on the `admin` guard.

### Alternatives
- Turn on spatie's teams feature: rejected. That feature makes `team_id` part of every role assignment, including the admin guard's `model_has_roles` primary key, which is not nullable.
- Let each team edit its own copy of Admin: rejected. The admin panel is the place that assigns role permissions, and every team account with a role follows that assignment.
- A roles screen inside the team panel: rejected for the same reason.

### Consequences
`TeamPolicy` and the OMS policies call `User::teamCan()`. The team sidebar reads the shared `teamAccess` list and has no roles screen. Changing Admin in the admin panel changes every team that uses Admin.

### Status
Accepted.
