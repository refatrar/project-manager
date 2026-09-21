# Requirement Traceability Matrix

Source documents: `RD.md` (functional/non-functional requirements), `ARCHITECTURE.md`, `DESIGN.md`, `TASKS.md`. IDs prefixed `FR-`/`NFR-` are quoted directly from `RD.md`. IDs prefixed otherwise (`NOTIFY-`, `SEARCH-`, `API-`, `VIS-`, `FILE-`, `DASH-`, `SEC-`) are implied or recommended requirements that are not yet written down anywhere and have no owner document. `Status` reflects the repository as inspected, not intent: `Done` means schema/model/test exists, `Planned` means a `TASKS.md` phase names it, `Gap` means no phase or document currently covers it.

## Existing requirements (from RD.md)

| ID | Requirement | Source | Phase | Architecture Area | Status |
| --- | --- | --- | --- | --- | --- |
| FR-1 | Multiple concurrent projects per team, with code/slug, hierarchy-free structure, plan vs actual dates, archiving | RD.md §FR-1 | MVP | Database, Delivery | Done (schema) / Gap (no `ProjectPolicy`, controllers, pages yet — TASKS 1.2) |
| FR-2 | Task management: optional module, subtasks, dependencies, three independent status axes, history, effort fields | RD.md §FR-2 | MVP | Database, Delivery | Done (schema) / Gap (controllers, board, dependency editor — TASKS 1.5) |
| FR-3 | Multi-user, multi-role task assignment with history preservation | RD.md §FR-3 | MVP | Database, Delivery | Done (schema, `task_assignments`, feature tests) / Gap (`AssignTask` action — TASKS 1.5) |
| FR-4 | Progress monitoring: rollups, daily snapshots, overdue/blocked queries, activity feed | RD.md §FR-4 | MVP → Phase 2 | Database, Reporting | Done (schema) / Gap (calculators, snapshot job, dashboards — TASKS Phase 2) |
| FR-5 | To-do lists: personal, daily, checklist, meeting-linked, generated | RD.md §FR-5 | Phase 3 | Database, Delivery | Done (schema) / Gap (controllers, generation job — TASKS Phase 3) |
| FR-6 | Meeting minutes: scheduling, attendees, agenda, decisions, action items, time logging | RD.md §FR-6 | Phase 4 | Database, Delivery | Done (schema) / Gap (controllers, editor, notification — TASKS Phase 4) |
| FR-7 | Git integration: multi-provider connection, durable webhook ingest, commit-to-task linking, push history | RD.md §FR-7 | Phase 5 | Git | Done (schema, `GitCommitTest`) / Gap (provider clients, controllers, ingest jobs — TASKS Phase 5) |
| FR-8 | Availability: work schedules, time off, allocations, capacity queries, time logs with approval | RD.md §FR-8 | Phase 6 | Time | Done (schema, `ResourceAllocationTest`) / Gap (controllers, timer, timesheet — TASKS Phase 6) |
| FR-9 | Collaboration: polymorphic comments with mentions and internal visibility, attachments | RD.md §FR-9 | Phase 7 | Collaboration | Done (schema) / Gap (controllers, mention parsing, signed downloads — TASKS Phase 7) |
| NFR-1 | Tenant isolation on every read/write | RD.md §NFR-1 | MVP | Security | Partial — enforced by convention (`Project::forMember()`, `EnsureTeamMembership`) and by 3 feature tests; no automated cross-team test exists for every model yet (see SEC-002) |
| NFR-2 | Paginated lists, no per-row queries, aggregate counts | RD.md §NFR-2 | MVP | Performance | Gap — no list controllers exist yet to verify against |
| NFR-3 | Git ingest is queued, provider outage does not block requests | RD.md §NFR-3 | Phase 5 | Git, Performance | Planned (`git_events` table exists; queue job not yet written) |
| NFR-4 | Provider tokens/webhook secrets encrypted at rest | RD.md §NFR-4 | MVP | Security | Done — `GitRepository` uses `encrypted` casts |
| NFR-5 | Destructive actions are soft deletes with a recorded actor | RD.md §NFR-5 | MVP | Database | Done for `projects`, `project_modules`, `project_members`, `tasks`, `git_repositories`; **not verified for every domain table** — see GAP-004 |
| NFR-6 | Availability/progress queries answer for 90 days × 50 users without full scan | RD.md §NFR-6 | Phase 6, Phase 2 | Performance | Planned — indexes exist (`DESIGN.md` §3.11); no query-plan review performed yet (TASKS Cross-Cutting) |
| NFR-7 | Reporting reads from snapshots, not recomputation | RD.md §NFR-7 | Phase 2 | Reporting | Done (schema: `project_progress_snapshots`) / Gap (writer job) |
| NFR-8 | UTC storage; work schedule carries its own timezone | RD.md §NFR-8 | MVP | Database | **Needs verification** — `user_work_schedules` migration was not inspected for a `timezone` column in this pass; confirm before Phase 6 |

