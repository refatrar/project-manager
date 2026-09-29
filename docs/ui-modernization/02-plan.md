# UI/UX Modernization — Plan (I–L, N–Q)

Status: **PLAN ACCEPTED 2026-09-29**, with D-1…D-7 as recommended. D-8 is still open (product decision; it blocks only UI-BE-001). D-9 is decided per task. Each task still needs its own approval before it starts and again before it is committed. Companion documents:
- `01-audit.md` covers the findings (sections A–H).
- `../design-system/README.md` is the proposed design system (section I).
- `tasks/` holds the micro-tasks (section M).

## Decisions needed before implementation starts

| ID | Decision | Recommendation | Blocks |
|---|---|---|---|
| D-1 | Primary brand hue | **Teal-700 `#0F766E`** (UI UX Pro Max "Productivity Tool", darkened for 5.5:1 contrast). The alternative is to keep today's near-black neutral primary. | UI-DS-002/003 |
| D-2 | Font | **Plus Jakarta Sans** (generator pick). The zero-risk alternative is to keep Instrument Sans. | UI-DS-004 |
| D-3 | New dependencies | **Approve:** `@radix-ui/react-tabs`, `react-alert-dialog`, `react-popover`, `react-switch`, `react-radio-group`, `react-scroll-area` (shadcn primitives, same family as the 13 Radix packages already installed). **Approve (recommended):** `cmdk` for the command palette and searchable pickers. **Defer:** `react-day-picker` (keep native date inputs) and `@dnd-kit` (keep button-based moves). **Reject:** chart and table libraries (not needed). | UI-DEP-001/002 and everything using tabs, confirm, combobox |
| D-4 | Frontend tests | Playwright via `npx` scripts for screenshots and axe (no `package.json` change, matching MEMORY.md:139) **plus** optional Vitest + Testing Library devDependencies for component tests | UI-TEST-001/002 |
| D-5 | `public/build` in commits | Task commits **exclude** `public/build`. You run or approve a separate `build` commit per batch. | Commit workflow |
| D-6 | Project workspace tabs | Regroup 9 → 7: Overview · Tasks (Board/List switch) · Modules · Milestones · Sprints · Team (Members + Allocations) · Activity | UI-PROJ-003 |
| D-7 | My Day "Meeting actions" placeholder | Short term: remove the placeholder card (UI-FIX-012). Wiring real data is backend task UI-BE-005. | UI-TODO-002 |
| D-8 | Member role holds `setup.manage` over **global** scopes and task types | Product-owner decision; not a UI change | UI-BE-001 |
| D-9 | Behavioural fixes (UI-FIX-*) | Approve individually; each one changes visible behaviour | UI-FIX-* |

---

## I. Proposed Product Design System

See `docs/design-system/README.md`, which is the single source. Summary:
- **Style:** flat, dense and calm, following "Flat / Minimal Swiss" and the "Drill-Down Analytics" dashboard pattern.
- **Colour:** one teal accent for action, focus and selection. Semantic success/warning/destructive/info tokens have subtle variants, work in light and dark, and meet ≥4.5:1 for text and ≥3:1 for control boundaries. The validated heatmap, chart and status tokens are kept.
- **Typography:** 14px app body, 24px page `h1`, 12px minimum, tabular numbers everywhere.
- **Spacing:** 4px grid, radius 8px, borders instead of shadows.
- **Motion:** 120/180/240ms, with a global reduced-motion rule.
- **Components:** shadcn primitives extended with a small set of composites (section K).

## J. Proposed Information Architecture

### J.1 App sidebar (same permission gates, regrouped)

```
[Team switcher]
Overview
  Dashboard                       dashboard.view
Work
  Projects                        projects.view
  Meetings                        meetings.view
My work
  My Day                          my-day.view
  My To-Dos                       todos.manage
Time
  Time Logs                       time-logs.manage
  Timesheet                       timesheet.view
  Time Off                        time-off.view
Manage                            (group hidden when empty)
  Timesheet Approvals             canApproveTimesheets
  Team Capacity                   team-capacity.view
  Find Available People           availability.view
Setup                             setup.manage
  Scopes · Task Types · Labels
─────
[User menu: Settings, Appearance, Log out]
```

