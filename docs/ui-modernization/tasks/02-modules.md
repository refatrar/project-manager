# UI Tasks — 02 Modules (DASH · PROJ · TASK · MEET · TODO · TIME · TEAM · USER · SET · SETUP · FORM · RPT)

Shorthand is defined in `00-foundation.md`. **Invariant for every task in this file:**
- page component names and props stay the same;
- `only: [...]` reload keys stay the same;
- `useHttp` endpoints and payloads stay the same;
- `can*` gating stays the same.

A task that must break one of these stops and asks.

---

## Phase 4 — Dashboard

## [ ] UI-DASH-001 — Dashboard header and linked KPI cards
- **Objective:** A clear title, and KPIs you can drill into.
- **Current State:**
  - The page has no heading (`pages/dashboard.tsx`).
  - Three stat cards (`:57-103`): Projects shows the array length, and Overdue and Blocked are not links.
- **Problem:** The KPIs are dead ends. The Projects KPI duplicates the list below it.
- **Proposed Change:**
  - Add `PageHeader` ("Dashboard", description showing the team name).
  - Show `StatCard`s for active projects, overdue tasks and blocked tasks.
  - Active projects links to `projects.index`.
  - Overdue and Blocked get no link, because no team-wide list of those tasks exists. They show a hint instead ("across N projects"). A later option is UI-BE-002/003.
- **UX Reason:** Drill-down analytics pattern.
- **Files/Components Affected:** `pages/dashboard.tsx`.
- **Routes Affected:** `dashboard` (visual only) · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-003, CMP-011.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile L · Scope S.
- **Implementation Steps:**
  1. Swap the cards.
  2. Add the header.
  3. Keep the pending-invitations modal auto-open (`:51-55`).
- **Acceptance Criteria:** The page has an `h1`. The cards are tone-coded and show text. The invitations modal behaves as before.
- **UI Tests:** Shots · **Functional Tests:** `DashboardTest` passes · **Responsive Tests:** 2 columns at 375 (not 3 tall stacks) · **Accessibility Tests:** axe.
- **Regression Tests:** Member vs Lead project counts.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DASH-001 dashboard header and KPIs`

## [ ] UI-DASH-002 — Portfolio health bar: tokens and accessible legend
- **Objective:** A health summary that doesn't rely on hover.
- **Current State:**
  - Tooltip triggers are non-focusable `div`s (`portfolio-health-bar.tsx:53-63`).
  - The health badge colour (`secondary` for at_risk) disagrees with the bar colour (`status-warning`) (`dashboard.tsx:24-34`).
- **Problem:** Keyboard users can't reach the tooltips, and colours are inconsistent.
- **Proposed Change:**
  - Make the segments `role="img"` with an aria-label.
  - Show the legend as a list with counts, which already exists; keep it as the accessible source.
  - Use `HealthBadge` everywhere.
- **UX Reason:** Consistent meaning.
- **Files/Components Affected:** `portfolio-health-bar.tsx`, `dashboard.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-002.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile L · Scope XS.
- **Implementation Steps:**
  1. Update the markup.
  2. Replace the badge map.
- **Acceptance Criteria:** Badge and bar use the same tone per health value.
- **UI Tests:** Shots · **Functional Tests:** n/a · **Responsive Tests:** 375 · **Accessibility Tests:** Screen-reader read-out.
- **Regression Tests:** Dashboard.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DASH-002 accessible portfolio health`

## [ ] UI-DASH-003 — "Your projects" as a sortable table
- **Objective:** Scan and prioritise projects.
- **Current State:** Card rows showing code, name, health, status, % and a meter (`dashboard.tsx:140-190`). The meter has no aria-label, and the empty state reads "No projects yet."
- **Problem:** Can't sort by health or progress.
- **Proposed Change:**
  - A `DataTable` with columns Code, Name (link), Health, Status, Progress (`Progress` with a label).
  - Client-side sort. The default is worst health first, which is presentation only.
  - An `EmptyState` with a New project CTA, shown only when `projects.create` is granted.
- **UX Reason:** Prioritisation.
- **Files/Components Affected:** `dashboard.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-006, CMP-018.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf L (unpaginated but a small set) · A11y + · Regr L · Mobile M (card mode) · Scope S.
- **Implementation Steps:**
  1. Define the columns.
  2. Set up sorting.
  3. Add the empty state.
- **Acceptance Criteria:** Sorting works with the keyboard. Rows link to the workspace.
- **UI Tests:** Shots · **Functional Tests:** Links · **Responsive Tests:** Cards at 375 · **Accessibility Tests:** `aria-sort`.
- **Regression Tests:** Dashboard.
- **Performance Checks:** Render < 16ms at 100 rows.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DASH-003 dashboard projects table`

## [ ] UI-DASH-004 — Admin dashboard cards and copy
- **Objective:** Accurate, linked admin KPIs.
- **Current State:** Two stat cards (`admin/dashboard.tsx:35,49`). The subtitle "Awaiting a leader… no members yet" is misleading (`:137`).
- **Problem:** Misleading copy, and the cards are dead ends.
- **Proposed Change:**
  - Use `StatCard` with a link to `admin.teams.index`.
  - Correct the copy to "Teams without a team lead".
- **UX Reason:** Accuracy.
- **Files/Components Affected:** `pages/admin/dashboard.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-011, LAYOUT-006.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile L · Scope XS.
- **Implementation Steps:**
  1. Swap in the cards.
  2. Fix the copy.
- **Acceptance Criteria:** The copy matches what `leaderlessTeamCount` actually counts. This requires checking the controller definition.
- **UI Tests:** Shots · **Functional Tests:** Admin dashboard test · **Responsive Tests:** 375 · **Accessibility Tests:** Link names.
- **Regression Tests:** Admin.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DASH-004 admin dashboard cards`

---

## Phase 5 — Project workspace

## [ ] UI-PROJ-001 — Projects index: table, filter bar, empty states
- **Objective:** A scannable portfolio list.
- **Current State:**
  - Card rows (`projects/index.tsx:212-216`) with 3 unlabelled `w-40` selects.
  - Hand-rolled pagination.
  - The empty state ignores active filters.
  - Owner, lead and dates are loaded but never shown.
- **Problem:** Hard to scan, and filter state is unclear.
- **Proposed Change:**
  - A `DataTable` with columns Code, Name, Health, Status, Priority, Progress, Lead, End date. Lead and dates are shown only where the row payload has them, which requires checking `toListArray`.
  - A row actions menu with Update, gated by `can_update` from FIX-001.
  - A `FilterBar` in server mode with the same `status/priority/health` params.
  - `Pagination`, plus `EmptyState` in both its empty and no-results variants.
  - No search and no sort; those come from UI-BE-002/003.
- **UX Reason:** Density and scanability.
- **Files/Components Affected:** `pages/projects/index.tsx`.
- **Routes Affected:** `projects.index` with the same query params · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-002/003/004/006/007/008/013, FIX-001.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile M · Scope M.
- **Implementation Steps:**
  1. Build the columns.
  2. Wire the filters.
  3. Wire pagination.
  4. Build the empty states.
  5. Keep the create modal's navigation to the new project.
- **Acceptance Criteria:**
  - Filter combinations return the same results as before.
  - Clear-all resets the URL.
  - Card mode is used at 375.
- **UI Tests:** Shots · **Functional Tests:** `ProjectControllerTest` passes; manual create and update · **Responsive Tests:** 4 widths · **Accessibility Tests:** Labelled filters.
- **Regression Tests:** R-STD.
- **Performance Checks:** Request count per filter change stays at 1.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PROJ-001 projects list table`

## [ ] UI-PROJ-002 — Workspace header
- **Objective:** Project identity and actions at a glance.
- **Current State:**
  - Title and full description with no clamp (`projects/show.tsx:159`).
  - Edit, Archive and Delete buttons sit in the header (`:162-191`).
- **Problem:** A long description pushes the tabs below the fold, and destructive actions sit next to Edit.
- **Proposed Change:**
  - A `PageHeader` with the code, name, and status and health badges.
  - The description is clamped to 2 lines with a "More" toggle.
  - Actions are Edit (primary) plus a ⋯ menu holding Archive and Delete, each through its existing modal.
- **UX Reason:** Hierarchy and a safer layout.
- **Files/Components Affected:** `projects/show.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-002/003/013.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Extract `ProjectHeader`.
  2. Keep the `canManageProject` gating.
- **Acceptance Criteria:** Archive and Delete work as before and are only visible when they were before.
- **UI Tests:** Shots · **Functional Tests:** Archive and delete · **Responsive Tests:** 375 · **Accessibility Tests:** The "More" toggle has `aria-expanded`.
- **Regression Tests:** Workspace.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PROJ-002 workspace header`

## [ ] UI-PROJ-003 — Workspace tabs: Radix, URL-synced, scrollable, regrouped
- **Objective:** Fix the top responsive defect and add deep links.
- **Current State:**
  - 9 hand-rolled tabs (`show.tsx:90-113,194-217`) with no overflow handling.
  - State lives in `useState('overview')` (`:146`).
- **Problem:** The tabs are clipped at 375–768px, and refresh, back and deep links all reset to Overview.
- **Proposed Change:**
  - Use `Tabs` with `useQueryParam('tab')`, plus `view=board|list` inside Tasks.
  - D-6 grouping: Overview, Tasks, Modules, Milestones, Sprints, Team (Members and Allocations), Activity.
  - Old tab keys (`board`, `list`, `members`, `allocations`) map to the new ones, so any bookmarked or typed URL still works.
  - Below 640px the tab list is replaced by a `Select`.
