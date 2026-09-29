# UI/UX Modernization — Phase 1 Audit (A–H)

Date: 2026-09-29 · Scope: whole repository · Mode: read-only analysis. No application code was changed.
Paths are relative to `resources/js/` unless they start with `app/`, `routes/`, `resources/` or `docs/`.

## 0. Ground truth that corrects the brief

| Brief assumption | Actual (verified) |
|---|---|
| Vue + PrimeVue | **React 19 + Inertia 3 + shadcn/ui (Radix, "new-york") + Tailwind 4 + lucide-react + sonner** (`package.json`, `components.json`). Vue is ruled out by ADR-001/012. No PrimeVue: §29 of the brief does not apply; the equivalent audit is of `components/ui/*` (shadcn). |
| MySQL | MySQL in dev/test; a recent commit targets PostgreSQL migrations (`d6eb978`). UI work is DB-agnostic. |
| `CLAUDE.md`, `PLANNING.md`/`PLANING.md` | **Not found.** `AGENTS.md`/`CLAUDE.md` are gitignored (`.gitignore:31,39-40`) but cited by `RULES.md` and by the tasks file ("new dependencies need approval"). Authoritative docs present: `RD.md`, `ARCHITECTURE.md`, `RULES.md`, `DESIGN.md` (database design, not visual), `MEMORY.md`, `docs/*.md`. |
| `TASKS.md` | Deleted in the working tree (uncommitted); `TASKS-old.md` (untracked) is byte-identical to `HEAD:TASKS.md`. Left untouched. `ALP-RTM_TASKS.md` and `Mother-At-Work_TASKS.md` belong to **other projects**. |
| Roles like Software Engineer/Analyst/Designer | Team roles: `team_lead`, `member` + admin-defined custom roles. Project member roles: owner, manager, lead, developer, designer, qa_engineer, devops, viewer, client. Task assignment roles: assignee, reviewer, qa, watcher. Platform admin is a separate guard. |

Build and tooling: `npm run build` (vite-plus `vp`), `npm run types:check`, `npm run check`; `composer test` (pint + phpstan L7 + tests). PHP tests must run on MySQL over a socket (RULES.md §9). **84 PHP test files, 32 using `assertInertia`; 0 frontend tests.** `public/build` **is committed**. Wayfinder output (`resources/js/actions|routes|wayfinder`) is gitignored and generated.

---

## A. Full Application UX Audit

### A.1 Module inventory