**Why:** it replaces the meaningless "Platform" label and separates personal work from team work and management, so each role sees shorter, meaningful groups. Nothing is removed, and every item keeps its exact current gate. The active state also matches parent URLs.

### J.2 Header

Sidebar trigger · breadcrumbs (middle crumbs collapse below 768px) · right slot: command-palette trigger "Search / Jump to… ⌘K" (with D-3).
- The palette is **navigation only**: nav items, the user's projects from props already loaded, and actions like "New project" when permitted.
- Entity search (tasks, meetings, users) needs backend UI-BE-002 and is explicitly out of the UI track.

### J.3 Project workspace (with D-6)

```
Projects › ALPHA — Project name        [status] [health]      [Edit] [⋯ Archive / Delete]
Overview | Tasks | Modules | Milestones | Sprints | Team | Activity      (?tab=…  &view=board|list)
```

- "Tasks" holds the Board and List as a view toggle, sharing one filter bar.
- "Team" holds Members and Allocations (with estimated-vs-actual).
- The tab is in the URL, so refresh, back and deep links from task pages work.
- No functionality moves out of the workspace, and all modals and partial-reload keys stay the same.
- Task detail stays a deep-linkable page (ADR-013). An optional quick-preview sheet is UI-TASK-010.

### J.4 Task detail

The main column holds the description, checklist, subtasks and dependencies. A right-hand **properties rail** holds status, priority, type, assignees, module, milestone, sprint, dates, hours and billable. Status and assignees are editable inline **through the existing endpoints** (`tasks.move`, `assignments.*`), only when `can_change_status` / `canManageTask` allow it.

### J.5 Meeting detail

The left column holds Agenda, Minutes & decisions, and Action items. A right-hand sticky rail holds schedule, location or join link, timer, and attendees with RSVP.

### J.6 Settings and Admin

- **Settings:** a horizontal scrollable tab strip below `lg`, and the vertical nav at `lg` and above, both with `aria-current`. Team pages get their own heading instead of "Profile and account settings".
- **Admin:** moves to the same sidebar primitives with its own items (Dashboard, Users, Teams, Team roles, Work schedules, Holidays, Admins), a mobile sheet, breadcrumbs and a page container. Admin permissions are unchanged (any admin sees all).

### J.7 Terminology

Keep all current names except the group labels. "Find Available People" stays, because it is distinct from Team Capacity.

## K. Proposed Component Architecture

```
resources/css/app.css            tokens (design system §2–§4, §21)
components/ui/*                  shadcn primitives (existing + table, pagination, tabs, alert-dialog,
                                 popover, command, switch, radio-group, scroll-area, progress)
components/patterns/*            product composites, stateless, token-only
  page-container, page-header, section, empty-state, stat-card, data-table (+ card mode),
  data-table-pagination, filter-bar, filter-chips, status-badge, health-badge, priority-badge,
  label-chip, confirm-dialog (+ use-confirm), form-dialog, form-field, form-error-alert,
  row-actions, user-avatar / avatar-group, detail-list (properties rail), region-pending
lib/format.ts                    dates, ranges, durations, hours (Date.UTC-safe)
lib/status.ts                    enum value → tone + icon (labels come from server options)
lib/http-feedback.ts             toast on useHttp success/error; 403/500/network → form alert
hooks/use-controllable-open.ts   replaces 16 copies of modal open-state
hooks/use-query-param.ts         tab/view sync with URL (router.get replace, preserveState)
components/<module>/*            existing feature components, refactored to consume the above
pages/*                          unchanged paths and props (RULES.md §10)
```

**Rules:**
1. A pattern is added only when at least 2 pages need it.
2. Patterns never fetch data. Feature components keep `useHttp` plus `router.reload({ only })` exactly as today.
3. `patterns/` must not import from feature folders.
4. The existing `Heading` stays for section headings, and `PageHeader` renders the page `h1`.
5. Delete modals collapse into `ConfirmDialog` *per module*, one task each, to keep diffs reviewable.
6. The setup trio becomes one config-driven `SetupResourcePage`, with the three route pages as thin wrappers so page paths and props don't change.

