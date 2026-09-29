# UI Tasks — 01 Shell, Navigation, Shared Components (LAYOUT · NAV · CMP)

Shorthand is defined in `00-foundation.md`. Every task: approval before start → implement only it → test → show results → approval → commit.

---

## Phase 2 — Global layout & navigation

## [ ] UI-LAYOUT-001 — App shell structure: content overflow, header padding, page width
- **Objective:** A shell that never hides overflow and has consistent gutters.
- **Current State:**
  - `SidebarInset` uses `min-w-0 overflow-x-clip` (`layouts/app/app-sidebar-layout.tsx:14`), which clips wide content instead of fixing it.
  - The header padding is inverted: `px-6 md:px-4` (`app-sidebar-header.tsx:11`).
  - There's no page container; the page wrapper string is copy-pasted in 17 pages.
- **Problem:** Hidden content (the project tabs) and inconsistent gutters.
- **Proposed Change:**
  - Replace the clip with a proper `min-w-0` layout, keeping clipping only where needed.
  - Fix the header padding to `px-4 md:px-6`.
  - Leave page wrappers as they are for now; they're replaced in UI-CMP-003.
- **UX Reason:** No lost content; visual rhythm.
- **Files/Components Affected:** `app-sidebar-layout.tsx`, `app-sidebar-header.tsx`, `app-shell.tsx`.
- **Routes Affected:** All app pages (visual only).
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-005.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y L · Regr M (exposes existing overflows) · Mobile M · Scope S.
- **Implementation Steps:**
  1. Remove the clip.
  2. Take shots of every page at 375.
  3. List every newly visible overflow; each is fixed in its own module task or RESP-001.
  4. If an overflow is severe (e.g. project tabs), keep a local `overflow-x-auto` on that element instead.
- **Acceptance Criteria:** No page-level horizontal scroll at 375px, with local scroll containers where needed. Header gutters are 16px on mobile and 24px on desktop.
- **UI Tests:** Shots at 4 widths.
- **Functional Tests:** Sidebar collapse persists (the `sidebarOpen` cookie).
- **Responsive Tests:** 375/768/1024/1440.
- **Accessibility Tests:** No focus traps.
- **Regression Tests:** R-STD.
- **Performance Checks:** None.
- **Risks:** Newly visible overflow; mitigated by per-element scroll containers.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-LAYOUT-001 app shell overflow and gutters`

## [ ] UI-LAYOUT-002 — Sidebar information architecture (grouped navigation)
- **Objective:** Implement the J.1 grouping.
- **Current State:** A single "Platform" group with up to 11 items, plus Setup (`components/app-sidebar.tsx:57-183`).
- **Problem:** Personal, team and management items are mixed together; the group label is meaningless.
- **Proposed Change:**
  - Build groups Overview · Work · My work · Time · Manage · Setup.
  - `NavMain` accepts a `groups` prop.
  - Empty groups are hidden.
  - **Gates are copied verbatim** from each item.
- **UX Reason:** Faster scanning; each role sees a shorter menu.
- **Files/Components Affected:** `app-sidebar.tsx`, `nav-main.tsx`, `types/navigation.ts`.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** FIX-007.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr M (permission gating) · Mobile L · Scope S.
- **Implementation Steps:**
  1. Restructure the item config.
  2. Give each group its own `SidebarGroup` with a label.
  3. Keep the collapsed icon mode working.
- **Acceptance Criteria:** For Team Lead, Member and a custom empty role, the set of visible items is **identical** to today; only the grouping changes. Record before/after item lists in the PR notes.
- **UI Tests:** Shots for each role, expanded and collapsed.
- **Functional Tests:** Every link navigates.
- **Responsive Tests:** Mobile sheet.
- **Accessibility Tests:** Group labels are exposed; `aria-current` works.
- **Regression Tests:** `TeamPermissionEnforcementTest` passes, plus R-STD.
- **Performance Checks:** None.
- **Risks:** A gate is mis-copied. Mitigation: diff the item lists per role.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes (IA change).
- **Commit Requirement:** `ui: implement UI-LAYOUT-002 grouped sidebar navigation`

## [ ] UI-LAYOUT-003 — Header: responsive breadcrumbs and actions slot
- **Objective:** Keep the header useful at every width.
- **Current State:** `breadcrumbs.tsx` never truncates; the header has no right-hand slot.
- **Problem:** Long trails wrap or overflow at 375px.
- **Proposed Change:**
  - Below 768px, collapse the middle crumbs into a dropdown and truncate the last crumb with a tooltip.
  - Add a right slot (used by NAV-001).
- **UX Reason:** Wayfinding on small screens.
- **Files/Components Affected:** `breadcrumbs.tsx`, `app-sidebar-header.tsx`, `ui/breadcrumb.tsx`.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** LAYOUT-001.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Add the ellipsis dropdown.
  2. Truncate the last crumb.
  3. Add the slot.
- **Acceptance Criteria:** The task page trail (4 levels) fits on one line at 375px.
- **UI Tests:** Shots.
- **Functional Tests:** Every crumb navigates.
- **Responsive Tests:** 375/768.
- **Accessibility Tests:** `nav aria-label="Breadcrumb"`; the current crumb has `aria-current`.
- **Regression Tests:** The 29 pages with breadcrumbs.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-LAYOUT-003 responsive breadcrumbs`