| Module | Purpose | Primary users | Pages | Create / Edit | List | Details | Related | Permissions | Key components | Main UX problems | Priority |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Portfolio dashboard | Health of the team's open projects | Team lead, members | `dashboard` | — | projects (unpaginated) | — | Projects | `dashboard.view` | `portfolio-health-bar` | No page title; KPI tiles are dead ends; health badge and bar use different colour systems; nothing personal | P1 |
| My Day | Personal daily digest | Everyone | `my-day/index` | Modal (list/item) | lists, assigned items (limit 50, silent) | — | To-dos, Tasks, Meetings | `my-day.view` | `todo-list-card`, `assigned-checklist-items` | "Meeting actions" card is **hardcoded placeholder** (`my-day/index.tsx:106-115`); duplicates My To-Dos; GET generates data | P1 |
| My To-Dos | Personal/daily/generated lists | Everyone | `todo-lists/index` | Modal | all lists + items, no filter | — | Tasks (promote) | `todos.manage` | `todo-list-card`, `promote-todo-item-modal` | Old daily lists pile up; completed items never collapse; same icon for two actions | P2 |
| Projects | Team project portfolio | All; managed by leads | `projects/index` | Modal (`project-form-modal`, the best form in the app) | paginated 15; filters status/priority/health | → workspace | Tasks, Members… | `projects.*`, per-row `can_update` | `project-form` | Rows-as-cards, no columns/search/sort; **Update gated by `projects.create` instead of `can_update`** (`projects/index.tsx:250`); empty state ignores filters | P1 |
| Project workspace | Everything about one project | Project members | `projects/show` (9 tabs) | Modals (ADR-013) | tasks, modules, members, milestones, sprints, allocations, activity | Tabs | Tasks, Time | project policy, `canManageProject` | `kanban-board`, `task-list-view`, `module-tree`, `*-list`, `burndown-chart` | **Tab bar overflows/clips at 375–768px** (`show.tsx:195`); tab not in URL (`:146`); 27-prop payload for all tabs; 646-line page | P1 |
| Tasks | Work items, board, list, detail | Contributors | `projects/tasks/show` + board/list tabs | Modal (`task-form-modal`, 572-line form) | board (no DnD), list (client filters, unpaginated) | Full page | Assignments, dependencies, checklist | TaskPolicy, `can_change_status`, `canManageTask` | `task-form`, `task-dependency-editor`, `task-checklist` | **Task modal cannot scroll** (Save unreachable on short screens); task page cannot change status/assignees; "Overdue only" counts done tasks (`task-list-view.tsx:83`); no search on dependency picker | P1 |
| Meetings | Schedule, agenda, minutes, actions, timer | All; organizers | `meetings/index`, `meetings/show` | Modal | paginated 15; status/type | Full page, 7 stacked cards | Projects, To-dos | MeetingPolicy + `can{}` | `agenda-list`, `minutes-editor`, `meeting-timer`, `meeting-action-items` | Past and future mixed; **Publish can send unsaved minutes** (`minutes-editor.tsx:50-61`); timer buried at the bottom | P2 |
| Time logs & timer | Record effort | Everyone | `time-logs/index` | Modal | last 30 days, unpaginated | — | Timesheet | `time-logs.manage` | `time-log-*` | No grouping or totals; delete unconfirmed; form not responsive; errors not shown for 2 fields | P2 |
| Timesheet | Weekly grid & submit | Everyone | `timesheet/index` | — | grid table | — | Approvals | `timesheet.view/submit` | — | ISO dates; no "This week"; Submit has no confirmation or explanation | P2 |
| Timesheet approvals | Approve/reject submitted logs | Leads/managers | `timesheet-approvals/index` | Decide modal | unpaginated cards | — | Time logs | policy `decide`, `canApproveTimesheets` | `decide-time-log-modal` | One-by-one only; no reason on reject; title flickers on close | P2 |
| Time off | Request/decide leave | Everyone; approvers | `time-off/index` | Modal | own + pending, unpaginated | — | Capacity | `time-off.*` | `time-off-*` | Cancel is an unlabelled X with no confirmation; no day count | P3 |
| Find available people | Capacity search by date range | Team leads | `availability/index` | — | results | — | Capacity | `availability.view` | — | Validation errors never rendered; results are yes/no only | P3 |
| Team capacity | 14-day heatmap | Team leads | `team-capacity/index` | — | members × days | — | Allocations | `team-capacity.view` | `heatmap` | Most polished page; cells not focusable; no sort/search | P3 |
| Setup | Scopes (global), task types (global), labels (team) | Setup managers (**Member role has it by default**) | `setup/*/index` ×3 | Modal | paginated 15/7/15 | — | Tasks, Modules | `setup.manage` | 9 near-identical files | Copy-pasted trio (task-types still uses `editingScope` names) | P3 |
| Teams (settings) | Team name, members, invitations | Team lead | `teams/index`, `teams/edit`, `no-team` | Inline + modals | members, invitations | — | Admin | TeamPolicy | 6 team modals | Role change applies instantly, no confirmation; danger zone uses raw red; rows overflow at 375px | P3 |
| Account settings | Profile, security (2FA, passkeys), appearance | Everyone | `settings/*` | Forms | passkeys | — | — | auth | `manage-two-factor`, `manage-passkeys` | 2FA disable and avatar removal unconfirmed; raw green status text | P3 |
| Platform admin | Users, teams, roles, schedule, holidays, admins | Platform admins (no RBAC) | `admin/*` ×9 | **Inline always-open forms per row** | users/teams paginated 20 | — | Everything | `auth:admin` only | none shared | Separate top-bar shell with **no mobile nav**; `window.confirm`; many unconfirmed deletes; silent successes; 508-line users page | P2 |
| Auth | Login, register, reset, 2FA, verify | Everyone | `auth/*`, `admin/auth/login` | Forms | — | — | — | guest | `passkey-verify` | Positive `tabIndex` breaks tab order; status message below the form; unlabelled OTP/recovery inputs | P3 |
| Not built | Git integration (on hold), comments/mentions, attachments, in-app notifications, global search, reports area, meeting calendar, Gantt | — | **Not found in current repository** (schema only for Git/comments/attachments) | — | — | — | — | — | — | Out of scope for UI modernization; noted so no UI is designed for them | — |