## L. Page-by-Page Modernization Plan

Legend: **P** = priority (1 highest), **Risk** L/M/H, **Tasks** = task IDs in `tasks/`. Problems are summarised; evidence is in `01-audit.md` and the task files.

| # | Page | Current problems (UX / visual / interaction / a11y / responsive / perf) | Proposed design | Reused | Changed | Added | Risk | Testing | Tasks | P |
|---|---|---|---|---|---|---|---|---|---|---|
| 1 | `projects/show` | Tabs overflow and clip below 768px; tab not in URL; 9 flat tabs; 11 stacked overview cards; ~5px burndown labels on phones; unnamed icon buttons; 646 lines; one heavy payload | Workspace header, URL-synced Radix tabs (7), shared task filter bar, KPI strip, burndown and cycle time side by side at 1440px, tables for members/milestones/sprints/allocations, per-tab lazy chunks | kanban, list, forms, modals | show.tsx split into tab files | tabs, page-header, stat-card, data-table, row-actions | H | All CRUD modals per tab; partial reloads; Member vs Lead; 4 widths | PROJ-002…011, TASK-001…004, PERF-002 | 1 |
| 2 | `projects/tasks/show` | Status and assignees can't be changed here; hidden fields; 5 tiny cards; unsearchable dependency picker; ungated Add subtask; dependency rows overflow | Two-column layout with properties rail; inline status and assignee editing via existing endpoints; combobox for dependencies | task-form-modal, assignments modal, checklist | layout | detail-list, combobox | M | Move/assign permissions; checklist CRUD; subtasks | TASK-006…009, FIX-010 | 1 |
| 3 | Task form modal | Doesn't scroll; 15 fields; label toggles without `aria-pressed`; To-dos duplicated with the checklist | Scrollable dialog; sections (Basics · Planning · Estimates · Labels · To-dos, collapsible); FormField everywhere | task-form | dialog | form-field, form-dialog | M | Create/edit/subtask; the `'none'` sentinel; 422 errors | DS-010, TASK-005, FORM-001 | 1 |
| 4 | `projects/index` | Row cards without columns; Update gated wrongly; no archived visibility; empty state ignores filters; unlabelled filters | Table ≥768px, cards below; filter bar with labelled selects and chips plus Clear; correct `can_update` gating | project-form-modal | page | data-table, filter-bar, pagination, empty-state | M | Filters and pagination query string; permissions | PROJ-001, FIX-001, CMP-006…008 | 1 |
| 5 | `dashboard` | No title; dead-end KPIs; badge vs bar colour mismatch; unlabelled meter; no cap on list | Page header; linked StatCards (to projects with filters); health bar using tokens with focusable legend; sortable project table | portfolio-health-bar | page | stat-card, health-badge | L | Invitations modal still auto-opens; Member vs Lead project set | DASH-001…003 | 1 |
| 6 | App shell and sidebar | Flat nav; exact-match active state; inverted header padding; no skip link or `h1`; dead header code | Grouped nav (J.1), parent-active, `aria-current`, skip link, focus on navigation | ui/sidebar | app-sidebar, nav-main, header | — | M | Every role's nav set identical to today | LAYOUT-001…005, FIX-007 | 1 |
| 7 | `my-day/index` | Fake Meeting actions card; duplicates To-Dos; silent 50 cap; unlabelled checkboxes | Assigned items grouped (Overdue · Today · Later), then today's lists; placeholder removed or replaced | todo-list-card | page | section, empty-state | M | Toggle, promote, add; generated daily list | TODO-001/002, FIX-012 | 2 |
| 8 | `meetings/show` | 8 stacked cards; publish can send unsaved minutes; timer buried; ungated action items in UI; long URL overflow | Two columns with sticky rail; dirty-state guard on Publish; labelled controls | agenda, attendees, minutes, timer | layout | detail-list | M | Minutes save/publish emails; RSVP; agenda reorder | MEET-002…004, FIX-003 | 2 |
| 9 | `meetings/index` | Past and future mixed; locale-dependent dates; filters unlabelled | Upcoming/Past sections grouped by day; table/cards; formatted time ranges | meeting-form-modal | page | data-table, filter-bar | L | Filters, pagination | MEET-001 | 2 |
| 10 | `time-logs/index` | No grouping or totals; unconfirmed delete; statuses lowercase; form grid not responsive; 2 errors never shown | Day-grouped table with totals; status badges; confirm delete; responsive form | time-log-form-modal | list, form | confirm-dialog, status-badge | M | Timer start/stop; edit only while pending; UTC dates | TIME-001…003 | 2 |
| 11 | `timesheet/index` | ISO range; no "This week"; unconfirmed Submit; no caption or scope; no sticky column | Formatted range plus Today; sticky first column; caption and scope; Submit confirmation with totals | — | page | confirm-dialog | L | Week navigation `?week=`; submit | TIME-004 | 2 |
| 12 | `timesheet-approvals/index` | One-by-one decisions; title flicker; unpaginated | Grouped by member and week; per-row actions kept; optional bulk (UI-BE-006) | decide modal | page | data-table | M | Decide permission per row | TIME-005, FIX-006 | 2 |
| 13 | Admin shell and `admin/*` | No mobile nav; inline forms per row; `window.confirm`; unconfirmed deletes; silent successes; 508-line users page | Sidebar shell; DataTables; create/edit in dialogs; row actions menu; ConfirmDialog; toasts | forms' logic | all admin pages | shared patterns | M | Admin feature tests; users/teams/roles CRUD | LAYOUT-006, USER-001…004 | 2 |
| 14 | `time-off/index` | Unlabelled X for cancel, no confirmation; ISO dates; lists unpaginated | Tables; status tabs for My requests; confirm cancel; responsive form; calendar-day count | forms | lists | confirm-dialog | L | Request, cancel, decide | TIME-006 | 3 |
| 15 | `todo-lists/index` | Lists pile up; completed items never collapse; duplicate icon | Status filter (client-side over `statusOptions`); collapse completed; distinct icons | todo-list-card | page | filter-bar | L | CRUD, promote | TODO-001/003 | 3 |
| 16 | `team-capacity/index` | Cells not focusable; no sort or search | Focusable cells, sort by load, name search (client-side) | heatmap | heatmap | — | L | Week navigation | TIME-008 | 3 |
| 17 | `availability/index` | Errors not rendered; no pending state | FormField errors, loading button, results table | — | page | data-table | L | Query params | TIME-007 | 3 |
| 18 | `setup/*` ×3 | Copy-pasted trio; no search; no CTA in empty state | One config-driven page; table; EmptyState with CTA; ConfirmDialog | forms | 9 files → shared page | — | M | CRUD + pagination for each of the 3 | SETUP-001 | 3 |
| 19 | `teams/index`, `teams/edit`, `no-team` | Role change without confirmation; raw red danger zone; overflow at 375px; duplicate View/Edit icons | Members table; confirm role change; tokens; whole row as link; current-team badge | team modals | pages | confirm-dialog | M | Team feature tests; anti-escalation paths | TEAM-001…004 | 3 |
| 20 | `settings/*` | 2FA disable and avatar removal unconfirmed; raw green; long security page | Horizontal tabs below lg; confirmations; tokens | manage-* | layout | — | L | 2FA, passkeys, avatar | LAYOUT-007, SET-001 | 3 |
| 21 | `auth/*`, `admin/auth/login` | Positive tabIndex; status below form; unlabelled OTP and recovery inputs | Natural tab order; Alert above form; labels; tokens | layouts | pages | — | L | Login, 2FA, passkey, reset | LAYOUT-008, A11Y-005 | 3 |
| 22 | Error pages | None exist | 403/404/419/500/503 in the app shell | — | `bootstrap/app.php` render hook | `pages/error` | M | Each status renders; no stack traces | CMP-017 | 3 |