## [ ] UI-LAYOUT-004 — Mobile navigation behaviour
- **Objective:** The mobile sheet nav works reliably.
- **Current State:** The sidebar renders as a Sheet below 768px (`ui/sidebar.tsx:181`). Whether it closes on navigation requires verification (`hooks/use-mobile-navigation.ts` exists).
- **Problem:** Potential stale open sheet; small touch targets.
- **Proposed Change:** Close the sheet on Inertia navigation, use targets of at least 40px on coarse pointers, and put the team switcher at the top of the sheet.
- **UX Reason:** Touch usability.
- **Files/Components Affected:** `ui/sidebar.tsx` (config only), `app-sidebar.tsx`, `hooks/use-mobile-navigation.ts`.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** LAYOUT-002.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Verify the current behaviour.
  2. Fix any gaps.
- **Acceptance Criteria:** Tapping an item navigates and closes the sheet; focus returns to the trigger.
- **UI Tests:** Mobile emulation.
- **Functional Tests:** Every link works.
- **Responsive Tests:** 375/390/414/768.
- **Accessibility Tests:** Focus trap inside the sheet.
- **Regression Tests:** Desktop collapse.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-LAYOUT-004 mobile navigation behaviour`

## [ ] UI-LAYOUT-005 — Skip link, one visible `h1`, focus after navigation
- **Objective:** Keyboard and screen-reader orientation.
- **Current State:** There's no skip link. No visible `h1` exists anywhere (`components/heading.tsx` always renders `h2`). The dashboard has no heading.
- **Problem:** Violates WCAG 2.4.1 and 2.4.6; users are disoriented.
- **Proposed Change:**
  - Add a "Skip to main content" link.
  - Give `<main id="content" tabIndex={-1}>` in the shell.
  - After an Inertia `navigate` event, move focus to `main` without scrolling.
  - `PageHeader` (CMP-003) renders the `h1`.
  - Pages adopt `PageHeader` in their module tasks; this task wires the shell and converts `Heading` usages that act as page titles to `PageHeader` with no visual change.
- **UX Reason:** Accessibility.
- **Files/Components Affected:** `app-shell.tsx`, `app-sidebar-layout.tsx`, `admin-layout.tsx`; `heading.tsx` untouched.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** CMP-003.
- **Feasibility:** Tech L · FE M (32 pages) · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope M.
- **Implementation Steps:**
  1. Add the skip link.
  2. Add the focus handler.
  3. Mechanically swap page-title headings.
- **Acceptance Criteria:** Every page has exactly one `h1`; Tab from page load reveals the skip link.
- **UI Tests:** Keyboard pass.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Axe `page-has-heading-one`.
- **Regression Tests:** Scroll restoration on back/forward is unaffected.
- **Performance Checks:** None.
- **Risks:** Focus moving on partial reloads. Only move it on `navigate`, not `reload`.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-LAYOUT-005 skip link headings and focus`

## [ ] UI-LAYOUT-006 — Admin shell on shared sidebar primitives
- **Objective:** A responsive admin area.
- **Current State:** `layouts/admin-layout.tsx` has a top bar with 7 inline links (`:43-57`, `:127-141`). There's no mobile menu, no `aria-label`, no breadcrumbs, and it shows the default Laravel logo.
- **Problem:** Unusable below 768px.
- **Proposed Change:**
  - Build `AdminSidebar` with `ui/sidebar`: 7 items with icons, plus the admin user menu in the footer.
  - Header with trigger and breadcrumbs.
  - The same `PageContainer`.
  - No permission logic, because admin has none.
- **UX Reason:** Consistency and mobile support.
- **Files/Components Affected:** `admin-layout.tsx`, `components/admin-sidebar.tsx` (new), admin pages (breadcrumb `.layout` props).
- **Routes Affected:** `admin/*` (visual only).
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** LAYOUT-001/002, CMP-003.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ (the `auth.user` prop is an Admin on these routes; `HandleInertiaRequests.php:40-47`) · Perf none · A11y + · Regr M · Mobile + · Scope M.
- **Implementation Steps:**
  1. Build the sidebar.
  2. Swap the layout.
  3. Add breadcrumbs to the 8 pages.
  4. Handle sidebar state separately from the user app, if the cookie is shared.