### A.2 User roles (verified: `app/Services/Teams/TeamAccessControl.php`, `app/Enums/TeamModulePermission.php`)

| Role | Navigation | Allowed actions | Dashboard | Project / Task access | Team | Settings |
|---|---|---|---|---|---|---|
| **Team Lead** (all 28 permissions) | Dashboard, My Day, Projects, Meetings, My To-Dos, Time Off, Time Logs, Timesheet, Timesheet Approvals, Find Available People, Team Capacity, Setup ×3 | Manage every project/meeting (`*.view-all` also grants manage); decide time off and timesheets | All open team projects | All | Rename, delete, members, invitations; cannot leave | Own account |
| **Member** (13 permissions) | All except Find Available People and Team Capacity; **Setup visible**; Approvals only if they lead/manage a project | Create projects/meetings; manage projects where owner/manager/lead or `project_lead_id`; change status on own assigned tasks | Projects where an active member | Member projects | Read only | Own account |
| **Custom team role** | Whatever the admin grants (starts empty) | Same permission catalogue | Depends | Depends | Depends | Own account |
| **Project roles** | — | owner/manager/lead manage project; others view + status on own tasks; client/viewer read (client: project-only per ADR-008) | — | Per project | — | — |
| **Platform Admin** (`admin` guard, no roles) | Admin top bar: Dashboard, Users, Teams, Team roles, Work schedules, Holidays, Admins | Everything in `/admin` | Team counts | — | Create teams, assign leads | Admin profile |

The frontend receives `teamAccess` (a flat permission list), `canApproveTimesheets`, `currentTeam`, `teams` and `teamHome` as shared props (`app/Http/Middleware/HandleInertiaRequests.php:50-67`). Navigation uses `useTeamAccess().can()` (`hooks/use-team-access.ts`, `components/app-sidebar.tsx:57-183`). **The redesign must keep every gate exactly as it is.**

### A.3 Cross-cutting product UX problems

1. **No tables anywhere.** Lists are bordered `div` rows without column headers (`projects/index.tsx:212-216`, admin, setup, meetings). Scanning 15–20 records is slow.
2. **No search and no sort** in the backend or UI (the header Search button in the unused `app-header.tsx:215-221` does nothing).
3. **Inconsistent destructive safety:** 12 near-identical confirm modals on one side; ≥16 actions deleting on a single click on the other (checklist/to-do/action/agenda items, attendees, dependencies, assignments, time log, time-off cancel, holiday, team role, user-team unassign, team lead removal, 2FA disable, avatar removal), plus `window.confirm` for user delete.
4. **Silent outcomes:** several admin `useHttp` successes are not toasted; 0/12 delete modals handle errors; 37 `router.reload` calls show no pending state.
5. **Enum text is mangled on the client:** `.replace('_',' ')` in 21 places (first underscore only, lowercase), even though server `*Options` labels exist (violates RULES.md §3).
6. **Dates:** raw ISO strings or browser-locale `toLocaleString()`; no shared formatter; three different duration formats.
7. **Detail pages lose their nav highlight** (`nav-main.tsx:29` exact match).

---

