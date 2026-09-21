# Implementation Roadmap

This restates `TASKS.md`'s phases with the MVP boundary made explicit and the gaps from `gap-analysis.md` inserted at the point they block real work. It does not replace `TASKS.md`, which remains the line-item backlog; this file is the phase-level view plus the decisions and observers that `TASKS.md` doesn't call out as prerequisites.

## MVP boundary

**MVP** = a team can create projects, break them into tasks, assign and track those tasks to completion, and see accurate progress. Everything else is Phase 2+.

| Phase | Contents | Boundary |
| --- | --- | --- |
| Phase 0 | Data layer (schema, models, enums) | MVP — largely done |
| Phase 1 | Projects, modules, members, tasks, milestones, sprints (CRUD + board) | MVP |
| Phase 2 | Progress monitoring, dashboards, activity feed | MVP (progress is core to "project management") |
| Phase 3 | To-do lists | Phase 2 (valuable, not launch-blocking) |
| Phase 4 | Meetings and minutes | Phase 2 |
| Phase 5 | Git integration | Phase 2 (high value, high complexity — do not let it block MVP) |
| Phase 6 | Availability and time tracking | Phase 2 |
| Phase 7 | Collaboration (comments, attachments, notifications) | Phase 2 — comments are borderline MVP; attachments/notifications are not |
| Future | Search infrastructure beyond MySQL, real-time updates, advanced analytics, API/integrations | Future / Optional, per `open-decisions.md` |

## Phase 0 — Data Layer (MVP)

Status: schema, models, enums, and factories for 17 of 29 new models are done (`TASKS.md` 0.1–0.3). Remaining before Phase 1 can start safely:

- [ ] Verify the migration table matches reality and correct the stale "apply the schema" line in `TASKS.md` 0.4 (`gap-analysis.md` row 1)
- [ ] Build the three invariant-enforcing observers named in `DESIGN.md` §5 and `MEMORY.md`: `todo_lists.type`-to-context validation, task-module-belongs-to-task-project, `task_dependencies` cycle rejection (`gap-analysis.md` row "Invariant enforcement")
- [ ] Add a soft-delete coverage table to `DESIGN.md` (`gap-analysis.md` row "Soft-delete coverage")
- [ ] Confirm `user_work_schedules` has a `timezone` column per NFR-8 (`gap-analysis.md` row "Timezone handling")
- [ ] Resolve OD-1 (role mapping) and OD-4 (project visibility) before writing `ProjectPolicy`

## Phase 1 — Project and Task Management (MVP)

As listed in `TASKS.md` §Phase 1, with two additions:

- [ ] `ProjectPolicy` and `TaskPolicy` must implement whatever OD-1/OD-4 resolve to, not an ad hoc interpretation
- [ ] Add throttling to any new auth-adjacent endpoint introduced in this phase (invitation flows already exist; nothing new expected here, but check)

## Phase 2 — Progress Monitoring (MVP)

As listed in `TASKS.md` §Phase 2. No new prerequisites — the snapshot schema and indexes are already designed.

## Phase 3 — To-Do Lists (Phase 2 priority)

As listed in `TASKS.md` §Phase 3. Depends on the `todo_lists.type` observer from Phase 0.

## Phase 4 — Meetings and Minutes (Phase 2 priority)

As listed in `TASKS.md` §Phase 4, with one addition:

- [ ] Resolve NOTIFY-001 (notification architecture) before building "Minutes published notification to attendees" — there is currently no `notifications` table or preference model for it to write to

## Phase 5 — Git Integration (Phase 2 priority)

As listed in `TASKS.md` §Phase 5. Add rate limiting to the webhook endpoint per SEC-001 when `Git\WebhookController` is built.

## Phase 6 — Availability and Time (Phase 2 priority)

As listed in `TASKS.md` §Phase 6. Depends on the NFR-8 timezone check from Phase 0.

## Phase 7 — Collaboration (Phase 2 priority)

As listed in `TASKS.md` §Phase 7, with one addition:

- [ ] Resolve OD-2 (file storage strategy) before `AttachmentController` accepts its first upload
- [ ] Resolve NOTIFY-001 before "Notification preferences per user"

## Future / Optional

- Search infrastructure beyond MySQL (Scout/Meilisearch/Typesense) — only if usage data shows MySQL search is a bottleneck
- Real-time updates (Reverb/WebSockets) for live task/comment/presence updates — nothing in `RD.md` currently requires this
- Public/versioned REST API for third-party or mobile clients — contingent on OD-3
- Redis-backed cache/queue, Horizon — contingent on scale beyond the 50-user/90-day ceiling NFR-6 already targets
- Security/compliance audit log distinct from the user-facing `activities` feed — contingent on a compliance requirement that doesn't currently exist (AUDIT-001)

## Cross-cutting, ongoing from Phase 1 onward

As listed in `TASKS.md` §Cross-Cutting, plus:

- [ ] Track cross-team test coverage per endpoint as a PR checklist item, not just a written rule (`gap-analysis.md` row "Cross-team test coverage")