- **Acceptance Criteria:** Admin works at 375px; all 7 sections are reachable; logout works.
- **UI Tests:** Shots.
- **Functional Tests:** `tests/Feature/Admin/*` pass.
- **Responsive Tests:** 4 widths.
- **Accessibility Tests:** `aria-current`, labelled nav.
- **Regression Tests:** Admin login/logout; the user app is unaffected.
- **Performance Checks:** Admin layout chunk size.
- **Risks:** Shared `sidebarOpen` cookie semantics; verify.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-LAYOUT-006 responsive admin shell`

## [ ] UI-LAYOUT-007 — Settings layout: tabs below lg, correct heading for team pages
- **Objective:** Compact settings navigation on mobile.
- **Current State:**
  - The vertical nav stacks above the content below `lg`, using about 180px (`layouts/settings/layout.tsx:47-74`).
  - There's no `aria-current`.
  - Team pages sit under the "Profile and account settings" heading (`:42`).
- **Problem:** Wasted space; misleading heading.
- **Proposed Change:**
  - Show a horizontal scrollable tab strip below `lg` and keep the vertical nav at `lg` and up.
  - Take the heading from the active section.
- **UX Reason:** Mobile efficiency; accurate labels.
- **Files/Components Affected:** `layouts/settings/layout.tsx`.
- **Routes Affected:** `settings/*`, `settings/teams*` (visual only).
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** FIX-007.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Build the responsive nav.
  2. Map a heading to each section.
- **Acceptance Criteria:** At 375px the nav uses 48px or less of height, and each section heading is correct.
- **UI Tests:** Shots.
- **Functional Tests:** All 4 sections navigate.
- **Responsive Tests:** 375/1024.
- **Accessibility Tests:** `aria-current`.
- **Regression Tests:** Password confirmation redirect on the security page.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-LAYOUT-007 responsive settings navigation`

## [ ] UI-LAYOUT-008 — Auth layout polish
- **Objective:** Clear, consistent auth pages.
- **Current State:**
  - Status messages appear *after* the form (`auth/login.tsx:127`) and use `text-green-600`.
  - Spinners are inconsistent (`forgot-password.tsx:48` uses `LoaderCircle`).
  - The invitation alert is placed inconsistently between login and register.
  - Two layouts are unused.
- **Problem:** Messages get missed; the pages look inconsistent.
- **Proposed Change:**
  - Show status in `Alert` (success/info) above the form.
  - Use `Spinner` everywhere.
  - Place the invitation alert in the same position on login and register.
  - Show the team/app brand consistently.
- **UX Reason:** Clarity at the first touchpoint.
- **Files/Components Affected:** `pages/auth/*`, `layouts/auth/auth-simple-layout.tsx`, `pages/admin/auth/login.tsx`.
- **Routes Affected:** Auth pages (visual only).
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-011, FIX-009.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr M (login is critical) · Mobile L · Scope S.
- **Implementation Steps:**
  1. Reorder and tokenise.
  2. Take shots.
- **Acceptance Criteria:** No raw palette classes in `auth/*`; status sits above the form.
- **UI Tests:** Shots of all 7 pages.
- **Functional Tests:** `tests/Feature/Auth/*` pass; manual login, register, reset, 2FA and passkey.
- **Responsive Tests:** 375.
- **Accessibility Tests:** Status announced (`role=status`).
- **Regression Tests:** Invitation acceptance via login and via register.
- **Performance Checks:** None.
- **Risks:** Critical path. Test every flow.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-LAYOUT-008 auth page polish`

## [ ] UI-NAV-001 — Command palette (navigation and actions only) with shortcut help
- **Objective:** Keyboard navigation for power users (the skill's must-have: keyboard shortcuts).
- **Current State:** Not found. The unused `app-header.tsx:215-221` has a dead Search button.
- **Problem:** Deep navigation takes many clicks.
- **Proposed Change:**
  - ⌘/Ctrl+K opens `CommandDialog`, lazy-loaded.
  - It lists nav items the user is permitted to see (the same config as the sidebar), plus projects from shared or already-loaded props only, plus permitted create actions that open existing modals via route.
  - `?` opens a shortcuts sheet.
  - **No entity search**; that is UI-BE-002.
- **UX Reason:** Efficiency.
- **Files/Components Affected:** `components/command-palette.tsx` (new), `ui/command.tsx` (new), `app-sidebar-header.tsx` (trigger).
- **Routes Affected:** None.
- **APIs Affected:** None. Any list of projects not already in props would need backend work, so only nav items are used unless they are.
- **Database Impact:** None.
- **Dependencies:** DEP-002, LAYOUT-002/003.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf L (lazy chunk) · A11y M (focus management) · Regr L · Mobile L (trigger button visible) · Scope M.
- **Implementation Steps:**
  1. Extract the nav config into a shared module.
  2. Build the palette.
  3. Add the shortcut listener, ignoring it when focus is in inputs.
  4. Add the help sheet.
- **Acceptance Criteria:** The palette shows only permitted items; Enter navigates; Esc closes and returns focus.
- **UI Tests:** Keyboard flows.
- **Functional Tests:** Each item navigates.
- **Responsive Tests:** Trigger button at 375.
- **Accessibility Tests:** Combobox roles from cmdk; screen-reader check.
- **Regression Tests:** No shortcut conflicts in text fields.
- **Performance Checks:** Entry chunk delta is 0 (lazy).
- **Risks:** Shortcut conflicts with the browser or OS.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-NAV-001 command palette`

---

## Phase 3 — Shared components

## [ ] UI-CMP-001 — `lib/format.ts`: dates, ranges, durations, hours
- **Objective:** One formatter set, safe for UTC and local time.
- **Current State:** 15 ISO `.slice()` calls and 5 `toLocaleString()` calls. Duplicates: `formatMinutes` ×3, `formatElapsed` ×2, `formatHours` ×2 (different rounding), `formatDuration`, `formatRange`.
- **Problem:** Inconsistent formats; timezone traps (MEMORY.md:225).
- **Proposed Change:**
  - Use `Intl.DateTimeFormat` with the app locale.
  - Functions: `formatDate`, `formatDateTime`, `formatTimeRange`, `formatDateRange`, `formatRelative`, `formatMinutes` ("1h 30m"), `formatHours` (1 decimal, tabular), `formatElapsed`, and `todayLocalIso`.
  - Replace the duplicate helpers only; page-level date displays migrate in their module tasks.
- **UX Reason:** Readable, consistent data.
- **Files/Components Affected:** `lib/format.ts` (new), `meeting-timer.tsx`, `timesheet-approvals/index.tsx`, `time-log-list.tsx`, `time-log-timer.tsx`, `heatmap.tsx`, `team-capacity/index.tsx`, `projects/show.tsx`.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** TEST-002 recommended.
- **Feasibility:** Tech M (timezone) · FE L · BE none · Arch ✓ · Perf none · A11y none · Regr M · Mobile none · Scope S.
- **Implementation Steps:**
  1. Write the functions and fixtures (midnight, week boundaries, UTC+6).
  2. Swap the duplicates.
  3. Decide the `formatHours` rounding: use 1 decimal and document it.
- **Acceptance Criteria:** Unit fixtures pass; displays that used the duplicated helpers look the same, apart from the documented rounding.
- **UI Tests:** Shots of time pages.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** n/a.
- **Regression Tests:** Timesheet and capacity values unchanged.
- **Performance Checks:** None.
- **Risks:** Locale choice. It requires verification whether the app has a locale setting; default to `en-GB` unless decided.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes. Decide the locale.
- **Commit Requirement:** `ui: implement UI-CMP-001 shared formatters`

## [ ] UI-CMP-002 — `lib/status.ts` with Status, Health and Priority badges
- **Objective:** One mapping from enum value to tone and icon.
- **Current State:** There are 8 duplicate maps with conflicting semantics (listed in audit B), and priority has no visual treatment.
- **Problem:** The same status looks different across pages.
- **Proposed Change:**
  - `lib/status.ts` maps each enum in `app/Enums` (TaskStatus, ProjectStatus, ProjectHealth, MeetingStatus, MilestoneStatus, SprintStatus, AllocationStatus, ApprovalStatus, TimeOffRequest status, Priority, TaskReviewStatus, DeploymentStage) to a tone and icon.
  - `patterns/status-badge.tsx`, `health-badge.tsx` and `priority-badge.tsx` take `value` and `label`, with the label coming from options.
  - Migrate the 8 maps.
- **UX Reason:** Consistent meaning.
- **Files/Components Affected:** New `lib/status.ts` and 3 pattern files; the 8 files listed in audit B.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-007, FIX-004.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ (the tone is presentation, not a business rule) · Perf none · A11y + (icon plus text) · Regr M · Mobile none · Scope M.
- **Implementation Steps:**
  1. Read every enum's cases.
  2. Build the table and get the tone choices reviewed (you approve the table).
  3. Migrate.
- **Acceptance Criteria:** `grep` finds no local variant maps; every status shows icon, text and tone.
- **UI Tests:** Badge matrix shots.
- **Functional Tests:** n/a.
- **Responsive Tests:** Badges wrap.
- **Accessibility Tests:** Icons are `aria-hidden` and the text is present.
- **Regression Tests:** Dashboard, projects, meetings, time off, milestones and allocations.
- **Performance Checks:** None.
- **Risks:** An enum case missing from the map; fall back to neutral.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes, including the tone table.
- **Commit Requirement:** `ui: implement UI-CMP-002 status health and priority badges`

## [ ] UI-CMP-003 — `PageContainer` and `PageHeader`
- **Objective:** Standard page frame and title bar.
- **Current State:**
  - The wrapper string is duplicated in 17 pages.
  - `Heading` carries `mb-8` inside `items-center` rows, which misaligns the action buttons (`projects/index.tsx:88`).
  - Admin and settings use different wrappers.
- **Problem:** Inconsistent spacing; misaligned actions.
- **Proposed Change:**
  - `PageContainer` provides responsive padding (`p-4 md:p-6 2xl:p-8`), `gap-6` and an optional `max-w` for reading pages.
  - `PageHeader` has title (`h1`), description, a meta slot for badges, and an actions slot. Actions wrap below 640px, with secondary actions going into an overflow menu.
- **UX Reason:** Hierarchy and a clear primary action.
- **Files/Components Affected:** `patterns/page-container.tsx`, `patterns/page-header.tsx` (new). Adoption happens in module tasks and LAYOUT-005.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-004/005.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Build both components.
  2. Pilot on `setup/labels`.
- **Acceptance Criteria:** On the pilot page the actions align with the title centre and wrap at 375.
- **UI Tests:** Pilot shots.
- **Functional Tests:** n/a.
- **Responsive Tests:** 4 widths.
- **Accessibility Tests:** `h1` present.
- **Regression Tests:** Pilot CRUD.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-003 page container and header`

## [ ] UI-CMP-004 — `EmptyState`
- **Objective:** Meaningful empty states.
- **Current State:**
  - About 40 inline "No … yet." strings in two styles.
  - Filtered lists still say "No projects yet." (`projects/index.tsx:266`).
  - Only `manage-passkeys.tsx:14-20` has an icon, title and description.
- **Problem:** No guidance; confusing when filters are active.
- **Proposed Change:** Props: `icon`, `title`, `description`, `action` (permission-aware from the caller), and `variant: 'empty' | 'no-results'`. The no-results variant includes a "Clear filters" action.
- **UX Reason:** Guides the next step.
- **Files/Components Affected:** `patterns/empty-state.tsx` (new). Adoption happens in module tasks.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-002.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile L · Scope XS.
- **Implementation Steps:**
  1. Build the component.
  2. Pilot on setup labels.
- **Acceptance Criteria:** The pilot shows an action only when permitted.
- **UI Tests:** Shots.
- **Functional Tests:** The CTA opens the create modal.
- **Responsive Tests:** 375.
- **Accessibility Tests:** Heading level is correct.
- **Regression Tests:** Pilot.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-004 empty state`

## [ ] UI-CMP-005 — `ui/table.tsx` primitive
- **Objective:** A semantic table primitive.
- **Current State:** Not found. There are 3 hand-styled tables with no `scope` and no `caption`.
- **Problem:** No tabular affordance.
- **Proposed Change:** The shadcn `table` (Table, Header, Body, Row, Head, Cell, Caption, Footer), styled with tokens: 40px rows, a sticky header option and `tabular-nums` cells.
- **UX Reason:** Scannable data.
- **Files/Components Affected:** `ui/table.tsx` (new).
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-002/004.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr none · Mobile L · Scope XS.
- **Implementation Steps:**
  1. Add the file.
  2. Migrate `estimated-vs-actual.tsx` as the pilot, which is a low-risk read-only table.
- **Acceptance Criteria:** The pilot has `caption` and `th scope`.
- **UI Tests:** Shots.
- **Functional Tests:** n/a.
- **Responsive Tests:** Scroll container.
- **Accessibility Tests:** Axe table rules.
- **Regression Tests:** Allocations tab.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-005 table primitive`

## [ ] UI-CMP-006 — `DataTable` composite with responsive card mode
- **Objective:** One list and table pattern for all record lists.
- **Current State:** Lists are bordered divs (projects, meetings, admin, setup).
- **Problem:** No columns, and 9 different row styles.
- **Proposed Change:**
  - Column configuration: `header`, `cell`, `align`, `hideBelow`, `sortable` (client-side only when all data is loaded), `mobile: 'title' | 'meta' | 'hidden'`.
  - Built-in states: loading (skeleton rows), empty (`EmptyState`), and an error slot.
  - Row actions slot and an optional row link.
  - Stacked cards below 768px.
  - No selection or bulk actions in v1, because no bulk endpoints exist (UI-BE-006).
  - No new dependency; TanStack Table is not needed at this scale.
- **UX Reason:** Consistency and density.
- **Files/Components Affected:** `patterns/data-table.tsx` (new).
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** CMP-004/005.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf L (≤100 rows client-side) · A11y M (`aria-sort`) · Regr L · Mobile M · Scope M.
- **Implementation Steps:**
  1. Build it.
  2. Pilot on `setup/labels`.
  3. Write component tests if TEST-002 is in place.
- **Acceptance Criteria:** The pilot works at 375 and 1440; sortable headers are keyboard-operable and announce the sort.
- **UI Tests:** Shots.
- **Functional Tests:** Label edit and delete from row actions.
- **Responsive Tests:** 4 widths.
- **Accessibility Tests:** `aria-sort`, `caption`.
- **Regression Tests:** Labels pagination.
- **Performance Checks:** Render time with 100 rows is under 16ms (React profiler).
- **Risks:** Over-generalising. Keep the API minimal.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-006 data table`

## [ ] UI-CMP-007 — `Pagination` component that replaces the 7 copies
- **Objective:** Accessible, reusable pagination.
- **Current State:** Copy-pasted `links.map` with `dangerouslySetInnerHTML` in `setup/*` ×3, `admin/users`, `admin/teams`, `projects/index` and `meetings/index`. There's no `aria-current` and no `preserveScroll`.
- **Problem:** Duplicated code, an XSS-shaped pattern, and poor accessibility.
- **Proposed Change:**
  - Takes Laravel `Paginated<T>.links` and meta.
  - Prev/next buttons, numbered pages with ellipsis, and a "Showing x–y of z" line.
  - Uses `aria-label="Pagination"` and `aria-current`.
  - Decodes labels without innerHTML.
  - Keeps each page's existing `preserveState` and query-string behaviour exactly.
- **UX Reason:** Clarity and safety.
- **Files/Components Affected:** `patterns/pagination.tsx` (new); the 7 pages (swap only).
- **Routes Affected:** The same list routes, with unchanged `?page=` params.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-006.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr M (7 pages) · Mobile + (compact on mobile) · Scope S.
- **Implementation Steps:**
  1. Build it.
  2. Swap each page, checking that filters persist.
- **Acceptance Criteria:** No `dangerouslySetInnerHTML` left for pagination; filters survive page changes on projects and meetings.
- **UI Tests:** Shots.
- **Functional Tests:** Paging on all 7 pages; note that setup scopes' paginator lacks a query string (`ScopeController.php:25`), so behaviour stays the same.
- **Responsive Tests:** 375.
- **Accessibility Tests:** Keyboard; `aria-current`.
- **Regression Tests:** R-STD.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-007 shared pagination`

## [ ] UI-CMP-008 — `FilterBar` with active-filter chips and Clear all
- **Objective:** Understandable, responsive filters.
- **Current State:**
  - Fixed `w-40` selects that are unlabelled (placeholder only), in `projects/index.tsx:137-143` and `meetings/index.tsx`.
  - Six client-side filters in `task-list-view.tsx:41-89`.
  - No chips and no clear action.
- **Problem:** Hidden state; poor mobile layout.
- **Proposed Change:**
  - `FilterBar`: labelled controls inline at 1024px and up; a "Filters (n)" button opening a Sheet below that.
  - `FilterChips`: removable chips plus Clear all.
  - Works in two modes: server (`router.get` with `preserveState` and `preserveScroll`, the same params as today) and client (callback).
- **UX Reason:** Filter visibility.
- **Files/Components Affected:** `patterns/filter-bar.tsx`, `patterns/filter-chips.tsx` (new).
- **Routes Affected:** None (the same query params).
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-008, DEP-001 (popover/sheet).
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf L · A11y M · Regr M · Mobile M · Scope M.
- **Implementation Steps:**
  1. Build it.
  2. Pilot on `meetings/index` (server mode).
- **Acceptance Criteria:** The pilot shows the active count; Clear resets the URL params; back/forward restores them.
- **UI Tests:** Shots.
- **Functional Tests:** Meeting filters give the same result sets as today.
- **Responsive Tests:** Sheet at 375.
- **Accessibility Tests:** Chips are buttons with names such as "Remove status: Scheduled".
- **Regression Tests:** Pagination with filters.
- **Performance Checks:** One request per change.
- **Risks:** The `'none'` sentinel in optional filter selects; keep it.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-008 filter bar and chips`

## [ ] UI-CMP-009 — `ConfirmDialog` (AlertDialog) and `useConfirm`
- **Objective:** One safe confirmation pattern.
- **Current State:**
  - 12 near-identical delete modals of about 80 lines each; 0 of 12 handle errors (`label-delete-modal.tsx:41-47`).
  - `window.confirm` in admin users (`:345`).
  - At least 16 actions have no confirmation (audit A.3).
- **Problem:** Duplication, silent failures and accidental deletes.
- **Proposed Change:**
  - `ConfirmDialog` props: `title`, `description` naming the object, `confirmLabel`, `tone`, and an `onConfirm` returning a promise, with loading and error handling (inline Alert plus toast).
  - `useConfirm()` for imperative use.
  - Migrate the setup trio's delete modals as the pilot; the others migrate per module.
- **UX Reason:** Error prevention and recovery.
- **Files/Components Affected:** `ui/alert-dialog.tsx`, `patterns/confirm-dialog.tsx` (new); `components/setup/*-delete-modal.tsx`.
- **Routes Affected:** None.
- **APIs Affected:** None (the same DELETE endpoints through `useHttp`).
- **Database Impact:** None.
- **Dependencies:** DEP-001, DS-006.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile L · Scope S.
- **Implementation Steps:**
  1. Build the component.
  2. Migrate the 3 setup delete modals.
  3. Force a 500 error to verify error display.
- **Acceptance Criteria:** A failed delete shows an error and keeps the dialog open; success shows a toast and reloads the same `only` keys.
- **UI Tests:** Shots.
- **Functional Tests:** Setup delete tests pass.
- **Responsive Tests:** 375.
- **Accessibility Tests:** Focus starts on Cancel; Esc cancels.
- **Regression Tests:** Setup CRUD.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-009 confirm dialog`

## [ ] UI-CMP-010 — `useControllableOpen` and the `FormDialog` shell
- **Objective:** Remove the modal boilerplate.
- **Current State:**
  - The same 12-line controlled/uncontrolled open-state code appears in 16 files (`task-form-modal.tsx:60-70`, `label-form-modal.tsx:29-39`, `meeting-form-modal.tsx:38-48`, …).
  - Forms are reset by remounting with a key.
- **Problem:** Duplication and drift.
- **Proposed Change:**
  - Add the hook.
  - `FormDialog` handles title, description, size, `DialogBody`, footer (Cancel plus submit with `loading`) and the remount key.
  - Pilot on `label-form-modal`.
  - Others migrate in module and FORM tasks.
  - **The `useHttp` submit stays in the feature form.**
- **UX Reason:** Consistent dialogs.
- **Files/Components Affected:** `hooks/use-controllable-open.ts`, `patterns/form-dialog.tsx` (new); `setup/label-form-modal.tsx`.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-010.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ (keeps the X-Inertia JSON branch; risk R2) · Perf none · A11y L · Regr L · Mobile L · Scope S.
- **Implementation Steps:**
  1. Build both.
  2. Migrate the pilot.
- **Acceptance Criteria:** Pilot create and edit behave identically, including reset on reopen.
- **UI Tests:** Pilot.
- **Functional Tests:** Label create and update.
- **Responsive Tests:** 375.
- **Accessibility Tests:** Focus returns to the trigger.
- **Regression Tests:** Labels.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-010 form dialog and open-state hook`

## [ ] UI-CMP-011 — `StatCard`
- **Objective:** A consistent KPI tile.
- **Current State:** Inlined in `dashboard.tsx:58-100`, `projects/show.tsx:438-586` and `admin/dashboard.tsx:35,49`, with a local `StatTile` in `team-capacity/index.tsx:79`.
- **Problem:** Four implementations.
- **Proposed Change:** Props: `label`, `value`, `tone`, `icon`, `hint`, and an optional `href` that makes the whole card a focusable link. Tabular numbers.
- **UX Reason:** Drill-down from summaries.
- **Files/Components Affected:** `patterns/stat-card.tsx` (new). Adoption happens in DASH and PROJ tasks.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-002/004.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr none · Mobile L · Scope XS.
- **Implementation Steps:**
  1. Build it.
  2. Pilot on the team-capacity `StatTile`.
- **Acceptance Criteria:** The pilot is visually equal or better.
- **UI Tests:** Shots.
- **Functional Tests:** n/a.
- **Responsive Tests:** Grid at 375.
- **Accessibility Tests:** The link name includes label and value.
- **Regression Tests:** Capacity page.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-011 stat card`

## [ ] UI-CMP-012 — `Tabs` primitive and `useQueryParam`
- **Objective:** Accessible, URL-synced tabs.
- **Current State:** Hand-rolled tabs with `role=tab` only (`projects/show.tsx:194-217`) and hand-rolled `appearance-tabs.tsx`. State lives in `useState`.
- **Problem:** No keyboard support; no deep links.
- **Proposed Change:**
  - `ui/tabs.tsx` (Radix) with a horizontally scrollable list and fade edges.
  - `useQueryParam('tab', default)` updates the URL with `router.get(url, {}, {replace, preserveState, preserveScroll, only: []})`, or `history.replaceState`, so no server request is triggered.
  - Verify against the Inertia v3 API; it requires verification whether `only: []` avoids the round-trip, otherwise use `replaceState`.
- **UX Reason:** Deep links and keyboard support.
- **Files/Components Affected:** `ui/tabs.tsx`, `hooks/use-query-param.ts` (new); pilot `appearance-tabs.tsx`.
- **Routes Affected:** None (query param only).
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DEP-001.
- **Feasibility:** Tech M (Inertia history interplay) · FE L · BE none · Arch ✓ · Perf L (must not refetch) · A11y + · Regr L · Mobile M · Scope S.
- **Implementation Steps:**
  1. Build both.
  2. Check that 0 network requests happen on a tab switch.
- **Acceptance Criteria:** Arrow keys switch tabs; the URL updates without a request; back/forward restores the tab.
- **UI Tests:** Pilot.
- **Functional Tests:** The appearance setting still works.
- **Responsive Tests:** Scrollable at 375.
- **Accessibility Tests:** `aria-controls` and tabpanel.
- **Regression Tests:** Settings appearance.
- **Performance Checks:** 0 requests per switch.
- **Risks:** Inertia history state conflicts.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-012 tabs with url state`

## [ ] UI-CMP-013 — `RowActions` menu
- **Objective:** Compact, named row actions.
- **Current State:** Rows show 3–5 unnamed icon buttons (kanban card, module tree, lists, time logs).
- **Problem:** Clutter and unnamed controls.
- **Proposed Change:** A labelled "⋯" (`MoreHorizontal`) trigger (`aria-label="Actions for {name}"`) opening a `DropdownMenu` of items with icons, with destructive items separated at the bottom. A primary inline action is still allowed next to it.
- **UX Reason:** Reduced clutter.
- **Files/Components Affected:** `patterns/row-actions.tsx` (new).
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-006.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr none · Mobile + · Scope XS.
- **Implementation Steps:**
  1. Build it.
  2. Pilot in the DataTable pilot.
- **Acceptance Criteria:** Keyboard-operable; named trigger.
- **UI Tests:** Pilot.
- **Functional Tests:** Actions fire.
- **Responsive Tests:** 375.
- **Accessibility Tests:** Menu roles.
- **Regression Tests:** Pilot.
- **Performance Checks:** None.
- **Risks:** Hides actions that are used often. Keep the primary one inline.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-013 row actions menu`

## [ ] UI-CMP-014 — Pending-region indicator for partial reloads, plus skeleton patterns
- **Objective:** Visible feedback for the 37 `router.reload({only})` calls.
- **Current State:** No pending UI; 0 Skeleton uses.
- **Problem:** Users can't tell whether a change has applied.
- **Proposed Change:**
  - `useReloading(keys)` subscribes to Inertia router start/finish events for visits whose `only` includes a given key.
  - `RegionPending` wrapper applies `aria-busy` and dims to 60% after 200ms.
  - Add `Skeleton` presets (row, card, stat) for later deferred props.
- **UX Reason:** System status visibility.
- **Files/Components Affected:** `hooks/use-reloading.ts`, `patterns/region-pending.tsx`, `patterns/skeletons.tsx` (new).
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DS-005.
- **Feasibility:** Tech M (router event API in v3 requires verification) · FE L · BE none · Arch ✓ · Perf L · A11y + · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. Verify the event payload exposes `only`.
  2. Build it.
  3. Pilot on the kanban board.
- **Acceptance Criteria:** After a move, the board shows a pending state until the reload completes, with no flicker for reloads under 200ms.
- **UI Tests:** Throttled network.
- **Functional Tests:** Moves still work.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** `aria-busy`.
- **Regression Tests:** Board.
- **Performance Checks:** No extra renders when idle.
- **Risks:** Event API differences.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-014 pending region and skeletons`

## [ ] UI-CMP-015 — `lib/http-feedback.ts` with form-level error alert
- **Objective:** Uniform success and error feedback for `useHttp`.
- **Current State:** Each modal toasts manually; some don't (FIX-013); none shows 403, 500 or network failures (audit C).
- **Problem:** Silent failures.
- **Proposed Change:**
  - `onHttpSuccess(response)` toasts `message`.
  - `onHttpError(error)`: 422 leaves the field errors in place; 403 shows "You don't have permission…"; 419 shows a session-expired message with a reload action; 5xx and network errors show a generic retry message.
  - `FormErrorAlert` renders inside `FormDialog`.
  - Adopted progressively.
- **UX Reason:** Error recovery.
- **Files/Components Affected:** `lib/http-feedback.ts`, `patterns/form-error-alert.tsx` (new); pilot label form.
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** CMP-010, DS-011.
- **Feasibility:** Tech M (useHttp error shape in v3 requires verification) · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. Inspect the error object.
  2. Build it.
  3. Pilot.
  4. Simulate 403, 419 and 500.
- **Acceptance Criteria:** Each status shows the correct message; no internal details are exposed.
- **UI Tests:** Simulated errors.
- **Functional Tests:** Pilot.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** The alert uses `role=alert`.
- **Regression Tests:** Pilot.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-015 http feedback helpers`

## [ ] UI-CMP-016 — `UserAvatar`, `AvatarGroup` and `UserLabel`
- **Objective:** A consistent way to show people.
- **Current State:** Assignees appear as plain names on kanban cards and lists; `ui/avatar` is used in 5 files.
- **Problem:** Dense text; inconsistent display.
- **Proposed Change:** Avatar with initials fallback (`use-initials`), a group showing `+n` with a tooltip listing names, and a label combining avatar and name.
- **UX Reason:** Faster recognition.
- **Files/Components Affected:** `patterns/user-avatar.tsx` (new).
- **Routes Affected:** None.
- **APIs Affected:** None (uses the existing `avatar` or `avatar_url` fields; which field requires verification per payload).
- **Database Impact:** None.
- **Dependencies:** DS-002.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf L (images lazy-loaded) · A11y L · Regr none · Mobile L · Scope XS.
- **Implementation Steps:**
  1. Build it.
  2. Pilot in the member list.
- **Acceptance Criteria:** Names are accessible via `alt`/`aria-label`.
- **UI Tests:** Shots.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Tooltip reachable by focus.
- **Regression Tests:** Member list.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-016 user avatar components`

## [ ] UI-CMP-017 — Error pages (403, 404, 419, 500, 503)
- **Objective:** Friendly error pages inside the shell.
- **Current State:** Not found; Laravel's default pages are shown.
- **Problem:** Users hit a dead end and see a non-branded page.
- **Proposed Change:**
  - `pages/error.tsx`, rendered through the Inertia exception handler in `bootstrap/app.php` (`respond()` with `Inertia::render('error', ['status'=>…])` in non-local environments). This only renders; there's no business change.
  - Each page explains what happened and what to do, with Go back / Go home.
- **UX Reason:** Recovery.
- **Files/Components Affected:** `pages/error.tsx` (new), `bootstrap/app.php`.
- **Routes Affected:** All routes (on error).
- **APIs Affected:** JSON requests must keep returning JSON; the handler only applies to Inertia and HTML requests.
- **Database Impact:** None.
- **Dependencies:** CMP-003/004.
- **Feasibility:** Tech L · FE L · BE L (render hook only) · Arch ✓ · Perf none · A11y L · Regr M (exception rendering, including JSON modals) · Mobile L · Scope S.
- **Implementation Steps:**
  1. Add the render hook, guarded by `! app()->isLocal()` and `! $request->expectsJson()`.
  2. Build the page.
  3. Add a feature test for 404 and 403 rendering.
- **Acceptance Criteria:** A 404 shows the page in the shell; modal JSON 422 and 403 responses are unchanged.
- **UI Tests:** Each status.
- **Functional Tests:** New tests pass, plus the existing 403 assertions in the suite.
- **Responsive Tests:** 375.
- **Accessibility Tests:** `h1` present.
- **Regression Tests:** Full PHP suite.
- **Performance Checks:** None.
- **Risks:** Masking errors in tests; the local-environment guard handles this.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes (touches `bootstrap/app.php`).
- **Commit Requirement:** `ui: implement UI-CMP-017 error pages`

## [ ] UI-CMP-018 — Remaining primitives: popover, switch, radio-group, scroll-area, progress
- **Objective:** Complete the primitive set used by later tasks.
- **Current State:**
  - Not present.
  - The time-off "Full day" option is a checkbox where a segmented control would read better.
  - Progress meters are hand-rolled divs (`dashboard.tsx:159-167`).
- **Problem:** Ad hoc controls.
- **Proposed Change:** Add the shadcn `popover`, `switch`, `radio-group`, `scroll-area` and `progress` components, with `progress` supporting a tone and requiring an `aria-label`. No adoption in this task.
- **UX Reason:** Correct controls for each job.
- **Files/Components Affected:** `ui/*.tsx` (new, 5 files).
- **Routes Affected:** None.
- **APIs Affected:** None.
- **Database Impact:** None.
- **Dependencies:** DEP-001.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none until used · A11y + · Regr none · Mobile L · Scope S.
- **Implementation Steps:**
  1. Add the components.
  2. Type check.
- **Acceptance Criteria:** Build passes.
- **UI Tests:** n/a.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** n/a.
- **Regression Tests:** Build.
- **Performance Checks:** 0 delta.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-CMP-018 remaining primitives`