## B. Existing Design-System Audit

| Area | Finding | Evidence |
|---|---|---|
| Tokens | Stock shadcn neutral oklch tokens via `@theme` (not `@theme inline`); **no brand hue** (`--primary` near-black) | `resources/css/app.css:10-92` |
| Custom tokens (good, keep) | `--status-good/warning/critical`, `--heatmap-1..7`, `--chart-1..5`, contrast-validated | `app.css:54-73,102-136` |
| Token bugs | Dark `--sidebar-primary` = `--sidebar-primary-foreground` (`:176-177`); light `--destructive-foreground` = `--destructive` (`:91-92`) and misused as "red text" | `app-logo.tsx:11` workaround |
| Missing semantic tokens | No success / warning / info UI tokens → raw `text-green-600` for success messages, blue palette in invitation alert | `auth/login.tsx:128`, `team-invitation-alert.tsx:14-17` |
| Palette discipline | 74 raw palette classes in 20 files vs 300+ semantic uses (good baseline) | worst: `team-invitation-alert`, `appearance-tabs`, `teams/edit`, `delete-user` |
| Hardcoded colours | Progress bar `#4B5563` (`app.tsx:40-42`); overlay `bg-black/80` (`ui/dialog.tsx:39`); label default `#6b7280` | |
| Typography | Instrument Sans 400/500/600 (bunny.net); no scale tokens; `text-sm` 177×, `text-xs` 53×; `text-[10px]`/`[11px]` in burndown; `Heading` always `<h2>`; **no visible `<h1>` on any page**; dashboard has no heading | `app.blade.php:37`, `components/heading.tsx:12-18` |
| Spacing | Page wrapper string `flex h-full flex-1 flex-col gap-6 p-4` copy-pasted in 17 pages; `Heading mb-8` inside `items-center` header rows misaligns actions | `projects/index.tsx:88` |
| Radius / shadow | `--radius .625rem`; flat already (few shadows) | `app.css:118` |
| Dark mode | Class-based, cookie + localStorage + system; inline no-flash background duplicated in blade | `app.blade.php:2-31`, `hooks/use-appearance.tsx` |
| Status mapping | **8 duplicate status→variant maps** with conflicting semantics (completed solid vs outline; cancelled red; at_risk grey) ; priority is plain text | `time-off-request-list.tsx:22`, `milestone-list.tsx:17`, `allocation-list.tsx:27`, `meetings/index.tsx:38`, `meetings/show.tsx:49`, `dashboard.tsx:24`, `projects/index.tsx:42`, `projects/show.tsx:389` |

## C. UI / Component Inventory

### C.1 Primitives (`components/ui/`) — consumers outside `ui/`

| Component | Consumers | Reuse? | Needs | Duplicate? |
|---|---|---|---|---|
| button | 107 | Yes | `loading` prop; destructive text token; coarse-pointer size | 8 files use native `<button>` |
| badge | 29 | Yes | success/warning/info/neutral + subtle style | — |
| card | 23 | Yes | `StatCard` composite | stat cards inlined ×4 places |
| dialog | 44 | Yes | max-height + scroll body + sticky footer (only project form has it) | open-state boilerplate ×16 |
| select (Radix) | 28 | Yes | keep `'none'` sentinel | — |
| input / label / textarea | 40 / 44 / 17 | Yes | `aria-invalid`/`aria-describedby` wiring via `FormField` | — |
| checkbox | 14 | Yes | label association | — |
| tooltip | 12 | Yes | remove nested providers (`app-header.tsx:224`, `heatmap.tsx:272`) | — |
| alert | 2 | Yes | info/success/warning variants | hand-rolled in `team-invitation-alert` |
| spinner | 8 (auth only) | Yes | use in app buttons | `LoaderCircle` in forgot-password |
| skeleton | 0 | Yes | patterns | — |
| dropdown-menu, avatar, sidebar, sheet, breadcrumb, separator, input-otp, sonner | 1–7 | Yes | — | — |
| navigation-menu, collapsible, toggle, toggle-group, icon, placeholder-pattern | 0–1 | Dead or near-dead | Remove in UI-CLEAN-001 unless used by the plan | — |