## Implied and recommended requirements (not currently in RD.md)

| ID | Requirement | Source | Phase | Architecture Area | Status |
| --- | --- | --- | --- | --- | --- |
| AUTH-001 | Reconcile `TeamRole` (owner/admin/member) with `ProjectMemberRole` (9 project-level roles) against the 5 actors named in RD.md §1 | Implied by RD.md §1 + existing `TeamRole`/`ProjectMemberRole` enums | MVP | Authorization | **Resolved** — Client is project-scoped only, no `TeamRole`. See `architecture-decisions.md` ADR-008. Remaining actor-to-role mapping (owner/PM/lead/contributor) still needs `ProjectPolicy` implementation in Phase 1. |
| VIS-001 | Explicit project visibility levels (team-wide vs membership-only vs client-restricted) | Implied by RD.md FR-1.6 ("wider visibility") and FR-9.2 (internal comments) | MVP | Delivery, Security | **Resolved** — Owner/Admin see all projects, Member requires explicit membership, no separate privacy flag. See ADR-011. |
| NOTIFY-001 | Notification architecture: channels (in-app/email), triggers (assignment, mention, due date, status change, invitation), digesting, per-user preferences | Implied by RD.md FR-9.1 (mentions) and TASKS.md Phase 7 ("Notification preferences per user") with no upstream FR | Phase 7 | Notifications | Gap — no FR, no schema (no `notifications` table in `DESIGN.md` table inventory). Still open — not covered by this round of decisions. |
| SEARCH-001 | Search strategy across projects, tasks, comments, users | Implied by product category (project management tool) | Phase 8 (Future) | Search | Gap — absent from RD.md entirely |
| API-001 | Public/versioned REST API strategy for third-party or mobile clients, distinct from Inertia page routes | User's stated stack ("REST/API where appropriate") vs RD.md, which defines only Inertia routes and inbound Git webhooks | N/A | API | **Resolved** — no public API; Inertia-only. See ADR-010. |
| FILE-001 | Attachment storage strategy: disk (local vs S3-compatible), size limits, MIME allowlist, signed URLs, virus-scan placeholder | RD.md FR-9.3 is one line ("Files can be attached"); `attachments` table exists but no policy is documented | MVP-adjacent (Phase 7 needs it) | Storage, Security | **Partially resolved** — local disk confirmed. See ADR-009. Size limits/MIME allowlist/signed URLs still open, to be settled when `AttachmentController` is built. |
| DASH-001 | Personal ("my work") and team-level dashboards, not only project/portfolio | RD.md FR-4 covers project and portfolio; TASKS.md Phase 2 adds portfolio only | Phase 2/3 | Reporting | Recommended — partially covered by FR-5.6 ("my open tasks due this week") and the "My day" page in TASKS Phase 3, but no dedicated FR |
| SEC-001 | Rate limiting on authentication, invitation acceptance, and Git webhook endpoints | Standard Laravel practice; not mentioned anywhere in RD.md/RULES.md | MVP for auth, Phase 5 for webhooks | Security | Gap |
| SEC-002 | A cross-team access feature test exists for every policy-guarded model, not only the 3 domains currently tested | RULES.md §9 already states the rule ("every endpoint gets ... a cross-team case"); no matrix tracks compliance | Ongoing | Security, Testing | Gap in tracking, not in intent |
| AUDIT-001 | Distinguish the user-facing activity feed (`activities`) from any future security/compliance audit log | RD.md FR-4.4 and DESIGN.md's `activities` table conflate both today | Phase 2 | Reporting, Security | Recommended — no action needed unless compliance requirements emerge; record as a conscious deferral |