- **UX Reason:** Reachability and deep linking.
- **Files/Components Affected:** `projects/show.tsx`.
- **Routes Affected:** `projects.show` (query params only) · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-012, D-6.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf L (0 requests per switch) · A11y + · Regr M · Mobile + · Scope M.
- **Implementation Steps:**
  1. Build the tab config.
  2. Map legacy keys.
  3. Move panels without changing their contents.
  4. Verify every modal still opens from each panel.
- **Acceptance Criteria:**
  - `?tab=milestones` opens that tab.
  - Back and forward restore the tab.
  - No horizontal page scroll at 375.
- **UI Tests:** Shots of every tab · **Functional Tests:** CRUD in every tab (modules, members, milestones, sprints, allocations, tasks) · **Responsive Tests:** 4 widths · **Accessibility Tests:** Arrow keys.
- **Regression Tests:** R-STD, plus the partial reload after each CRUD.
- **Performance Checks:** No request on tab switch.
- **Risks:** The combined Team panel must keep both sets of `only` keys.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes, and D-6.
- **Commit Requirement:** `ui: implement UI-PROJ-003 url-synced workspace tabs`

## [ ] UI-PROJ-004 — Overview tab restructure (extract file)
- **Objective:** Surface the most important information first.
- **Current State:**
  - Eleven stacked cards (`show.tsx:378-529`): Status, Health, Overdue and Blocked cards, then burndown, cycle time, and Owner, Lead, Timeline and Client.
  - OverviewTab and CycleTimeCard are defined inside the 646-line page.
- **Problem:** No hierarchy.
- **Proposed Change:**
  - `components/projects/overview-tab.tsx` with a KPI strip (4 `StatCard`s).
  - Burndown and cycle time side by side at 1280px and wider.
  - One "Details" card as a `DetailList` (owner, lead, dates planned vs actual, client, budget if already in the payload).
- **UX Reason:** Visual hierarchy: most important first.
- **Files/Components Affected:** `projects/show.tsx`, `components/projects/overview-tab.tsx` (new).
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** PROJ-003, CMP-011.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile M · Scope M.
- **Implementation Steps:**
  1. Extract the tab into its own file.
  2. Recompose the layout.
  3. Format dates with `lib/format`.
- **Acceptance Criteria:** Every value shown today is still shown. Values match those before the change.
- **UI Tests:** Shots · **Functional Tests:** n/a · **Responsive Tests:** 4 widths · **Accessibility Tests:** Heading order.
- **Regression Tests:** Values compared for 2 projects.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PROJ-004 overview tab layout`

## [ ] UI-PROJ-005 — Burndown chart: responsive text, keyboard and touch, data alternative
- **Objective:** A readable chart on every device.
- **Current State:**
  - Fixed 640×220 viewBox with `text-[10px]` labels (`burndown-chart.tsx:8,92`), which render at about 5px on phones.
  - Hover only (`:162-171`).
  - Raw ISO axis labels (`:151,159`).
  - `role=img` with no data alternative.
- **Problem:** Unreadable on phones, and inaccessible.
- **Proposed Change:**
  - Measure the container with `ResizeObserver` and render axis text in CSS pixels, at least 12px.
  - Use 3–5 ticks, formatted with `lib/format`.
  - Make the plot a focusable group: arrow keys move a cursor across points, and tap selects a point.
  - Add a visually hidden, or toggleable, data table of the 30 points.
  - No chart library.
- **UX Reason:** Accessible charts guidance.
- **Files/Components Affected:** `components/projects/burndown-chart.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-001.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf L · A11y + · Regr L · Mobile + · Scope M.
- **Implementation Steps:**
  1. Refactor sizing.
  2. Add the keyboard cursor.
  3. Add the table alternative.
- **Acceptance Criteria:**
  - Labels are at least 12px at 375.
  - Keyboard users can read every point.
  - The chart matches the data exactly.
- **UI Tests:** Shots · **Functional Tests:** Values equal the `burndown` prop · **Responsive Tests:** 375/1440 · **Accessibility Tests:** Screen reader and keyboard.
- **Regression Tests:** Overview.
- **Performance Checks:** No re-render loop from `ResizeObserver`.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PROJ-005 accessible responsive burndown`

## [ ] UI-PROJ-006 — Modules tree: row actions, indentation, labels
- **Objective:** A tree usable at any depth and width.
- **Current State:**
  - `marginLeft: depth*24` (`module-tree.tsx:117`).
  - Five unnamed `sm` icon buttons (`:139-184`).
  - Raw status text (`:126`).
- **Problem:** At depth 3 on a 375px screen the name disappears, and the icon buttons have no labels.
- **Proposed Change:**
  - Indent 16px per level with a guide line, capped at 4 levels. Deeper levels show a depth badge.
  - Keep up/down as a labelled inline pair.
  - Add child, edit and delete move into `RowActions`.
  - Show a `StatusBadge`.
  - Add `role="tree"` semantics, or a simpler nested list. Nested list is recommended, because a full tree keyboard model is costly.
- **UX Reason:** Mobile usability.
- **Files/Components Affected:** `module-tree.tsx`.
- **Routes Affected:** None · **APIs Affected:** None (same reorder, update and delete endpoints) · **Database Impact:** None.
- **Dependencies:** CMP-002/013.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr M (reorder) · Mobile + · Scope S.
- **Implementation Steps:**
  1. Restyle.
  2. Move actions into the menu.
  3. Test reorder. It is a single swap request; see MEMORY.md:195 on reorder correctness. Don't change that logic here.
- **Acceptance Criteria:** A name is readable at depth 4 at 375. All actions work.
- **UI Tests:** Shots · **Functional Tests:** `ProjectModuleControllerTest` passes; manual reorder · **Responsive Tests:** 375 · **Accessibility Tests:** Named buttons.
- **Regression Tests:** Modules CRUD.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PROJ-006 module tree layout`

## [ ] UI-PROJ-007 — Members as a table (Team tab)
- **Objective:** A clear roster with capacity.
- **Current State:**
  - Bordered rows with a 14-day capacity badge and a tooltip.
  - Unnamed icons.
  - An O(n²) `members.find` call inside the render loop (`member-list.tsx:119-128`).
- **Problem:** Hard to scan, and the icons are unlabelled.
- **Proposed Change:**
  - A `DataTable` with columns Member (`UserLabel`), Role, Allocation %, 14-day capacity (badge plus text), Joined, and Actions.
  - Precompute capacity in a `Map`.
  - Hourly rate stays hidden for non-managers, exactly as the server does it.
- **UX Reason:** Scanability.
- **Files/Components Affected:** `member-list.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-006/013/016, PROJ-003.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf + · A11y + · Regr L · Mobile M · Scope S.
- **Implementation Steps:**
  1. Define the columns.
  2. Build the capacity map.
  3. Wire the modals.
- **Acceptance Criteria:** Add, update and remove work, and the list refreshes after each.
- **UI Tests:** Shots · **Functional Tests:** `ProjectMemberControllerTest` passes · **Responsive Tests:** Cards at 375 · **Accessibility Tests:** Capacity is conveyed in text.
- **Regression Tests:** Team tab.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PROJ-007 members table`

## [ ] UI-PROJ-008 — Milestones as a dated list with status
- **Objective:** A scannable delivery timeline.
- **Current State:** Rows sorted by due date. The code comments "No Gantt yet" (`milestone-list.tsx:41-42`). A local status map sits at `:17`.
- **Problem:** Weak time context.
- **Proposed Change:**
  - A `DataTable` with columns Name, Due (relative plus absolute), Status, Billable, and Actions.
  - Overdue rows get warning or destructive tone plus text.
  - A Gantt view is an optional recommendation, not part of this task.
- **UX Reason:** Time awareness.
- **Files/Components Affected:** `milestone-list.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-001/002/006.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile M · Scope S.
- **Implementation Steps:**
  1. Define the columns.
  2. Add the tones.
  3. Wire the modals.
- **Acceptance Criteria:** CRUD works. "Overdue" appears as text, not colour alone.
- **UI Tests:** Shots · **Functional Tests:** `MilestoneControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** axe.
- **Regression Tests:** Milestones.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PROJ-008 milestones table`

## [ ] UI-PROJ-009 — Sprints as a table with capacity state
- **Objective:** A clear view of sprints.
- **Current State:** Rows with raw status text (`sprint-list.tsx:63`) and an "Over capacity" badge. There's no link to the sprint's tasks.
- **Problem:** Raw labels, and the view is disconnected from tasks.
- **Proposed Change:**
  - A `DataTable` with columns Name, Dates, Status, Capacity vs planned, and Actions.
  - Add a "View tasks" action that opens Tasks → List filtered by `sprint`, using the client-side filter from TASK-003.
- **UX Reason:** Connected workflow.
- **Files/Components Affected:** `sprint-list.tsx`.
- **Routes Affected:** `projects.show` query params · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-006, TASK-003.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile M · Scope S.
- **Implementation Steps:**
  1. Define the columns.
  2. Build the filtered link (`?tab=tasks&view=list&sprint=ID`).
- **Acceptance Criteria:** "View tasks" shows only that sprint's tasks.
- **UI Tests:** Shots · **Functional Tests:** `SprintControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** axe.
- **Regression Tests:** Sprints.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PROJ-009 sprints table`

## [ ] UI-PROJ-010 — Allocations panel (Team tab)
- **Objective:** Readable bookings and over-allocation.
- **Current State:**
  - `AllocationList` shows tasks as `#{number}` instead of their reference (`allocation-list.tsx:114`).
  - Over-allocation appears only in a tooltip.
  - A local status map sits at `:27`.
- **Problem:** Inconsistent references, and warnings are hidden.
- **Proposed Change:**
  - A `DataTable` with columns Member, Task (reference), Dates, Hours/day, Status, and Actions.
  - Over-allocation gets an inline warning badge with text.
  - Use `reference` if it's in the payload; this requires verification. If it isn't, keep `#number` and add a UI-BE note.