## M. Micro-level tasks

See `tasks/`. Every task follows the brief's 21-field template, including feasibility. Files:
- `tasks/00-foundation.md`: TEST, FIX, DEP, DS
- `tasks/01-shell-and-components.md`: LAYOUT, NAV, CMP
- `tasks/02-modules.md`: DASH, PROJ, TASK, MEET, TODO, TIME, TEAM, USER, SET, SETUP, FORM, RPT
- `tasks/03-quality.md`: RESP, A11Y, PERF, CLEAN, QA, BE

## N. Task Dependencies

```
UI-TEST-001 (baseline) ─┬─► every implementation task (before/after evidence)
UI-TEST-002/003 ────────┘
UI-FIX-* ── independent; can go first (small, high value)
D-3 ─► UI-DEP-001 ─► UI-CMP-009 (confirm), UI-CMP-012 (tabs), UI-CMP-018 (popover…)
D-3 ─► UI-DEP-002 ─► UI-NAV-001 (palette), UI-TASK-008 (combobox)
D-1/D-2 ─► UI-DS-002 ─► UI-DS-003 ─► UI-DS-004 ─► UI-DS-005 ─► UI-DS-006…011
UI-DS-* ─► UI-CMP-001…017 ─► module tasks
UI-LAYOUT-001 ─► 002 ─► 003 ─► 004 ─► 005 ; UI-CMP-003 ─► UI-LAYOUT-005
UI-CMP-005/006/007 ─► every table task (PROJ-001, USER-*, SETUP-001, TIME-*)
UI-CMP-012 ─► UI-PROJ-003 ─► UI-PROJ-004…011, UI-TASK-003/004, UI-PERF-002
UI-CMP-009 ─► every "confirm" task (A11Y-003 sweep closes the remainder)
UI-DS-009 (FormField) ─► UI-FORM-001…004
Module tasks ─► UI-RESP-*, UI-A11Y-* sweeps ─► UI-PERF-* ─► UI-CLEAN-001 ─► UI-QA-001
UI-BE-* ─ separate backend track; each unblocks only the optional items that name it
```

