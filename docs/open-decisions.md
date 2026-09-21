# Open Decisions

Decisions that materially affect architecture and cannot be safely resolved from the existing documentation. Each blocks specific, named work in `implementation-roadmap.md`.

All five decisions below were resolved by the product owner on 2026-09-21. Resolutions are recorded in `architecture-decisions.md` as ADR-008 through ADR-012. This file is kept as the historical record of the question asked; treat the linked ADRs as the current source of truth.

---

## OD-1: How do RD.md's five actors map onto `TeamRole` and `ProjectMemberRole`? — RESOLVED, see ADR-008

**Blocks:** `ProjectPolicy`, `TaskPolicy` (Phase 1).

`RD.md` §1 names: Team owner, Project manager, Team lead, Contributor, Client. The codebase has `TeamRole` (Owner/Admin/Member) and `ProjectMemberRole` (Owner/Manager/Lead/Developer/Designer/QaEngineer/DevOps/Viewer/Client). The two aren't wired together anywhere.

Candidate mapping, offered as a starting point, not a decision:

| RD.md actor | TeamRole | ProjectMemberRole |
| --- | --- | --- |
| Team owner | Owner | typically Owner on projects they created, but not required |
| Project manager | Admin or Member | Manager |
| Team lead | Admin or Member | Lead |
| Contributor | Member | Developer / Designer / QaEngineer / DevOps |
| Client | Member (or a new restricted team role) | Client |

Open question: should a "Client" ever hold a `TeamRole` at all, or should client access always route through project-level `ProjectMemberRole::Client` with a minimal/no team role? This affects whether clients appear in team member management screens.

**Resolved:** Client access is project-scoped only. A Client never holds a `TeamRole` and never appears in team membership/billing screens — only in the member list of the specific projects they're invited to via `ProjectMemberRole::Client`. See ADR-008.

---

## OD-2: Attachment storage strategy — RESOLVED, see ADR-009

**Blocks:** `AttachmentController` (Phase 7), and the `attachments` table's real-world safety.

`.env` currently sets `FILESYSTEM_DISK=local`. `RD.md` FR-9.3 says only "Files can be attached to any of those records."

Needs a decision on:
- Storage disk for production (local disk vs. S3-compatible)
- Maximum file size and MIME allowlist
- Whether attachment downloads are signed/expiring URLs (they should be, given FR-9.2's internal-comment visibility rule extends the same access-control need to files)
- Whether virus scanning is a Phase 7 requirement or an explicit Future deferral

**Resolved:** Local disk (`FILESYSTEM_DISK=local`) for now, matching the current `.env`. Revisit before a production deploy or when Laravel Cloud deployment is planned (`deploying-to-cloud` skill covers provisioning object storage at that point). Size limits, MIME allowlist, and signed-URL policy are still open and should be decided when `AttachmentController` is actually built in Phase 7 — the disk choice doesn't resolve those. See ADR-009.

---

## OD-3: Is a public/versioned REST API in scope? — RESOLVED, see ADR-010

**Blocks:** Nothing today (RD.md explicitly defers all HTTP surfaces to later phases), but it changes how Phase 1+ controllers are shaped if the answer is yes — Eloquent API Resources and versioning add structure that's wasted effort if nothing ever consumes them, and expensive to retrofit if skipped and later needed.

`RD.md` never mentions an API beyond inbound Git webhooks. If nothing outside the Inertia frontend will ever call this application (no mobile app, no third-party integration), the honest answer is "no API," and Inertia-only controllers are the right, simpler choice per the "don't over-engineer" constraint.

**Resolved:** No public API. This is an Inertia-only web application; controllers respond with `Inertia::render()`/redirects and JSON only for modal forms, per the existing pattern. No Eloquent API Resources or versioning scaffolding should be added speculatively. See ADR-010.

---

## OD-4: What does "wider visibility" mean in FR-1.6? — RESOLVED, see ADR-011

**Blocks:** `ProjectPolicy`, the project list query (Phase 1).

`RD.md` FR-1.6: "A user sees only the projects in the current team, and only those where the user is a member unless the team role grants wider visibility." No `TeamRole` currently documents which role(s) grant this, and no visibility field exists on `projects`.

Two different decisions are bundled here and should be separated:
1. Does `TeamRole::Owner` (and/or `Admin`) see every project in the team regardless of membership? (Likely yes, uncontroversial.)
2. Should `projects` additionally carry its own visibility flag (e.g., "private" project restricted even from non-member team owners), independent of team role? (Not currently requested by RD.md — only raise this if the answer is "yes, we need per-project privacy.")

**Resolved:** Both `TeamRole::Owner` and `TeamRole::Admin` bypass project membership for read access; `TeamRole::Member` does not and still requires explicit `ProjectMemberRole` membership to see a project. No separate per-project visibility flag was requested — question 2 above remains unaddressed unless a need for project-level privacy emerges later. See ADR-011.

---

## OD-5: Frontend framework — confirm React over the audit brief's mention of Vue — RESOLVED, see ADR-012

**Blocks:** Nothing functional (React is already built and committed) — this is a confirmation, not a blocker.

The instructions for this audit described the stack as "Inertia.js, Vue.js." The committed `ARCHITECTURE.md`, `package.json`, and the actual `resources/js` tree all use React 19 with `@inertiajs/react`. `architecture-decisions.md` ADR-001 treats React as authoritative since it's what's built and documented, but this is flagged explicitly in case the Vue mention reflects an actual intended pivot rather than a copy-paste artifact in the brief.

**Resolved:** React is confirmed as correct. The Vue mention in the original brief was a copy-paste artifact, not an intended pivot. No code or documentation changes needed beyond what ADR-001 already recorded. See ADR-012.