- **UX Reason:** Consistency.
- **Files/Components Affected:** `allocation-list.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-006, PROJ-003.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile M · Scope S.
- **Implementation Steps:**
  1. Define the columns.
  2. Add the warning badge.
- **Acceptance Criteria:** CRUD works. Over-allocation is visible without hover.
- **UI Tests:** Shots · **Functional Tests:** `ResourceAllocationControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Warnings in text.
- **Regression Tests:** Team tab.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PROJ-010 allocations table`

## [ ] UI-PROJ-011 — Activity feed grouped by day
- **Objective:** A readable history.
- **Current State:** The last 50 events with relative times and a user filter (`activity-feed.tsx`). The limit is silent.
- **Problem:** A flat list with no time grouping.
- **Proposed Change:**
  - Day group headers (Today, Yesterday, then dates).
  - Absolute time shown in a tooltip.
  - A footer stating "Showing the latest 50 events". Pagination would need a backend change and is out of scope.
- **UX Reason:** Orientation.
- **Files/Components Affected:** `activity-feed.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-001.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile L · Scope XS.
- **Implementation Steps:**
  1. Group the events.
  2. Add the footer.
- **Acceptance Criteria:** Groups respect the local timezone (`Date.UTC` rules).
- **UI Tests:** Shots · **Functional Tests:** The user filter still works · **Responsive Tests:** 375 · **Accessibility Tests:** A list with headings.
- **Regression Tests:** Activity tab.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PROJ-011 activity feed grouping`

---

## Phase 6 — Task management

## [ ] UI-TASK-001 — Kanban card redesign
- **Objective:** Calm, scannable cards with named controls.
- **Current State:**
  - Each card has reference, title, chevrons, type and priority badges (with raw priority text at `kanban-board.tsx:256`), assignee names, and a status Select (`:271-306`).
  - Assign, Edit and Delete are unnamed icon buttons.
  - One shared `moveForm` (`:76`) means rapid clicks race each other.
- **Problem:** Visual noise, and moves have no pending state per card.
- **Proposed Change:**
  - Card layout: reference and priority on the first row, title (link) on the second, then labels, an `AvatarGroup` and the due date.
  - A footer with the status Select, which is still the accessible way to move a card, plus labelled up/down buttons and `RowActions` (Assign, Edit, Delete).
  - Each card shows its own pending state and disables its controls while pending.
  - The server contract is unchanged: `PATCH move` with the same payload.
- **UX Reason:** Clarity, plus a drag alternative (already present).
- **Files/Components Affected:** `kanban-board.tsx`, and possibly a `kanban-card.tsx` extract.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-002/013/014/016.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf L · A11y + · Regr M (moves) · Mobile M · Scope M.
- **Implementation Steps:**
  1. Extract the card.
  2. Build per-card pending state, using a pending id instead of the shared form flag.
  3. Keep the reload keys `tasks`, `activities` and `cycleTime`.
- **Acceptance Criteria:**
  - All moves work: within a column, across columns, and quickly in succession.
  - The gating on `can_change_status` is unchanged.
- **UI Tests:** Shots · **Functional Tests:** `TaskControllerTest` (move) passes; manual moves · **Responsive Tests:** 375 · **Accessibility Tests:** Every control named.
- **Regression Tests:** Board, List and Overview counts.
- **Performance Checks:** No extra requests.
- **Risks:** Race conditions. Serialise moves per card.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TASK-001 kanban card redesign`

## [ ] UI-TASK-002 — Board columns and layout
- **Objective:** A board that fits every viewport.
- **Current State:** `h-[calc(100svh-16rem)]` (`kanban-board.tsx:144`). Columns are `w-72`, each with a count and a "+" button.
- **Problem:** The height offset leaves about 60% of the screen for cards on mobile. The column headers are plain.
- **Proposed Change:**
  - Height from a flex layout instead of a magic number.
  - Sticky column headers showing a status icon, label and count.
  - A labelled "+" button ("Add task to In progress").
  - `scroll-snap` for columns on mobile.
- **UX Reason:** Screen use.
- **Files/Components Affected:** `kanban-board.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** TASK-001.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Change the layout.
  2. Build the headers.
- **Acceptance Criteria:** The board fills the space available. Snap scrolling works on touch.
- **UI Tests:** Shots · **Functional Tests:** "+" presets the status · **Responsive Tests:** 4 widths · **Accessibility Tests:** Button names.
- **Regression Tests:** Board.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TASK-002 board layout`

## [ ] UI-TASK-003 — Shared task filter bar for Board and List
- **Objective:** One set of filters for both views, including status and priority.
- **Current State:**
  - The List has 6 client-side filters (`task-list-view.tsx:41-89`), none of them for status.
  - The Board has no filters.
- **Problem:** No filters on the Board, and the List's filters can't narrow by status.
- **Proposed Change:**
  - A client-mode `FilterBar` above both views, backed by URL params: assignee, label, milestone, sprint, status, priority, due-before, overdue.
  - Overdue follows FIX-002.
  - On the Board, filters hide cards, and column counts show "3 of 8".