**Missing:** table, pagination, tabs, alert-dialog, popover, command/combobox, switch, radio-group, scroll-area, progress, date picker. Composite patterns are also missing: page header, page container, empty state, stat card, filter bar and chips, status/priority badge, confirm hook, form field, and shared formatters.

### C.2 Feature components (reviewed)

- `components/projects/*` (38 files), `meetings/*` (13), `todo-lists/*` (8), `time-logs/*` (5), `time-off/*` (5), `setup/*` (9), `team-capacity/heatmap`, team modals (6), auth/security (8).
- **42 `*-modal.tsx`**: all `ui/dialog` + Inertia `useHttp` (JSON). 12 of them are ~80-line delete modals whose diffs are only nouns (≈960 lines).
- **Pagination copied in 7 pages** with `dangerouslySetInnerHTML` (`setup/*` ×3, `admin/users`, `admin/teams`, `projects/index`, `meetings/index`).
- Duplicated helpers: `formatMinutes` ×3, `formatElapsed` ×2, `formatHours` ×2 (different rounding), `formatDuration`, `formatRange`.
- Dead shell code: `components/app-header.tsx` (still links to the Laravel starter-kit repo), `layouts/app/app-header-layout.tsx`, `components/nav-footer.tsx`, `layouts/auth/auth-card-layout.tsx`, `layouts/auth/auth-split-layout.tsx`.
- Largest files: `pages/projects/show.tsx` 646, `task-form.tsx` 572, `project-form.tsx` 530, `pages/admin/users/index.tsx` 508, `heatmap.tsx` 466, `kanban-board.tsx` 429.

### C.3 Charts

All hand-built, no chart library: burndown (SVG), heatmap (HTML table), portfolio health bar (flex), estimated-vs-actual (plain table). **Keep this approach.** It has no dependency cost, and the fixes needed are responsiveness and accessibility, not a library.

## D. Navigation / Information Architecture Audit

| Surface | Current | Problem |
|---|---|---|
| App sidebar (`app-sidebar.tsx:57-183`) | One "Platform" group with up to 11 items + "Setup" group | Flat list mixes personal work (My Day, To-Dos, Time Logs, Timesheet, Time Off), team work (Projects, Meetings) and management (Approvals, Capacity, Availability). No grouping = poor scanning. |
| Active state | Exact URL match (`nav-main.tsx:29`) | Project/meeting/task detail pages highlight nothing. `isCurrentOrParentUrl` exists (`hooks/use-current-url.ts`) but is unused here. |
| Header (`app-sidebar-header.tsx:11`) | Trigger + breadcrumbs | Padding inverted (`px-6 md:px-4`); breadcrumbs never collapse; no page actions or search slot. |
| Breadcrumbs | Static per page `.layout` (29 pages) | Fine; keep. Long trails overflow on mobile. |
| Project workspace | 9 hand-rolled tabs in `useState` | Not in URL; overflow on small screens; no keyboard arrows; 9 tabs at one level. |
| Settings (`layouts/settings/layout.tsx`) | Vertical nav; stacks above content < lg (~180px) | No `aria-current`; team pages render under a "Profile and account settings" heading. |
| Admin (`layouts/admin-layout.tsx`) | Separate top bar, 7 text links | **No mobile menu** (overflows < 768px); no icons, breadcrumbs, `aria-current`; default Laravel logo. |
| Team switcher | Sidebar dropdown | Two requests per switch; slug substring bug (`team-switcher.tsx:350`); switching to current team still switches. |
| Terminology | "Platform" group label, "My To-Dos" vs "My Day", "Find Available People" vs "Team Capacity" | Group label means nothing to users; two overlapping personal pages. |
| Hidden functionality | Task status/assignee change only on the Board; meeting start/complete transitions not exposed (no route); sprint `sprint_id` accepted by the meeting controller but no field | The first is UI-fixable; the others need backend decisions. |

