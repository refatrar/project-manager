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