- **UX Reason:** Focus.
- **Files/Components Affected:** `task-list-view.tsx`, `kanban-board.tsx`, `components/projects/task-filters.tsx` (new).
- **Routes Affected:** Query params only · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-008, PROJ-003, FIX-002.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf L (client filtering of one project's tasks, as today) · A11y M · Regr M · Mobile M · Scope M.
- **Implementation Steps:**
  1. Lift filter state into the URL.
  2. Share the predicate between both views.
  3. Build the chips.
- **Acceptance Criteria:**
  - Filters persist across the Board/List switch and across a refresh.
  - Counts are correct.
- **UI Tests:** Shots · **Functional Tests:** Each filter · **Responsive Tests:** Sheet at 375 · **Accessibility Tests:** Result count announced.
- **Regression Tests:** Board moves while filtered.
- **Performance Checks:** Filter latency under 50ms for 500 tasks.
- **Risks:** A filtered-out card being moved. Moves still use full-column positions; verify.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TASK-003 shared task filters`

## [ ] UI-TASK-004 — Task List as a sortable table
- **Objective:** A spreadsheet-like list.
- **Current State:** Link rows with no sorting (`task-list-view.tsx`). Label chips are drawn with a raw `backgroundColor` (`:224-234`).
- **Problem:** Can't sort, and label contrast is unsafe.
- **Proposed Change:**
  - A `DataTable` with columns Ref, Title, Status, Priority, Assignees, Due, Labels (`LabelChip`), and Sprint.
  - Client-side sort on all of them.
  - Sticky header.
  - Card mode on mobile.
  - No bulk actions (optional UI-BE-006).
- **UX Reason:** Power-user efficiency.
- **Files/Components Affected:** `task-list-view.tsx`, `patterns/label-chip.tsx` (new).
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** TASK-003, CMP-006.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf M (virtualisation only if the task count exceeds 500; measure first) · A11y + · Regr L · Mobile M · Scope M.
- **Implementation Steps:**
  1. Define the columns.
  2. Implement sorting.
  3. Build `LabelChip`.
- **Acceptance Criteria:** Sorting is stable. Labels are readable in both themes.
- **UI Tests:** Shots · **Functional Tests:** Row links · **Responsive Tests:** 375 · **Accessibility Tests:** `aria-sort`; contrast.
- **Regression Tests:** List view.
- **Performance Checks:** Render time at 500 tasks.
- **Risks:** Large projects. Measure before deciding on virtualisation.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TASK-004 task list table`

## [ ] UI-TASK-005 — Task form: sections, scroll, accessible label picker
- **Objective:** A manageable 15-field form.
- **Current State:**
  - The form is 572 lines (`task-form.tsx`).
  - Label toggles have no `aria-pressed` (`:335-356`).
  - Status is hidden when editing (`:367`).
  - The "To-dos" section (`:488-553`) duplicates the checklist on the task page.
- **Problem:** Long and dense.
- **Proposed Change:**
  - Sections: Basics (title, type, priority, description), Planning (module, milestone, sprint, parent, dates), Effort (estimate, billable), Labels, and To-dos (collapsible, collapsed when editing).
  - Use `FormDialog` size lg.
  - Label toggles get `aria-pressed` and a group label.
  - The fields and payload stay exactly the same, including the `'none'` sentinel.
- **UX Reason:** Chunking.
- **Files/Components Affected:** `task-form.tsx`, `task-form-modal.tsx`.
- **Routes Affected:** None · **APIs Affected:** None (same payload) · **Database Impact:** None.
- **Dependencies:** DS-009/010, CMP-010.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile + · Scope M.
- **Implementation Steps:**
  1. Regroup the fields.
  2. Adopt `FormField`.
  3. Compare the payload JSON before and after for create, edit and subtask.
- **Acceptance Criteria:** The payload is byte-equivalent for the same inputs. The form is usable at 375×667.
- **UI Tests:** Shots · **Functional Tests:** `TaskControllerTest` passes; manual create, edit and subtask · **Responsive Tests:** 375 · **Accessibility Tests:** Screen-reader pass.
- **Regression Tests:** Board "+" preset status.
- **Performance Checks:** None.
- **Risks:** Payload drift. Compare the JSON.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TASK-005 sectioned task form`

## [ ] UI-TASK-006 — Task detail: two columns and a properties rail (display)
- **Objective:** See everything about a task.
- **Current State:**
  - Five small cards (`tasks/show.tsx`).
  - Module, milestone, sprint, dates and billable are visible only inside the edit modal.
  - Dependency rows don't wrap (`task-dependency-editor.tsx:107`).
- **Problem:** Information is hidden.
- **Proposed Change:**
  - Main column (at most 72ch): description, checklist, subtasks, dependencies.
  - Right-hand rail as a `DetailList` showing every task field already in the `task` prop.
  - Stacked below 1024px.
  - Rows wrap.
- **UX Reason:** One-glance context.
- **Files/Components Affected:** `pages/projects/tasks/show.tsx`, `patterns/detail-list.tsx` (new).
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-002/003/016.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile M · Scope M.
- **Implementation Steps:**
  1. Inventory the fields in the `task` prop.
  2. Build the layout.
- **Acceptance Criteria:** Every field in the payload is visible. No overflow at 375.
- **UI Tests:** Shots · **Functional Tests:** Edit and delete · **Responsive Tests:** 4 widths · **Accessibility Tests:** Heading order.
- **Regression Tests:** Task page.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TASK-006 task detail layout`

## [ ] UI-TASK-007 — Task detail: inline status and assignees via existing endpoints
- **Objective:** Change status and assignees without returning to the board.
- **Current State:** Neither can be changed on the task page. `TaskAssignmentsModal` is only reachable from the board (`kanban-board.tsx:414`).
- **Problem:** Extra navigation for the most common edits.
- **Proposed Change:**
  - The rail's Status becomes a `Select` that calls the existing `tasks.move`. Its position payload must follow the move endpoint's rules (append to the target column, as the board's Select does today at `kanban-board.tsx:137`).
  - "Manage assignees" opens the existing `TaskAssignmentsModal`.
  - Both are gated by the same abilities: `can_change_status` / `canManageTask`. Whether `tasks/show` receives `can_change_status` requires verification. If it doesn't, stop, because it needs a UI-BE prop addition.
- **UX Reason:** Fewer steps.
- **Files/Components Affected:** `tasks/show.tsx`.
- **Routes Affected:** None · **APIs Affected:** None (existing endpoints) · **Database Impact:** None.
- **Dependencies:** TASK-006.
- **Feasibility:** Tech M · FE M · BE possibly a prop addition (separate task) · Arch ✓ · Perf none · A11y L · Regr M · Mobile L · Scope S.
- **Implementation Steps:**
  1. Verify the props.
  2. Wire the move endpoint.
  3. Reload the `task` prop and the checklist keys.
- **Acceptance Criteria:**
  - A Member can change status only on tasks assigned to them.
  - The board reflects the change.
- **UI Tests:** Manual · **Functional Tests:** `TaskControllerTest` and `TaskAssignmentControllerTest` pass · **Responsive Tests:** 375 · **Accessibility Tests:** Labelled select.
- **Regression Tests:** Board positions after moving from the page.
- **Performance Checks:** 1 request plus 1 reload.
- **Risks:** Position semantics. Mirror the board's cross-column logic exactly.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes. This exposes an existing capability in a new place.
- **Commit Requirement:** `ui: implement UI-TASK-007 inline status and assignees`

## [ ] UI-TASK-008 — Searchable dependency picker (combobox)
- **Objective:** Find a task quickly.
- **Current State:** A plain Select over every task in the project (`task-dependency-editor.tsx:174-195`).
- **Problem:** Unusable in large projects.
- **Proposed Change:** A `Combobox` (Popover plus Command) that searches reference and title client-side over the existing `taskCandidates`.
- **UX Reason:** Efficiency.
- **Files/Components Affected:** `task-dependency-editor.tsx`, `patterns/combobox.tsx` (new).
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** DEP-002, CMP-018.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf L · A11y + (cmdk roles) · Regr L · Mobile M · Scope S.
- **Implementation Steps:**
  1. Build the combobox.
  2. Swap it in.
- **Acceptance Criteria:** Typing "114" finds ALPHA-114. Add and remove still work.
- **UI Tests:** Manual · **Functional Tests:** `TaskDependencyControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Keyboard and screen reader.
- **Regression Tests:** Cycle-prevention errors are displayed.
- **Performance Checks:** Smooth at 1,000 candidates.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TASK-008 searchable dependency picker`

## [ ] UI-TASK-009 — Checklist: labels, confirmation, gating
- **Objective:** A safe, accessible checklist.
- **Current State:**
  - Checkboxes have no labels.
  - Delete happens in one click.
  - An icon button at `task-checklist.tsx:128` has no name.
  - Actions are ungated (`:120-156`); see FIX-010.
- **Problem:** Accidental deletes, and the control is inaccessible.
- **Proposed Change:**
  - Checkboxes labelled by the item title.
  - `ConfirmDialog` on delete.
  - Named buttons.
  - Gating applied as in FIX-010.
- **UX Reason:** Safety.
- **Files/Components Affected:** `task-checklist.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-009, FIX-010.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile L · Scope S.
- **Implementation Steps:**
  1. Add the labels.
  2. Add the confirmation.
- **Acceptance Criteria:** Toggle, add, edit, delete and promote all work.
- **UI Tests:** Manual · **Functional Tests:** Checklist tests pass · **Responsive Tests:** 375 · **Accessibility Tests:** Labels.
- **Regression Tests:** The To-dos section of the task form.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TASK-009 accessible checklist`

## [ ] UI-TASK-010 — Optional: task quick-preview sheet from Board and List
- **Objective:** Peek at a task without losing board context. This is an optional recommendation.
- **Current State:** Every task link does a full page navigation.
- **Problem:** Context switching.
- **Proposed Change:**
  - Clicking a card opens a `Sheet` with read-only details drawn from the board's `tasks` payload.
  - "Open full page" goes to the task page. Cmd/Ctrl-click still does full navigation.
  - No new endpoint.
- **UX Reason:** Flow.
- **Files/Components Affected:** `kanban-board.tsx`, `task-list-view.tsx`, `components/projects/task-preview-sheet.tsx` (new).
- **Routes Affected:** Optional `?task=ID` param · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** TASK-001/004/006.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ (ADR-013 keeps the task page deep-linkable) · Perf L · A11y M · Regr M · Mobile M (full-width sheet) · Scope M.
- **Implementation Steps:**
  1. Build the sheet.
  2. Handle the URL param.
  3. Make sure links still behave as links for middle-click and screen readers.
- **Acceptance Criteria:** Esc closes the sheet and returns focus to the card. Deep links work.
- **UI Tests:** Manual · **Functional Tests:** n/a · **Responsive Tests:** 375 · **Accessibility Tests:** Focus trap.
- **Regression Tests:** Link behaviour.
- **Performance Checks:** 0 requests.
- **Risks:** The payload lacks some fields; the sheet shows only what's loaded.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes (optional feature).
- **Commit Requirement:** `ui: implement UI-TASK-010 task preview sheet`

## [ ] UI-TASK-011 — Optional: optimistic board moves and checklist toggles
- **Objective:** Instant feedback. This is an optional recommendation.
- **Current State:** Every change waits for a reload.
- **Problem:** Lag of one network round-trip.
- **Proposed Change:** React 19 `useOptimistic` inside `startTransition` for card moves and checkbox toggles, rolling back on error with a toast.
- **UX Reason:** Responsiveness.
- **Files/Components Affected:** `kanban-board.tsx`, `todo-list-card.tsx`, `task-checklist.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** TASK-001, CMP-015.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf + · A11y L · Regr M · Mobile L · Scope M.
- **Implementation Steps:**
  1. Wrap the state.
  2. Implement rollback.
  3. Test with the network throttled or failing.
- **Acceptance Criteria:** The UI updates immediately. A failure reverts and says so.
- **UI Tests:** Throttled network · **Functional Tests:** Move and toggle tests pass · **Responsive Tests:** n/a · **Accessibility Tests:** Announcement on failure.
- **Regression Tests:** Board ordering after reload equals the optimistic order.
- **Performance Checks:** None.
- **Risks:** Order mismatches. The server remains the truth after reload.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes (optional).
- **Commit Requirement:** `ui: implement UI-TASK-011 optimistic moves and toggles`

---

## Phase 8a — Meetings, To-dos, Time (CRUD workflows)

## [ ] UI-MEET-001 — Meetings list: Upcoming and Past, grouped by day
- **Objective:** Make the next meeting obvious.
- **Current State:**
  - Mixed ordering by `scheduled_start` descending.
  - `toLocaleString()` dates (`meetings/index.tsx:190`).
  - Unlabelled filters; a local status map (`:38`); copied pagination.
- **Problem:** Future meetings are buried.
- **Proposed Change:**
  - Within the current page of results, split into Upcoming (ascending) and Past (descending), with day headers.
  - Rows show time range, title, type, status, project and actions.
  - Add `FilterBar` (server status and type) and `Pagination`.
  - This is a client-side split of the current page only. A real split needs a UI-BE param, so state the limitation in the UI.
- **UX Reason:** Temporal relevance.
- **Files/Components Affected:** `pages/meetings/index.tsx`.
- **Routes Affected:** Same params · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-001/002/006/007/008.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile M · Scope M.
- **Implementation Steps:**
  1. Group the rows.
  2. Add the components.
- **Acceptance Criteria:** Meetings today and tomorrow are labelled. Filters and pagination behave the same as before.
- **UI Tests:** Shots · **Functional Tests:** `MeetingControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Group headings.
- **Regression Tests:** Create redirects to the meeting.
- **Performance Checks:** None.
- **Risks:** Grouping across pages is confusing. Mitigation: a note, plus UI-BE-003 later.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-MEET-001 meetings list grouping`

## [ ] UI-MEET-002 — Meeting detail: two columns with a sticky rail
- **Objective:** Before, during and after in one clear layout.
- **Current State:**
  - Eight stacked cards (`meetings/show.tsx`), with the timer at the bottom.
  - A long `meeting_url` overflows (`:168-175`).
- **Problem:** No hierarchy, and the timer is buried.
- **Proposed Change:**
  - At 1024px and wider: the left column holds Agenda, Minutes and Action items; the right rail (sticky) holds Schedule, Location with a Join button (`break-all`), Timer, and Attendees.
  - Stacked below 1024px, with the Timer first while the meeting is in progress.
- **UX Reason:** Task-oriented layout.
- **Files/Components Affected:** `pages/meetings/show.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-003, TASK-006 (DetailList).
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y L · Regr M · Mobile M · Scope M.
- **Implementation Steps:**
  1. Recompose the layout without changing any component internals.
- **Acceptance Criteria:** Every section is present, and every `can.*` gate is unchanged.
- **UI Tests:** Shots · **Functional Tests:** Meeting feature tests pass · **Responsive Tests:** 4 widths · **Accessibility Tests:** Landmarks.
- **Regression Tests:** Timer, RSVP, agenda.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-MEET-002 meeting detail layout`

## [ ] UI-MEET-003 — Minutes editor: autosize, dirty indicator, decisions section
- **Objective:** A comfortable editor for minutes.
- **Current State:** Two textareas with Save and Publish (`minutes-editor.tsx`). FIX-003 adds the dirty guard.
- **Problem:** Cramped editing, and no clear state.
- **Proposed Change:**
  - Textareas autosize, using a 16px reading size.
  - A "Saved at hh:mm" / "Unsaved changes" status line.
  - A published state badge.
  - Publish sits behind a `ConfirmDialog` ("Attendees will be emailed").
- **UX Reason:** Confidence before an irreversible send.
- **Files/Components Affected:** `minutes-editor.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** FIX-003, CMP-009.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile L · Scope S.
- **Implementation Steps:**
  1. Autosize the textareas.
  2. Add the status line.
  3. Add the confirmation.
- **Acceptance Criteria:** Publishing requires confirmation, and emails are sent once.
- **UI Tests:** Manual · **Functional Tests:** `MeetingMinutesControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Live status.
- **Regression Tests:** `can.recordMinutes` gating.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-MEET-003 minutes editor polish`

## [ ] UI-MEET-004 — Agenda, attendees and action items: labels, confirmations, gating
- **Objective:** Safe, accessible meeting sub-lists.
- **Current State:**
  - Unlabelled checkboxes and selects (`attendee-list.tsx:134-160`).
  - One-click deletes.
  - Action items are ungated in the UI (`meeting-action-items.tsx:122-182`).
  - Agenda reorder sends two sequential PATCHes (`agenda-list.tsx:73-87`); don't change that logic here.
- **Problem:** Accidental deletes, and controls that aren't accessible.
- **Proposed Change:**
  - Label every control.
  - Use `ConfirmDialog` for agenda item, attendee and action item deletes.
  - Gate action items the way the server does. Check the policy first, as in FIX-010.
  - Use `RowActions`.
- **UX Reason:** Safety.
- **Files/Components Affected:** `agenda-list.tsx`, `attendee-list.tsx`, `meeting-action-items.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-009/013.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile L · Scope M.
- **Implementation Steps:**
  1. Verify the policies.
  2. Add labels.
  3. Add confirmations.
  4. Apply gating.
- **Acceptance Criteria:** No unnamed controls. Every delete is confirmed.
- **UI Tests:** Manual · **Functional Tests:** Agenda, attendee and action item tests pass · **Responsive Tests:** 375 · **Accessibility Tests:** axe.
- **Regression Tests:** Promote an action item to a task.
- **Performance Checks:** None.
- **Risks:** Hiding an action the server allows. Stop and report if that would happen.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-MEET-004 meeting lists accessibility`

## [ ] UI-TODO-001 — To-do list card redesign
- **Objective:** Lists that stay tidy.
- **Current State:**
  - Completed items are always shown (`todo-list-card.tsx:80`).
  - Priority is plain text (`:159`).
  - The same `ArrowUpRight` icon means both "open task" and "promote" (`:186,207`).
  - Unnamed icons (`:112,120,201`).
  - One `useHttp` instance is shared between toggle and delete (`:47,69`).
- **Problem:** Clutter and ambiguity.
- **Proposed Change:**
  - Completed items collapse under "Completed (n)".
  - Show a `PriorityBadge`.
  - Promote uses the `ListPlus` icon.
  - Use `RowActions`, labelled checkboxes, and a delete `ConfirmDialog`.
  - Separate `useHttp` instances for toggle and delete.
- **UX Reason:** Focus on open work.
- **Files/Components Affected:** `todo-list-card.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-002/009/013.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M (used on 2 pages) · Mobile + · Scope M.
- **Implementation Steps:**
  1. Restructure the card.
  2. Split the request instances.
  3. Test on both My Day and My To-Dos.
- **Acceptance Criteria:** Toggle, promote, edit and delete work on both pages.
- **UI Tests:** Shots · **Functional Tests:** `TodoItemControllerTest` and `PromoteTodoItemToTaskTest` pass · **Responsive Tests:** 375 · **Accessibility Tests:** Labels.
- **Regression Tests:** Generated daily list.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TODO-001 todo list card redesign`

## [ ] UI-TODO-002 — My Day structure
- **Objective:** A focused "today" page.
- **Current State:** Assigned items (limit 50, silent), then all lists, then a placeholder (removed in FIX-012). It duplicates My To-Dos (`MyDayController.php:37-45` versus `TodoListController.php:38-46`).
- **Problem:** No focus on today.
- **Proposed Change:**
  - Assigned items grouped into Overdue, Due today and Later, with a note if the list is capped at 50.
  - Then "Today's list": the daily list first, then the others collapsed.
  - Links to My To-Dos for managing lists.
  - Presentation only; the same props.
- **UX Reason:** Daily focus.
- **Files/Components Affected:** `pages/my-day/index.tsx`, `assigned-checklist-items.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** TODO-001, FIX-012, CMP-001.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile + · Scope M.
- **Implementation Steps:**
  1. Group by due date using local-day rules.
  2. Change the layout.
- **Acceptance Criteria:** The groups are correct across the midnight boundary (UTC+6).
- **UI Tests:** Shots · **Functional Tests:** `MyDayControllerTest` passes · **Responsive Tests:** 4 widths · **Accessibility Tests:** Headings.
- **Regression Tests:** Toggle removes the item after reload.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TODO-002 my day structure`

## [ ] UI-TODO-003 — My To-Dos: status filter and layout
- **Objective:** Manage many lists.
- **Current State:** Every list shown in 2 columns. `statusOptions` is sent to the page but unused.
- **Problem:** Old daily lists pile up.
- **Proposed Change:**
  - A client-side status filter (default: active) and a type filter (custom or daily), both kept in the URL.
  - Empty states for both "nothing yet" and "no results".
- **UX Reason:** Findability.
- **Files/Components Affected:** `pages/todo-lists/index.tsx`.
- **Routes Affected:** Query params · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-004/008, TODO-001.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf L · A11y L · Regr L · Mobile L · Scope S.
- **Implementation Steps:**
  1. Add the filters.
  2. Add the empty states.
- **Acceptance Criteria:** By default only active lists are shown, and the filter is shown as active.
- **UI Tests:** Shots · **Functional Tests:** CRUD · **Responsive Tests:** 375 · **Accessibility Tests:** Result count announced.
- **Regression Tests:** List create appears in the current filter.
- **Performance Checks:** None.
- **Risks:** Users thinking a list was lost to the default filter. Mitigation: a visible chip.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes (changes the default view).
- **Commit Requirement:** `ui: implement UI-TODO-003 todo lists filters`

## [ ] UI-TODO-004 — Optional: inline quick-add item
- **Objective:** Add an item in a single step. This is an optional recommendation.
- **Current State:** "Add item" always opens a modal.
- **Problem:** Friction.
- **Proposed Change:**
  - An inline input at the bottom of each list: Enter creates an item with the title only, through the existing store endpoint.
  - A "More options" link opens the existing modal.
- **UX Reason:** Speed.
- **Files/Components Affected:** `todo-list-card.tsx`.
- **Routes Affected:** None · **APIs Affected:** None (existing store endpoint with title only; which fields are required requires verification) · **Database Impact:** None.
- **Dependencies:** TODO-001.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile L · Scope S.
- **Implementation Steps:**
  1. Verify the required fields.
  2. Build the input.
  3. Show errors inline.
- **Acceptance Criteria:** Enter adds an item and keeps focus in the input.
- **UI Tests:** Manual · **Functional Tests:** Store test passes · **Responsive Tests:** 375 · **Accessibility Tests:** Labelled input.
- **Regression Tests:** The modal still works.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes (optional).
- **Commit Requirement:** `ui: implement UI-TODO-004 inline quick add`

## [ ] UI-TIME-001 — Time logs: grouped by day, totals, confirmed delete
- **Objective:** An understandable effort log.
- **Current State:**
  - A flat 30-day `<ul>` (`time-log-list.tsx`).
  - Lowercase statuses (`:63-66`) and a one-click delete (`:39,97`).
  - Unnamed icons (`:83-100`).
- **Problem:** No totals, and deletes are unsafe.
- **Proposed Change:**
  - A `DataTable` grouped by day, with a day-total header, and columns Project, Activity, Duration, Billable, Status and Actions.
  - Edit and delete only while the entry is pending and stopped, as today.
  - Delete through `ConfirmDialog`.
  - A running entry shows "Running" and links to the timer.
- **UX Reason:** Overview and safety.
- **Files/Components Affected:** `time-log-list.tsx`, `pages/time-logs/index.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-001/002/006/009.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile M · Scope M.
- **Implementation Steps:**
  1. Group the entries.
  2. Add totals using `formatMinutes`.
  3. Add the table and the confirmation.
- **Acceptance Criteria:** Totals equal the sum of the entries. The edit and delete rules are unchanged.
- **UI Tests:** Shots · **Functional Tests:** `TimeLogControllerTest` passes · **Responsive Tests:** Cards at 375 · **Accessibility Tests:** Named actions.
- **Regression Tests:** Timesheet totals match.
- **Performance Checks:** None.
- **Risks:** Day boundaries. Use local-day rules.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TIME-001 grouped time logs`

## [ ] UI-TIME-002 — Time log form: responsive grid, FormField, clearable project
- **Objective:** A form usable on phones.
- **Current State:**
  - `grid-cols-2` isn't responsive (`time-log-form.tsx:85,126`).
  - The project can't be cleared (no "none" item).
  - The footer differs from the time-off form.
- **Problem:** Cramped layout, and the project choice can't be undone.
- **Proposed Change:**
  - `sm:grid-cols-2`.
  - Adopt `FormField`.
  - Add a "No project" item using the existing `'none'` sentinel. Confirm that the server normalises `project_id` `'none'`; this requires verification in the FormRequest. If it doesn't, stop and log a UI-BE item.
- **UX Reason:** Usability.
- **Files/Components Affected:** `time-log-form.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** DS-009, FIX-011.
- **Feasibility:** Tech L · FE L · BE none (if the sentinel is supported) · Arch ✓ · Perf none · A11y + · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Verify the sentinel.
  2. Change the grid.
  3. Adopt `FormField`.
- **Acceptance Criteria:** The form works at 375, and a project can be cleared (if supported).
- **UI Tests:** Shots · **Functional Tests:** Store and update tests pass · **Responsive Tests:** 375 · **Accessibility Tests:** Linked errors.
- **Regression Tests:** `datetime-local` keeps seconds on edit (MEMORY.md:207).
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TIME-002 time log form layout`

## [ ] UI-TIME-003 — Timer card: layout, announcements, document title
- **Objective:** A timer that's visible and accessible.
- **Current State:**
  - Fixed `w-48`/`w-40` selects (`time-log-timer.tsx:134,150`).
  - The description has no placeholder.
  - The elapsed time re-renders every second with no announcement strategy.
  - Stop errors aren't toasted (`:88`).
- **Problem:** Fixed widths on mobile, and no status for assistive technology.
- **Proposed Change:**
  - Responsive widths.
  - An example placeholder for the description.
  - The elapsed time is visible every second but announced only on start and stop.
  - While running, `document.title` is prefixed with the elapsed time.
  - Stop errors are toasted.
  - The ticking display is isolated into its own component (PERF-004).
- **UX Reason:** Awareness.
- **Files/Components Affected:** `time-log-timer.tsx`, `meeting-timer.tsx` (shared ticker).
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-001.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf + · A11y + · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Extract `ElapsedTicker`.
  2. Add the title effect with cleanup.
- **Acceptance Criteria:** The title resets on stop. Screen readers aren't spammed.
- **UI Tests:** Manual · **Functional Tests:** Start and stop tests pass · **Responsive Tests:** 375 · **Accessibility Tests:** Screen-reader check.
- **Regression Tests:** The meeting timer.
- **Performance Checks:** Only the ticker re-renders.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TIME-003 timer polish`

## [ ] UI-TIME-004 — Timesheet: navigation, sticky column, semantics, submit confirmation
- **Objective:** A clear weekly grid.
- **Current State:**
  - The range shows raw ISO dates (`timesheet/index.tsx:108`), and there's no "This week" button.
  - The table (`:165`) has no caption or scope.
  - Submit has no confirmation and doesn't explain why it's disabled (`:146`).
- **Problem:** Orientation, and a risky action.
- **Proposed Change:**
  - A formatted range with prev, This week and next.
  - A sticky first column, `caption`, `scope`, and "—" cells with screen-reader text.
  - Submit goes through `ConfirmDialog` ("Submit N entries, H hours").
  - When disabled, show the reason from existing props (`canSubmit`, `hasRunningTimer`).
- **UX Reason:** Clarity.
- **Files/Components Affected:** `pages/timesheet/index.tsx`.
- **Routes Affected:** Same `?week=` · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-001/005/009.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile M · Scope S.
- **Implementation Steps:**
  1. Header navigation.
  2. Table semantics.
  3. Confirmation.
- **Acceptance Criteria:** The disabled reason is visible. The submit flow is unchanged apart from the confirmation.
- **UI Tests:** Shots · **Functional Tests:** `TimesheetControllerTest` and `SubmitTimesheetTest` pass · **Responsive Tests:** Horizontal scroll with sticky column at 375 · **Accessibility Tests:** Table semantics.
- **Regression Tests:** The same reload keys.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TIME-004 timesheet polish`

## [ ] UI-TIME-005 — Timesheet approvals: grouped by member and week
- **Objective:** Efficient review.
- **Current State:** An unpaginated card list of individual entries (`timesheet-approvals/index.tsx`). Rejecting has no reason field.
- **Problem:** Decisions are one at a time, with no context.
- **Proposed Change:**
  - Group entries by member, then week (`Collapsible`), with totals.
  - A table per group with per-row Approve and Reject, as today.
  - **No bulk action and no reject reason** until UI-BE-006.
- **UX Reason:** Context for decisions.
- **Files/Components Affected:** `pages/timesheet-approvals/index.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-005/006, FIX-006.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf L · A11y L · Regr L · Mobile M · Scope M.
- **Implementation Steps:**
  1. Group the entries.
  2. Add the tables.
- **Acceptance Criteria:** Every entry is visible and the decisions work.
- **UI Tests:** Shots · **Functional Tests:** `TimesheetApprovalControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Group headings.
- **Regression Tests:** The `canApproveTimesheets` nav gate.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TIME-005 grouped approvals`

## [ ] UI-TIME-006 — Time off: tables, status tabs, confirmed cancel, responsive form
- **Objective:** Clear leave management.
- **Current State:**
  - Cancel is an unlabelled X with no confirmation (`time-off-request-list.tsx:98-107`).
  - ISO dates.
  - The form's date grid isn't responsive (`:93`) and its footer doesn't stack (`:167`).
  - The decision note persists after Cancel (`decide-time-off-request-modal.tsx:55`).
- **Problem:** Safety and clarity.
- **Proposed Change:**
  - Pending approvals as a table: Member, Type, Dates, Calendar days, Reason, Actions.
  - "Your requests" as a table with client-side status tabs.
  - Cancel through `ConfirmDialog`.
  - The form uses `sm:grid-cols-2`, a `RadioGroup` for Full day / Half day, and a calendar-day count labelled "calendar days" (this is not a working-day calculation).
  - Reset the decision note when the dialog closes.
- **UX Reason:** Safety.
- **Files/Components Affected:** `components/time-off/*`, `pages/time-off/index.tsx`.
- **Routes Affected:** None · **APIs Affected:** None (same payload for `full_day`) · **Database Impact:** None.
- **Dependencies:** CMP-001/006/009/018.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile + · Scope M.
- **Implementation Steps:**
  1. Build the tables.
  2. Convert the form (keeping the boolean payload).
  3. Add the confirmation.
  4. Reset the note.
- **Acceptance Criteria:** The half-day times still appear conditionally, and the payload is unchanged.
- **UI Tests:** Shots · **Functional Tests:** `TimeOffRequestControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Radio group.
- **Regression Tests:** The `time-off.manage` gate on the request button.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TIME-006 time off redesign`

## [ ] UI-TIME-007 — Find available people: form states and results table
- **Objective:** A usable availability search.
- **Current State:** Errors aren't rendered (FIX-011), there's no pending state, and results are shown as a list of name and email.
- **Problem:** Weak feedback.
- **Proposed Change:**
  - `FormField` for the inputs and a `loading` submit button.
  - Results as a `DataTable` (Name, Email).
  - Empty states for "not searched yet" and "no results".
  - An optional link to Team Capacity for the same `from` date.
- **UX Reason:** Feedback.
- **Files/Components Affected:** `pages/availability/index.tsx`.
- **Routes Affected:** Same params · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** DS-009, CMP-006.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile L · Scope S.
- **Implementation Steps:**
  1. Form states.
  2. The table.
  3. Empty states.
- **Acceptance Criteria:** Errors are shown and the loading state is visible.
- **UI Tests:** Shots · **Functional Tests:** `FindAvailableUsersControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Linked errors.
- **Regression Tests:** The `availability.view` gate.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TIME-007 availability search polish`

## [ ] UI-TIME-008 — Team capacity heatmap: focusable cells, sort, search
- **Objective:** Keyboard access to the heatmap, plus a way to find people.
- **Current State:**
  - Cells have `role="img"` and an aria-label but can't receive focus (`heatmap.tsx:201`).
  - There's no sort or search.
  - `members.flatMap` runs on every render (`team-capacity/index.tsx:119`).
- **Problem:** Keyboard users can't reach the tooltips, and a large team is hard to scan.
- **Proposed Change:**
  - Cells use `tabIndex=0`, with arrow-key roving focus and tooltips on focus.
  - A client-side name search and a sort by two-week load.
  - Memoise the aggregates. The React Compiler may already handle this; measure first.
- **UX Reason:** Accessible data viz.
- **Files/Components Affected:** `heatmap.tsx`, `pages/team-capacity/index.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** None hard; CMP-008 for search.
- **Feasibility:** Tech M (roving tabindex) · FE M · BE none · Arch ✓ · Perf L · A11y + · Regr L · Mobile L · Scope M.
- **Implementation Steps:**
  1. Roving focus.
  2. Search and sort.
  3. Memoisation.
- **Acceptance Criteria:** A keyboard user can read every cell. Sort and search are correct.
- **UI Tests:** Manual · **Functional Tests:** `TeamCapacityControllerTest` passes · **Responsive Tests:** 375 scroll · **Accessibility Tests:** Screen reader.
- **Regression Tests:** Week navigation.
- **Performance Checks:** Render time at 150 members.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TIME-008 accessible capacity heatmap`

---

## Phase 7 — Teams, users, settings, setup

## [ ] UI-TEAM-001 — Teams index: row links, current-team badge
- **Objective:** Simple team navigation.
- **Current State:** Separate View and Edit icons point to the same route (`teams/index.tsx:96-140`), and the current team isn't marked.
- **Problem:** Redundant controls.
- **Proposed Change:**
  - Make the whole row a link.
  - Add a "Current" badge and a role badge.
  - Leave the team through `RowActions` and the existing modal.
- **UX Reason:** Simplicity.
- **Files/Components Affected:** `pages/teams/index.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-006/013.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile L · Scope S.
- **Implementation Steps:**
  1. Restructure the rows.
- **Acceptance Criteria:** Leave is still gated. Team leads can't leave (`TeamPolicy.php:38-42`).
- **UI Tests:** Shots · **Functional Tests:** Teams tests pass · **Responsive Tests:** 375 · **Accessibility Tests:** Link names.
- **Regression Tests:** Team switch.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TEAM-001 teams index`

## [ ] UI-TEAM-002 — Team edit: members table, confirmed role change, danger zone tokens
- **Objective:** Safe member management.
- **Current State:**
  - The role dropdown applies immediately via `router.visit` (`teams/edit.tsx:74-78`).
  - Member rows don't wrap (`:174`).
  - The danger zone uses `red-50`/`red-600` (`:330`).
  - Icon-only buttons (`:250,308`).
- **Problem:** Accidental role changes, overflow on narrow screens, and hardcoded colour.
- **Proposed Change:**
  - A members table: Member, Email, Role (Select), Joined, Actions.
  - A role change asks for confirmation ("Change Alex to Team lead?") and shows a pending state.
  - The current role can't be re-selected.
  - The danger zone uses the destructive tokens.
  - Honour `manageable` and `permissions` exactly.
- **UX Reason:** Error prevention.
- **Files/Components Affected:** `pages/teams/edit.tsx`, `delete-user.tsx` (same danger-zone tokens).
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-006/009.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M (anti-escalation paths) · Mobile + · Scope M.
- **Implementation Steps:**
  1. Build the table.
  2. Add the confirmation.
  3. Switch to tokens.
- **Acceptance Criteria:** Role changes need confirmation. Unmanageable members stay read-only.
- **UI Tests:** Shots · **Functional Tests:** `Teams/TeamMemberControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Named buttons.
- **Regression Tests:** Invitations section.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TEAM-002 team members management`

## [ ] UI-TEAM-003 — Team modals: shared confirmation, label ids
- **Objective:** Consistent team dialogs.
- **Current State:**
  - The remove, leave and cancel-invitation modals repeat their processing boilerplate.
  - In the invite modal, `Label htmlFor="role"` has no matching id (`invite-member-modal.tsx:201,212`).
  - A possible duplicate `id="email"`.
- **Problem:** Duplicated code and broken label association.
- **Proposed Change:**
  - Move the remove, leave and cancel modals to `ConfirmDialog`.
  - Fix the ids by using `useId`.
  - Keep the typed-name confirmation on delete team.
- **UX Reason:** Consistency.
- **Files/Components Affected:** `remove-member-modal.tsx`, `leave-team-modal.tsx`, `cancel-invitation-modal.tsx`, `invite-member-modal.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-009.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile L · Scope S.
- **Implementation Steps:**
  1. Migrate the three modals.
  2. Fix the ids.
- **Acceptance Criteria:** All team flows work.
- **UI Tests:** Manual · **Functional Tests:** Teams tests pass · **Responsive Tests:** 375 · **Accessibility Tests:** Labels.
- **Regression Tests:** Invite and cancel.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TEAM-003 team dialogs consolidation`

## [ ] UI-TEAM-004 — Pending invitations and no-team page consistency
- **Objective:** One invitation list component.
- **Current State:** The accept/decline list is duplicated between `pending-invitations-modal.tsx` and `no-team.tsx:17-29`. `no-team` has its own centred card.
- **Problem:** Duplicated code.
- **Proposed Change:**
  - Extract an `InvitationList` component.
  - Give `no-team` the auth-simple styling with `EmptyState` guidance.
- **UX Reason:** Consistency.
- **Files/Components Affected:** `pending-invitations-modal.tsx`, `pages/no-team.tsx`, `components/invitation-list.tsx` (new).
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-004.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile L · Scope S.
- **Implementation Steps:**
  1. Extract the component.
  2. Restyle `no-team`.
- **Acceptance Criteria:** Accept and decline work from both places.
- **UI Tests:** Manual · **Functional Tests:** `StartControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** axe.
- **Regression Tests:** Dashboard invitation auto-open.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TEAM-004 invitation list`

## [ ] UI-USER-001 — Admin users: table, dialogs, row actions, confirmations
- **Objective:** A scalable user admin page.
- **Current State:**
  - 508 lines (`admin/users/index.tsx`), with an always-open create card.
  - Each row renders an edit toggle form and an assign-team form with 2 Selects (`:398-426`).
  - "Remove" unassigns immediately (`:409`), and delete uses `window.confirm` (`:345`).
  - Stale form state after reload (`:145`).
- **Problem:** A heavy DOM (20 rows × 2 Radix Selects), unsafe actions and clutter.
- **Proposed Change:**
  - A `DataTable` with columns User, Email, Teams (badges), Created, Actions.
  - Create and Edit in `FormDialog`, reset whenever opened.
  - "Assign to team" in a dialog.
  - Unassign and delete through `ConfirmDialog`.
  - `Pagination`.
  - The same endpoints.
- **UX Reason:** Clarity and safety.
- **Files/Components Affected:** `pages/admin/users/index.tsx`, `components/admin/user-*.tsx` (new).
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** LAYOUT-006, CMP-006/007/009/010/013.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf + (smaller DOM) · A11y + · Regr M · Mobile + · Scope L.
- **Implementation Steps:**
  1. Split into components.
  2. Move forms into dialogs.
  3. Add confirmations.
  4. Compare payloads.
- **Acceptance Criteria:** Every user operation works, and no inline forms remain.
- **UI Tests:** Shots · **Functional Tests:** `Admin/UserControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** axe.
- **Regression Tests:** Toasts (FIX-013).
- **Performance Checks:** DOM node count before and after.
- **Risks:** Payload drift. Compare.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-USER-001 admin users redesign`

## [ ] UI-USER-002 — Admin teams: table and dialogs
- **Objective:** A consistent admin teams page.
- **Current State:**
  - Each row has an inline rename input, an unlabelled assign-lead email input (`admin/teams/index.tsx:149`) and a remove-lead button with no confirmation (`:113`).
  - Fixed `w-56` widths.
- **Problem:** Same issues as the users page.
- **Proposed Change:**
  - A `DataTable` with columns Team, Members, Lead, Actions.
  - Rename and Assign lead in dialogs with labelled fields.
  - Remove lead through `ConfirmDialog`.
  - `Pagination`.
- **UX Reason:** Consistency.
- **Files/Components Affected:** `pages/admin/teams/index.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** USER-001 patterns.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile + · Scope M.
- **Implementation Steps:**
  1. Build the table and dialogs.
  2. Add the confirmation.
- **Acceptance Criteria:** All team admin operations work.
- **UI Tests:** Shots · **Functional Tests:** `Admin/TeamControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Labels.
- **Regression Tests:** Admin dashboard counts.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-USER-002 admin teams redesign`

## [ ] UI-USER-003 — Admin team roles: permission matrix
- **Objective:** Understandable role editing.
- **Current State:**
  - The create form is always expanded with every permission (`team-roles/index.tsx:278`).
  - There's no per-module "select all".
  - Delete has no confirmation, even when the role is in use (`:196-200`).
  - Edit state isn't reset on Cancel.
- **Problem:** Error-prone.
- **Proposed Change:**
  - Show roles as a list of cards with an account count.
  - Edit in a dialog showing permissions grouped by module, with a "select all in module" checkbox (tri-state).
  - Delete through `ConfirmDialog`, warning when `accounts_count > 0`.
  - Reset on open.
  - Anti-escalation stays server-side, unchanged.
- **UX Reason:** Clarity.
- **Files/Components Affected:** `pages/admin/team-roles/index.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-009/010.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf none · A11y + (tri-state) · Regr M · Mobile M · Scope M.
- **Implementation Steps:**
  1. Group by module (the `module` field already exists).
  2. Build the matrix.
  3. Add the confirmation.
- **Acceptance Criteria:** The `permission_ids` payload is identical for the same selection.
- **UI Tests:** Manual · **Functional Tests:** `Admin/TeamRoleControllerTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Checkbox group labels.
- **Regression Tests:** A member's nav reflects role changes.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-USER-003 team roles matrix`

## [ ] UI-USER-004 — Admin admins, holidays, work schedules: labels, confirmations, layout
- **Objective:** Finish the admin area.
- **Current State:**
  - The admins page shows raw error `<p>` tags (`admins/index.tsx:197`).
  - Holiday removal has no confirmation (`holidays/index.tsx:77`), and the year buttons are labelled with the bare number (`:129,144`).
  - On the work schedule, the day checkbox and time inputs have no labels (`work-schedules/index.tsx:167,185-246`).
- **Problem:** Accessibility and safety.
- **Proposed Change:**
  - Use `FormField` everywhere.
  - Holiday removal through `ConfirmDialog`.
  - Year navigation labelled "Previous year (2025)".
  - The schedule becomes a table with labelled inputs per day.
- **UX Reason:** Accessibility.
- **Files/Components Affected:** `admin/admins`, `admin/holidays`, `admin/work-schedules`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** DS-009, CMP-009, FIX-005.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile M · Scope M.
- **Implementation Steps:**
  1. Work through the three pages one by one within this task.
- **Acceptance Criteria:** Axe reports 0 label violations on all three pages.
- **UI Tests:** Shots · **Functional Tests:** Admin holiday, schedule and admin tests pass · **Responsive Tests:** 375 · **Accessibility Tests:** axe.
- **Regression Tests:** Capacity after a schedule change.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-USER-004 admin utility pages`

## [ ] UI-SET-001 — Account settings: confirmations and tokens
- **Objective:** Safe, consistent settings.
- **Current State:**
  - Disabling 2FA happens immediately (`manage-two-factor.tsx:134`), and so does avatar removal (`avatar-upload.tsx:243`).
  - Hardcoded `text-green-600` (`settings/profile.tsx:108`).
  - No file size or type hint on the avatar upload.
- **Problem:** Risky one-click actions.
- **Proposed Change:**
  - `ConfirmDialog` for disabling 2FA and removing the avatar.
  - Status messages as `Alert` success.
  - A hint for the avatar upload (limits from the FormRequest rules).
  - Section cards on the security page.
- **UX Reason:** Safety.
- **Files/Components Affected:** `settings/profile.tsx`, `settings/security.tsx`, `manage-two-factor.tsx`, `avatar-upload.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-009, DS-011.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr M (security flows) · Mobile L · Scope S.
- **Implementation Steps:**
  1. Add the confirmations.
  2. Switch to tokens.
  3. Add the hint.
- **Acceptance Criteria:** The 2FA, passkey and avatar flows all work.
- **UI Tests:** Manual · **Functional Tests:** `Settings/*` tests pass · **Responsive Tests:** 375 · **Accessibility Tests:** axe.
- **Regression Tests:** Password confirmation gate.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-SET-001 account settings safety`

## [ ] UI-SETUP-001 — One config-driven setup page for scopes, task types and labels
- **Objective:** Remove the triplicated code.
- **Current State:**
  - Three near-identical pages. `task-types/index.tsx` still uses the `editingScope` and `scopeToDelete` names and imports `ScopeStatusOption`.
  - Nine form, modal and delete files.
- **Problem:** Duplication and drift.
- **Proposed Change:**
  - `components/setup/setup-resource-page.tsx`, taking config for columns, fields and routes.
  - The three pages become thin wrappers with the same page names and props.
  - `DataTable`, `FormDialog`, `ConfirmDialog`, `EmptyState` with a CTA, and `Pagination`.
  - Keep the current paginator behaviour of each controller (scopes has no query string; task types uses 7 per page).
- **UX Reason:** Consistency.
- **Files/Components Affected:** `pages/setup/*`, `components/setup/*`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-004/006/007/009/010.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M (3 CRUDs) · Mobile + · Scope M.
- **Implementation Steps:**
  1. Build the shared page.
  2. Migrate labels.
  3. Migrate scopes.
  4. Migrate task types.
  5. Delete the dead files.
- **Acceptance Criteria:**
  - CRUD works on all three pages.
  - Label colour, scope status and task type status are preserved.
  - The file count drops by at least 6.
- **UI Tests:** Shots · **Functional Tests:** `tests/Feature/Setup/*` pass · **Responsive Tests:** 375 · **Accessibility Tests:** The colour field is labelled (`label-form.tsx:82`).
- **Regression Tests:** Labels on the task form.
- **Performance Checks:** Bundle smaller.
- **Risks:** Over-abstraction. Keep the config flat.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-SETUP-001 shared setup resource page`

---

## Phase 8b — Forms (adopt FormField, FormDialog, http-feedback)

## [ ] UI-FORM-001 — Project-area forms
- **Objective:** Every project-area form uses the standard field and dialog.
- **Current State:** The project, module, milestone, sprint, member, allocation and assignment forms use raw Label, control and InputError without aria wiring.
- **Problem:** Accessibility, plus no form-level errors.
- **Proposed Change:**
  - Adopt `FormField`, `FormDialog` and `http-feedback`.
  - Keep the fields, payloads, `'none'` sentinels and remount keys exactly as they are.
- **UX Reason:** Accessible, consistent forms.
- **Files/Components Affected:** `components/projects/*-form*.tsx`, `task-assignments-modal.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** DS-009, CMP-010/015. The task form is handled separately in TASK-005.
- **Feasibility:** Tech L · FE M (7 forms) · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile + · Scope M.
- **Implementation Steps:**
  1. Migrate one form per sub-step.
  2. Compare payloads.
  3. Force 422 and 500 responses.
- **Acceptance Criteria:** Axe reports 0 label violations. 422 errors are linked. A 500 shows the form alert.
- **UI Tests:** Each modal · **Functional Tests:** The related controller tests · **Responsive Tests:** 375 · **Accessibility Tests:** Screen-reader spot check.
- **Regression Tests:** Partial reload keys.
- **Performance Checks:** None.
- **Risks:** Payload drift. Compare.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FORM-001 project area forms`

## [ ] UI-FORM-002 — Meeting and to-do forms
- **Objective:** Same as FORM-001.
- **Current State:** `meeting-form`, `agenda-item-form`, `attendee-invite-modal`, `todo-list-form`, `todo-item-form` and `promote-todo-item-modal` use the raw pattern.
- **Problem:** Same as FORM-001.
- **Proposed Change:** Same as FORM-001. Note that the meeting controller accepts `sprint_id` but the form has no field for it; that is not added here (logged).
- **UX Reason:** Consistency.
- **Files/Components Affected:** `components/meetings/*-form*`, `components/todo-lists/*-form*`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** As FORM-001.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile + · Scope M.
- **Implementation Steps:** As FORM-001.
- **Acceptance Criteria:** As FORM-001.
- **UI Tests:** Each modal · **Functional Tests:** Meeting and to-do tests · **Responsive Tests:** 375 · **Accessibility Tests:** axe.
- **Regression Tests:** Meeting create navigates to the meeting.
- **Performance Checks:** None.
- **Risks:** `datetime-local` seconds (MEMORY.md:207).
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FORM-002 meeting and todo forms`

## [ ] UI-FORM-003 — Time forms
- **Objective:** Same as FORM-001.
- **Current State:** The time-log form (partly covered by TIME-002), the time-off form and both decide modals use the raw pattern.
- **Problem:** Same as FORM-001.
- **Proposed Change:** Same as FORM-001.
- **UX Reason:** Consistency.
- **Files/Components Affected:** `components/time-logs/*`, `components/time-off/*`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** TIME-002/006.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile + · Scope S.
- **Implementation Steps:** As FORM-001.
- **Acceptance Criteria:** As FORM-001.
- **UI Tests:** Each modal · **Functional Tests:** Time tests · **Responsive Tests:** 375 · **Accessibility Tests:** axe.
- **Regression Tests:** Timesheet.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FORM-003 time forms`

## [ ] UI-FORM-004 — Team, settings and auth forms
- **Objective:** Same as FORM-001, for Inertia `<Form>` based pages.
- **Current State:** 18 files use `<Form>` (auth and settings).
- **Problem:** Error linking.
- **Proposed Change:** Adopt `FormField`. The `<Form>` component and its `disableWhileProcessing` behaviour stay.
- **UX Reason:** Consistency.
- **Files/Components Affected:** `pages/auth/*`, `pages/settings/*`, `pages/teams/edit.tsx`, `pages/admin/profile/edit.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** DS-009.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr M (auth) · Mobile L · Scope M.
- **Implementation Steps:**
  1. Migrate page by page.
- **Acceptance Criteria:** Auth flows are unchanged, and axe is clean.
- **UI Tests:** Each page · **Functional Tests:** `Auth/*` and `Settings/*` tests · **Responsive Tests:** 375 · **Accessibility Tests:** axe.
- **Regression Tests:** Password manager autofill still works (`autocomplete` attributes kept).
- **Performance Checks:** None.
- **Risks:** Critical path.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FORM-004 team settings and auth forms`

---

## Phase 9 — Reports and analytics (existing only)

A standalone Reports area is **not found in the current repository**. It is not planned here, and would need requirements first.

## [ ] UI-RPT-001 — Estimated vs actual: table semantics and variance display
- **Objective:** A readable variance report.
- **Current State:** A plain table with a red variance column (`estimated-vs-actual.tsx:19,50-54`) and no scope or caption.
- **Problem:** Colour is the only signal.
- **Proposed Change:**
  - Use `ui/table` with a caption.
  - Variance shown with a sign, an icon and a tone: over is warning or destructive, under is neutral.
  - A totals row.
  - A tabular numbers footer.
- **UX Reason:** Clarity.
- **Files/Components Affected:** `estimated-vs-actual.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-005.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile M · Scope XS.
- **Implementation Steps:**
  1. Migrate to the table component.
- **Acceptance Criteria:** Values are unchanged. Variance is readable without colour.
- **UI Tests:** Shots · **Functional Tests:** `CalculateEstimatedVersusActualTest` passes · **Responsive Tests:** 375 scroll · **Accessibility Tests:** Table rules.
- **Regression Tests:** Team tab.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-RPT-001 estimated vs actual table`

## [ ] UI-RPT-002 — Cycle-time card: accessible bar list
- **Objective:** An accessible cycle and lead time summary.
- **Current State:** A per-status bar list in `CycleTimeCard`, defined inside `projects/show.tsx` along with `formatDuration` (`:531`).
- **Problem:** Bars are visual only.
- **Proposed Change:**
  - Extract the card into its own file.
  - Each bar has a text value and an aria-label.
  - Use `lib/format`.
- **UX Reason:** Accessibility.
- **Files/Components Affected:** `components/projects/cycle-time-card.tsx` (new), `projects/show.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** PROJ-004, CMP-001.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile L · Scope XS.
- **Implementation Steps:**
  1. Extract.
  2. Add labels.
- **Acceptance Criteria:** A screen reader reads each status with its duration.
- **UI Tests:** Shots · **Functional Tests:** `CalculateCycleTimeReportTest` passes · **Responsive Tests:** 375 · **Accessibility Tests:** Screen reader.
- **Regression Tests:** Overview.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-RPT-002 accessible cycle time card`