## E. Responsive Audit (375 / 768 / 1024 / 1440)

- 27 of 40 pages have **no** breakpoint prefix. Header rows are `flex justify-between` without wrap.
- **Overflows at 375px:** project tab bar (`projects/show.tsx:195`, and it is *clipped* by `SidebarInset overflow-x-clip`), admin top nav (`admin-layout.tsx:127-141`), team member rows (`teams/edit.tsx:174`), dependency rows (`task-dependency-editor.tsx:107`), long meeting URLs (`meetings/show.tsx:168-175`), fixed `w-56` admin inputs.
- **Dialogs:** only the project form scrolls; the task form (≈15 fields) and meeting form exceed the viewport on phones and short laptops.
- **Cramped:** 2-column grids without `sm:` in time-log and time-off forms; list filters of 6× `w-40` (≈4 rows on phone); module tree at depth ≥3.
- **Charts:** burndown labels render at ≈5px on phones (fixed viewBox + `text-[10px]`); hover-only tooltips (burndown, health bar) are unusable on touch.
- **Good:** kanban scrolls horizontally with 288px columns; heatmap has a sticky first column; timesheet table scrolls within its card; auth pages are fine at every width.
- 1440/1920: content is fluid and full-width. That is fine for data pages, but reading-width limits are missing for detail pages (task description, minutes).

## F. Accessibility Audit

| Check | Status | Evidence |
|---|---|---|
| Accessible names on icon buttons | **Fail**: ~40 unnamed across modules | `todo-list-card.tsx:112,120,201`, `time-log-list.tsx:83-100`, `time-off-request-list.tsx:89-107`, module/member/milestone/sprint/allocation lists |
| Form errors linked to inputs | **Fail**: 0 `aria-invalid`, 1 `aria-describedby` | `input-error.tsx` |
| Checkbox labels | Fail in to-do, agenda, action items, work-schedule days | `assigned-checklist-items.tsx:56`, `work-schedules/index.tsx:167` |
| Headings | Fail: no visible `h1`; dashboard has no heading | `heading.tsx` |
| Tab widget | Partial: role/aria-selected only | `projects/show.tsx:194-217` |
| Tab order | Fail: positive `tabIndex` on both login pages; password reveal `tabIndex=-1` | `auth/login.tsx:71,118`, `admin/auth/login.tsx:32-66`, `password-input.tsx:27` |
| Live regions | None (`aria-live` 0); timer text re-renders every second without announcement strategy | `time-log-timer.tsx` |
| `aria-current` in navs | 0 | sidebar, settings, admin |
| Destructive confirmations | Esc/outside click can dismiss (Dialog not AlertDialog), which is acceptable. **Unconfirmed deletes** are the real issue | see A.3 |
| Colour-only signals | Label colour dots; health bar segments not focusable; priority has no visual at all | `labels/index.tsx:56`, `portfolio-health-bar.tsx:53-63` |
| Contrast | Tokens are shadcn defaults (pass); raw `text-green-600` on white ≈3.3:1 (**fail** for small text); user label colours behind `text-primary-foreground` are unguarded | `task-form.tsx:335-356` |
| Heatmap | Good: `role="img"` + full aria-label per cell, non-colour encodings; not focusable | `heatmap.tsx:200-203` |
| Charts | Burndown has `aria-label` but no data alternative | `burndown-chart.tsx:74-75` |
| Reduced motion | Not handled globally (tw-animate-css animations on overlays) | `app.css` |

## G. Performance Audit (baseline from committed `public/build`)