Recommended execution order:
1. TEST-001, then FIX-001…013, DEP-001, DS-001…011.
2. CMP-001…017, then LAYOUT/NAV.
3. DASH, PROJ, TASK.
4. MEET, TODO, TIME.
5. USER, TEAM, SET, SETUP.
6. FORM, RPT.
7. RESP, A11Y, PERF, CLEAN, QA.

## O. Testing Strategy

| Layer | Tool | What | When |
|---|---|---|---|
| Static | `npm run types:check`, `npm run check` (vite-plus lint/format), `vendor/bin/pint --dirty --format agent`, `vendor/bin/phpstan analyse` | Types, lint, PHP style/static | Every task |
| Build | `npm run build` (into a scratch output dir per D-5, or reverted) | Compiles; bundle size diff | Every task |
| PHP feature | `php artisan test --compact <paths>` on MySQL socket (RULES.md §9) | Page contracts (`assertInertia` component + props), authorization, the CRUD endpoints the page uses | Every task touching a page; new `assertInertia` tests where missing (UI-TEST-003) |
| Visual | Playwright via `npx -p playwright@1.48` (MEMORY.md:139), script under `tests/ui/` (UI-TEST-001) | Screenshots at 375/768/1024/1440 × light/dark for affected pages; before/after comparison | Every visual task |
| Accessibility | axe-core injected by the same script, plus manual keyboard pass | 0 new serious/critical violations; tab order; focus visible; screen-reader names | Every task |
| Component (optional D-4) | Vitest + Testing Library | Patterns (`ConfirmDialog`, `DataTable`, `FormField`, `lib/format`) | Tasks that create patterns |
| Manual functional | Real browser on the running dev server (don't start a second one, MEMORY.md:117) as **Team Lead** and **Member** (and an Admin for `/admin`) | Create/edit/delete/view/filter/paginate flows on the affected page; partial reload visibly refreshes | Every task |

Seeded users and roles must match the environment's seeders. Requires verification of which seeded accounts to use per role; noted in UI-TEST-001.

## P. Regression Strategy

For every task, in this order:
1. **Contract check.** Diff the page's props usage, the `only: [...]` keys, route imports (Wayfinder) and `can*` usage before and after. Any difference must be intended and listed in the task.
2. **Automated.** Run the static suite, the affected PHP feature tests plus `tests/Feature/TeamPermissionEnforcementTest.php`, and the build.
3. **Visual.** Compare screenshots for the affected pages at 4 widths × 2 themes. Unrelated pages are sampled (dashboard, projects/show) to catch token ripple.
4. **Functional smoke.** Log in as Team Lead and as Member. For each module touched: create, edit, delete, reorder/move, filter, paginate, and check that the list refreshes after each mutation.
5. **Accessibility.** Keyboard-only pass of the changed UI; axe shows no new violations.
6. **Performance.** Bundle delta (entry + page chunk) within budget: ≤ +10 KB gzip per new primitive, and 0 for refactors. Request count per interaction unchanged (network tab or Playwright request log).
7. **Rollback readiness.** One task = one commit, so rollback is `git revert <sha>`. No destructive git commands.

**Standing regression checklist (named `R-STD` in tasks):** login and team switch; sidebar items per role; dashboard loads; projects list filters and pagination; workspace Board move and List filter; task create, edit and delete; meeting create; time-log start/stop; timesheet submit; admin users list. About 10 minutes.

## Q. Approval & Commit Workflow

```
You approve the plan (and decisions D-1…D-9)
  └─► I announce the next task ID and restate its scope
       └─► You approve that task
            └─► I implement ONLY that task
                 └─► I run: static → tests → build → screenshots/axe → manual smoke (R-STD + task tests)
                      └─► I show: diff summary, test output, screenshots, bundle delta, anything skipped or failed
                           └─► You approve → I commit:  ui: implement <TASK-ID> <short description>
                                (plus the Co-Authored-By trailer; no public/build unless D-5 says otherwise)
                                └─► I stop and wait for your approval of the next task
```

Git safety:
- Before the first implementation I run `git status`, `git diff` and `git log -10 --oneline`, and after that before every commit.
- Only the task's files are staged, by explicit path.
- Your uncommitted `TASKS.md` deletion and `TASKS-old.md` are never staged.
- No `reset --hard`, `clean`, `checkout .` or `restore .`.
- No amends, no force pushes, no pushes unless you ask.

---

## Task Statistics

Counted from the task files. Categories are those in the brief, and each task is counted once.

```
Total Tasks:            136
Design System:           11   (UI-DS-001…011)
Dependencies:             2   (UI-DEP-001…002)
Layout:                   8   (UI-LAYOUT-001…008)
Navigation:               1   (UI-NAV-001)
Shared components:       18   (UI-CMP-001…018)
Dashboard:                4   (UI-DASH-001…004)
Projects:                11   (UI-PROJ-001…011)
Tasks:                   11   (UI-TASK-001…011)
Meetings:                 4   (UI-MEET-001…004)
To-dos / My Day:          4   (UI-TODO-001…004)
Time & capacity:          8   (UI-TIME-001…008)
Teams:                    4   (UI-TEAM-001…004)
Users (admin):            4   (UI-USER-001…004)
Settings:                 1   (UI-SET-001)
Setup:                    1   (UI-SETUP-001)
Forms:                    4   (UI-FORM-001…004)
Tables:          (covered by CMP-005…008 + 9 module table tasks; not double-counted)
Reports:                  2   (UI-RPT-001…002)
Responsive:               3   (UI-RESP-001…003)
Accessibility:            6   (UI-A11Y-001…006)
Performance:              4   (UI-PERF-001…004)
Cleanup:                  1   (UI-CLEAN-001)
Behavioural fixes:       13   (UI-FIX-001…013)
Testing / QA:             4   (UI-TEST-001…003, UI-QA-001)
Backend (separate track): 7   (UI-BE-001…007)
```