| Item | Baseline | Note |
|---|---|---|
| Page code-splitting | Yes, lazy per page (87 JS chunks) | `@inertiajs/vite` default `lazy: true` |
| Shared vendor chunk `utils-*.js` | **373 KB** raw | react-dom, Inertia, Radix |
| Entry `app-*.js` | **225 KB** raw | eager-imports all four layouts + sidebar + sonner (`app.tsx:5-8`) |
| CSS | 106 KB raw | |
| Largest page `projects/show` | 92 KB | all 9 tabs bundled together |
| Server payload `projects/show` | ~30 props, all computed on every visit regardless of tab | `ProjectController.php:134-286` (every task + checklist, capacity, over-allocation detection) |
| `tasks/show` | `taskCandidates` = every task in the project on each view | `TaskController.php:102-111` |
| N× work | Team capacity runs availability per member; approvals run a policy check per row; admin teams calls `owner()` per row | `TeamCapacityController.php:43-56`, `TimesheetApprovalController.php:44`, `Admin/TeamController.php:39` |
| Deferred / WhenVisible / prefetch | 0 / 0 / 4 | |
| Re-render hot spots | Timers tick every second at component level; `members.flatMap` per render in capacity | `time-log-timer.tsx`, `team-capacity/index.tsx:119` |
| Dependencies | Light: no chart, date, table or DnD libs; React Compiler on | `vite.config.ts:17-19` |

Raw sizes are listed; gzip sizes will be recorded in UI-TEST-001 with the same build command so before/after is comparable.

## H. UI/UX Risk Register

| ID | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R1 | Partial-reload keys (`only: [...]`) drift when pages are refactored → stale UI after mutations (MEMORY.md:147) | High | High | Treat `only` lists as a contract; list them in each task's regression checklist; real-browser check after every modal refactor (MEMORY.md:239) |
| R2 | Converting `useHttp` modals to `router`/`useForm` flips the `X-Inertia` response branch (JSON → redirect) | Medium | High | Rule: keep `useHttp` in modals; composites wrap it, they don't replace it |
| R3 | Radix `'none'` sentinel for optional selects removed by a "cleaner" component | Medium | High | Documented in design system §7; regression test on each optional select |
| R4 | Date math regressions (UTC+6 browser vs UTC app) when centralising formatters | Medium | High | `lib/format.ts` uses `Date.UTC`; unit fixtures around midnight/week boundaries |
| R5 | Authorization regression: redesigned actions shown or hidden differently from today | Medium | Critical | Every task lists the `can*` props it touches; PHP authorization tests are unchanged; manual check as Team Lead and Member |
| R6 | Wayfinder route names/params changed | Low | High | No route renames in UI tasks; run `npm run build` after `wayfinder:generate` (MEMORY.md:119) |
| R7 | Token changes (primary hue, radius) ripple across 107 button consumers and the dark theme | High | Medium | Tokens-first tasks with screenshot baseline diff at 4 widths × 2 themes |
| R8 | New dependencies (Radix tabs/alert-dialog/popover, cmdk) grow bundle | Medium | Low–Med | Explicit approval D-3; bundle budget check per task (+≤10 KB gzip per primitive) |
| R9 | `public/build` is committed → UI commits either include huge diffs or production drifts | High | Medium | Decision D-5; recommended: task commits exclude `public/build`, and a separate `build` commit is made per approved batch (matches history `fbff229 build`, `08a5519 prod build`) |
| R10 | No frontend test harness → UI regressions only caught manually | High | Medium | UI-TEST-001/002 before implementation |
| R11 | Behavioural fixes hidden inside visual tasks (e.g. overdue filter, Update gating) | Medium | Medium | Separated into explicit `UI-FIX-*` tasks, each approved on its own |
| R12 | Backend security observation: default **Member** role holds `setup.manage`, and Scopes/Task Types are **global**, so any member of any team can edit them platform-wide | Requires product decision | High | Logged as UI-BE-001 (not a UI change; needs owner decision) |
| R13 | User's uncommitted `TASKS.md` deletion / `TASKS-old.md` rename | — | Low | Never touched; UI tasks live in `docs/ui-modernization/` |
| R14 | Scope creep into unbuilt modules (comments, attachments, notifications, search) | Medium | Medium | Only marked as optional backend-first recommendations |
