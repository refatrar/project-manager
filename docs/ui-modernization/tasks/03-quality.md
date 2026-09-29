# UI Tasks — 03 Quality, Performance, Cleanup, QA, Backend track

Shorthand is defined in `00-foundation.md`. These sweeps close whatever the module tasks left over. Each one starts with a grep or axe inventory, and its scope is limited to what that inventory finds.

---

## Phase 10 — Responsive

## [ ] UI-RESP-001 — Header rows, fixed widths and wrapping sweep
- **Objective:** No cramped or overflowing controls at 375–414px.
- **Current State:** 27 of 40 pages have no breakpoint prefixes. Header rows use `flex justify-between` without wrapping. There are fixed `w-40`, `w-48` and `w-56` controls (projects, meetings, timer, admin).
- **Problem:** Squeezed titles and controls wrapping onto 4 rows.
- **Proposed Change:**
  - Remaining header rows move to `PageHeader`.
  - Fixed widths become `w-full sm:w-40`.
  - Add `min-w-0` and `break-words` wherever long user text sits in flex rows.
- **UX Reason:** Mobile usability.
- **Files/Components Affected:** Whatever the grep finds after the module tasks.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** All module tasks.
- **Feasibility:** Tech L · FE M · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile + · Scope M.
- **Implementation Steps:**
  1. Grep for `w-(40|48|56)` and `justify-between` without `flex-wrap`.
  2. Take shots at 375, 390 and 414.
  3. Fix what they show.
- **Acceptance Criteria:** No page-level horizontal scroll and no truncated primary actions at 375, 390 and 414.
- **UI Tests:** Shots · **Functional Tests:** n/a · **Responsive Tests:** 375/390/414/768 · **Accessibility Tests:** Zoom to 200% without loss of content.
- **Regression Tests:** Desktop shots are unchanged.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-RESP-001 responsive wrapping sweep`

## [ ] UI-RESP-002 — Tables and overflow containers verification
- **Objective:** Every table works on mobile.
- **Current State:** After the module tasks, tables use DataTable card mode or local scroll containers.
- **Problem:** Regressions creep in as pages change.
- **Proposed Change:**
  - Check every DataTable and grid table at 375/768.
  - Grid tables need a sticky first column.
  - No nested scrollbars inside dialogs.
- **UX Reason:** Mobile data access.
- **Files/Components Affected:** As found.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** RESP-001.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Build a checklist of every table.
  2. Fix what fails.
- **Acceptance Criteria:** The checklist is fully ticked in the PR notes.
- **UI Tests:** Shots · **Functional Tests:** Row actions on mobile · **Responsive Tests:** 375/768 · **Accessibility Tests:** Scroll regions are focusable and labelled.
- **Regression Tests:** Desktop tables.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-RESP-002 table responsiveness verification`

## [ ] UI-RESP-003 — Large screens (1440 and 1920): reading width and layout balance
- **Objective:** Make good use of wide screens.
- **Current State:** Content stretches to full width with no maximum on reading content (task description, minutes).
- **Problem:** Line lengths become too long.
- **Proposed Change:**
  - Reading blocks get `max-w-[72ch]`.
  - Detail pages cap at `max-w-screen-2xl` and centre.
  - Dashboards use a 12-column grid at 1440 and above.
- **UX Reason:** Readability.
- **Files/Components Affected:** `PageContainer` variants and detail pages.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-003.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. Take shots at 1440 and 1920.
  2. Adjust the layouts.
- **Acceptance Criteria:** No line runs longer than about 80 characters in reading content.
- **UI Tests:** Shots · **Functional Tests:** n/a · **Responsive Tests:** 1280/1440/1920 · **Accessibility Tests:** n/a.
- **Regression Tests:** 1024 shots.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-RESP-003 large screen layout`

---

## Phase 11 — Accessibility

## [ ] UI-A11Y-001 — Icon-only buttons: accessible names sweep
- **Objective:** Every icon-only control has a name.
- **Current State:** About 40 unnamed buttons at audit time (audit §F). Module tasks fix most of them.
- **Problem:** Screen readers announce "button" with no label (WCAG 4.1.2).
- **Proposed Change:**
  - Convert any remaining ones to `IconButton`, which requires an `aria-label`.
  - Tooltip text matches the label.
- **UX Reason:** Accessibility.
- **Files/Components Affected:** As the grep and axe results show.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** DS-006.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. Run axe across all pages with modals opened.
  2. Fix the findings.
- **Acceptance Criteria:** Axe `button-name` violations = 0.
- **UI Tests:** Axe · **Functional Tests:** n/a · **Responsive Tests:** n/a · **Accessibility Tests:** Screen-reader spot check.
- **Regression Tests:** Visual unchanged.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-A11Y-001 icon button names`

## [ ] UI-A11Y-002 — Checkbox, select and input labels sweep
- **Objective:** Every form control is labelled.
- **Current State:** Unlabelled checkboxes and selects existed in to-do, agenda, attendee and work-schedule forms. The label colour hex input has no label (`label-form.tsx:82`).
- **Problem:** WCAG 1.3.1 and 3.3.2.
- **Proposed Change:** Label whatever remains, using a visible label or `aria-labelledby`.
- **UX Reason:** Accessibility.
- **Files/Components Affected:** As found.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** FORM-001…004.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. Run axe `label` rules.
  2. Fix the findings.
- **Acceptance Criteria:** 0 violations.
- **UI Tests:** Axe · **Functional Tests:** n/a · **Responsive Tests:** n/a · **Accessibility Tests:** Screen reader.
- **Regression Tests:** Forms submit.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-A11Y-002 control labels`

## [ ] UI-A11Y-003 — Unconfirmed destructive actions sweep
- **Objective:** Every destructive action is confirmed or undoable.
- **Current State:** At least 16 unconfirmed actions (audit A.3). Module tasks cover most of them.
- **Problem:** Accidental data loss.
- **Proposed Change:** Find any remaining ones with a grep for `.delete(`, `destroy` and `cancel` calls outside `ConfirmDialog`, and add confirmation. For high-frequency, low-stakes items (a to-do item), an undo toast is an acceptable alternative, but only if an endpoint exists to restore the item. None is known, so use confirmation.
- **UX Reason:** Error prevention.
- **Files/Components Affected:** As found.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-009.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. Inventory.
  2. Fix.
- **Acceptance Criteria:** The inventory table shows every action confirmed.
- **UI Tests:** Manual · **Functional Tests:** Deletes still work · **Responsive Tests:** n/a · **Accessibility Tests:** Focus handling in the dialogs.
- **Regression Tests:** R-STD.
- **Performance Checks:** None.
- **Risks:** Extra friction for frequent actions. Ask for your approval on any list of high-frequency actions.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-A11Y-003 destructive action confirmations`

## [ ] UI-A11Y-004 — Live announcements for async results
- **Objective:** Screen readers hear outcomes.
- **Current State:** No `aria-live` regions (count 0). Sonner provides its own announcements.
- **Problem:** Filter result counts and pending or saved states are silent.
- **Proposed Change:**
  - A single app-level `role="status"` region.
  - An `announce(msg)` helper.
  - Used by FilterBar ("12 tasks"), RegionPending ("Updating…" / "Updated") and dirty-state indicators.
- **UX Reason:** Accessibility.
- **Files/Components Affected:** `components/live-region.tsx` (new), patterns.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** CMP-008/014.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. Build the region.
  2. Wire it into the patterns.
  3. Debounce announcements.
- **Acceptance Criteria:** Filtering announces the result count once.
- **UI Tests:** n/a · **Functional Tests:** n/a · **Responsive Tests:** n/a · **Accessibility Tests:** NVDA/VoiceOver.
- **Regression Tests:** No duplicate announcements with toasts.
- **Performance Checks:** None.
- **Risks:** Chatty output. Debounce it.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-A11Y-004 live announcements`

## [ ] UI-A11Y-005 — Authentication inputs and password reveal
- **Objective:** Accessible authentication.
- **Current State:**
  - The recovery code input has no label (`two-factor-challenge.tsx:67`) and the OTP group has no accessible name.
  - The password reveal button has `tabIndex={-1}` (`password-input.tsx:27`).
  - The toggle copy is lowercase ("login using…").
  - `forgot-password` uses `autoComplete="off"` and no `required` (`:166`).
- **Problem:** Keyboard and screen-reader barriers on the critical path.
- **Proposed Change:**
  - Label the inputs.
  - Make the reveal button focusable, with `aria-pressed` and the label "Show password".
  - Sentence-case the copy.
  - Use `autocomplete="email"` and `required`.
- **UX Reason:** Accessibility.
- **Files/Components Affected:** `pages/auth/two-factor-challenge.tsx`, `password-input.tsx`, `pages/auth/forgot-password.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** FIX-009.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr M (auth) · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Fix each item.
- **Acceptance Criteria:** Axe is clean on the auth pages, and a keyboard user can reveal the password.
- **UI Tests:** Keyboard · **Functional Tests:** `Auth/*` tests · **Responsive Tests:** n/a · **Accessibility Tests:** Screen reader.
- **Regression Tests:** 2FA login with an OTP and with a recovery code.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-A11Y-005 accessible auth inputs`

## [ ] UI-A11Y-006 — Contrast audit in both themes, plus chart alternatives check
- **Objective:** WCAG AA contrast everywhere.
- **Current State:** Token values are verified in DS-001. The raw `text-green-600` (≈3.3:1) is removed in earlier tasks. User-chosen label colours are handled by `LabelChip`.
- **Problem:** Residual violations.
- **Proposed Change:**
  - Run axe `color-contrast` in light and dark across every page, including open modals, hover and focus states.
  - Fix any findings.
  - Verify the burndown, heatmap and health bar each have a text alternative.
- **UX Reason:** Accessibility.
- **Files/Components Affected:** As found.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** All DS tasks and module tasks.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. Run the scan.
  2. Fix.
  3. Document the results in the design-system doc.
- **Acceptance Criteria:** 0 contrast violations in either theme.
- **UI Tests:** Axe · **Functional Tests:** n/a · **Responsive Tests:** n/a · **Accessibility Tests:** Manual check of states.
- **Regression Tests:** Visual.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-A11Y-006 contrast audit fixes`

---

## Phase 12 — Performance and cleanup

## [ ] UI-PERF-001 — Lazy-load layouts that aren't in use
- **Objective:** A smaller entry chunk.
- **Current State:** `app.tsx:5-8` eagerly imports the App, Admin, Auth and Settings layouts. The entry chunk is 225 KB raw.
- **Problem:** Every user downloads the admin and auth shells.
- **Proposed Change:** Resolve layouts through dynamic imports, if Inertia v3's `layout` resolution supports async (requires verification). Otherwise split them at the page level by assigning `.layout` inside the admin and auth pages.
- **UX Reason:** Faster first load.
- **Files/Components Affected:** `resources/js/app.tsx`, possibly admin and auth pages.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** LAYOUT-006.
- **Feasibility:** Tech M · FE M · BE none · Arch ✓ (persistent layouts must stay persistent) · Perf + · A11y none · Regr M (layout remounts) · Mobile + · Scope S.
- **Implementation Steps:**
  1. Measure.
  2. Implement.
  3. Verify the sidebar doesn't remount between app pages.
  4. Measure again.
- **Acceptance Criteria:** The entry chunk shrinks by at least 15 KB gzip. Layouts don't remount on navigation within the same area.
- **UI Tests:** Navigation smoke test · **Functional Tests:** n/a · **Responsive Tests:** n/a · **Accessibility Tests:** n/a.
- **Regression Tests:** Sidebar state persists; scroll restoration works.
- **Performance Checks:** Before/after gzip sizes, and the Lighthouse TTI trend.
- **Risks:** A layout flash on the first admin visit.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PERF-001 lazy layouts`

## [ ] UI-PERF-002 — Code-split the workspace tabs
- **Objective:** A smaller `projects/show` chunk (92 KB raw).
- **Current State:** All 9 tabs are bundled into the page.
- **Problem:** Code for the board, modules and every other tab loads even when the user only views Overview.
- **Proposed Change:** Load each tab panel with `React.lazy`, with a Skeleton fallback. Overview stays eager.
- **UX Reason:** Faster first paint.
- **Files/Components Affected:** `projects/show.tsx`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** PROJ-003/004.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf + · A11y L (focus management on the fallback) · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Split the tabs.
  2. Prefetch on tab hover or focus.
- **Acceptance Criteria:** The initial page chunk shrinks by at least 30%, and switching tabs shows no visible delay on a fast connection.
- **UI Tests:** Throttled network · **Functional Tests:** All tabs · **Responsive Tests:** n/a · **Accessibility Tests:** The fallback is announced.
- **Regression Tests:** Modals open from lazy tabs.
- **Performance Checks:** Chunk sizes.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PERF-002 lazy workspace tabs`

## [ ] UI-PERF-003 — Bundle budget report per task
- **Objective:** Catch bundle growth automatically.
- **Current State:** Sizes are measured manually through UI-TEST-001.
- **Problem:** Drift goes unnoticed.
- **Proposed Change:** Add `tests/ui/bundle-report.mjs`, which builds to a temporary directory, compares against a committed `tests/ui/bundle-baseline.json`, and fails if the entry grows by more than 10 KB gzip or a page by more than 20 KB. The baseline is updated only by explicit approval.
- **UX Reason:** Guards performance.
- **Files/Components Affected:** `tests/ui/*`.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** TEST-001.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf n/a · A11y n/a · Regr none · Mobile n/a · Scope S.
- **Implementation Steps:**
  1. Write the script.
  2. Record the baseline.
- **Acceptance Criteria:** The script fails on an artificial size increase.
- **UI Tests:** n/a · **Functional Tests:** n/a · **Responsive Tests:** n/a · **Accessibility Tests:** n/a.
- **Regression Tests:** n/a.
- **Performance Checks:** This task is the performance check.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PERF-003 bundle budget report`

## [ ] UI-PERF-004 — Isolate per-second timer re-renders
- **Objective:** Only the ticking text re-renders.
- **Current State:** Timers tick every second at component level (`time-log-timer.tsx`, `meeting-timer.tsx`).
- **Problem:** The whole card, including its Radix Selects, re-renders each second.
- **Proposed Change:** An `ElapsedTicker` leaf component owns the interval. Whatever TIME-003 hasn't already covered is finished here.
- **UX Reason:** Smoothness.
- **Files/Components Affected:** Timers.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** TIME-003.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf + · A11y none · Regr L · Mobile + · Scope XS.
- **Implementation Steps:**
  1. Extract the ticker.
  2. Profile.
- **Acceptance Criteria:** The React Profiler shows one component re-rendering per tick.
- **UI Tests:** n/a · **Functional Tests:** Start and stop · **Responsive Tests:** n/a · **Accessibility Tests:** n/a.
- **Regression Tests:** Displayed values.
- **Performance Checks:** Profiler.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-PERF-004 timer render isolation`

## [ ] UI-CLEAN-001 — Remove dead UI code
- **Objective:** Less code to maintain and no misleading components.
- **Current State:** The following are unused:
  - `components/app-header.tsx`, which links to the Laravel starter-kit repo and has a dead Search button
  - `layouts/app/app-header-layout.tsx`
  - `components/nav-footer.tsx`
  - `layouts/auth/auth-card-layout.tsx`
  - `layouts/auth/auth-split-layout.tsx`
  - `ui/navigation-menu`, `collapsible`, `toggle`, `toggle-group`, `icon` and `placeholder-pattern`, unless later tasks use them (Collapsible is used by TIME-005)
- **Problem:** Dead code, and outbound links to the starter-kit repository.
- **Proposed Change:** Delete whatever is still unused after all module tasks, confirmed by grep for imports.
- **UX Reason:** Maintainability.
- **Files/Components Affected:** The files listed above.
- **Routes Affected:** None · **APIs Affected:** None · **Database Impact:** None.
- **Dependencies:** All module tasks.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf + (little effect, since tree-shaking already drops them) · A11y none · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. Grep for imports.
  2. Delete.
  3. Build and type-check.
- **Acceptance Criteria:** The build passes and no imports break.
- **UI Tests:** n/a · **Functional Tests:** R-STD · **Responsive Tests:** n/a · **Accessibility Tests:** n/a.
- **Regression Tests:** Full build.
- **Performance Checks:** Bundle report.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes (deletion).
- **Commit Requirement:** `ui: implement UI-CLEAN-001 remove dead ui code`

---

## Phase 13 — Final regression

## [ ] UI-QA-001 — Full regression and quality gate
- **Objective:** Confirm the complete modernization against the design quality gate.
- **Current State:** —
- **Problem:** —
- **Proposed Change:**
  - Run everything: the full PHP suite on MySQL, `composer ci:check`, the component tests, and screenshots of all 40 pages at 8 widths × 2 themes.
  - Axe on every page with modals open.
  - A manual pass as Team Lead, Member, custom role and Admin through every workflow in the audit's module inventory.
  - Compare the bundle report with the baseline.
- **UX Reason:** Release confidence.
- **Files/Components Affected:** None (a report in `docs/ui-modernization/qa-report.md`).
- **Routes Affected:** All · **APIs Affected:** None · **Database Impact:** Test database only.
- **Dependencies:** Everything.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf check · A11y check · Regr check · Mobile check · Scope M (effort, not code).
- **Implementation Steps:**
  1. Run the checklist.
  2. Log defects as new `UI-FIX-*` tasks, which are not fixed within this task.
- **Acceptance Criteria:** The report shows 0 critical issues and every quality gate item (brief §48) ticked.
- **UI Tests:** All · **Functional Tests:** All · **Responsive Tests:** 375/390/414/768/1024/1280/1440/1920 · **Accessibility Tests:** All.
- **Regression Tests:** All.
- **Performance Checks:** Bundle and request counts against the baseline.
- **Risks:** None.
- **Rollback Plan:** n/a.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-QA-001 final regression report`

---

## Backend track: separate approval, not UI modernization

These change backend behaviour, API contracts or product rules, so they are **not** part of the UI work. Each one only unlocks the optional UI items that name it.

## [ ] UI-BE-001 — Review the default Member role's `setup.manage` over global scopes and task types
- **Objective:** Product-owner decision on a possible over-grant.
- **Current State:**
  - The Member defaults include `setup.manage` (`TeamAccessControl.php:320-335`).
  - Scopes and task types are **global**, not per team (`ScopeController.php:22-26`, `TaskTypeController.php:22-27`).
- **Problem:** Any member of any team may edit the platform-wide scopes and task types. Whether this is intended requires verification with the owner.
- **Proposed Change:** Decision only. The options are:
  1. Keep it as it is.
  2. Remove `setup.manage` from the Member defaults, which needs a seeder or migration of role permissions.
  3. Make scopes and task types team-scoped, which needs a schema change.
- **UX Reason:** Not UX; security and data integrity.
- **Files/Components Affected:** Depends on the decision.
- **Routes Affected:** Setup · **APIs Affected:** Depends · **Database Impact:** Option 2 changes role data; option 3 changes the schema.
- **Dependencies:** D-8.
- **Feasibility:** Tech L–H depending on the option · FE none · BE M–H · Arch per ADRs · Perf none · A11y none · Regr M · Mobile none · Scope TBD.
- **Implementation Steps:** Planned after the decision.
- **Acceptance Criteria:** Decision recorded in `docs/open-decisions.md`.
- **UI Tests:** n/a · **Functional Tests:** `TeamPermissionEnforcementTest` · **Responsive Tests:** n/a · **Accessibility Tests:** n/a.
- **Regression Tests:** Full suite.
- **Performance Checks:** n/a.
- **Risks:** Removing access users currently rely on.
- **Rollback Plan:** Per option.
- **Approval Required:** Yes, a product decision.
- **Commit Requirement:** `fix: <per option>`, not a `ui:` commit.

## [ ] UI-BE-002 — Server-side text search (`q`) for projects, meetings and admin users
- **Objective:** Enable search boxes, plus an optional global search later (SEARCH-001).
- **Current State:** No search endpoint or parameter exists anywhere.
- **Problem:** Users can't find records by name.
- **Proposed Change:** An optional `q` query parameter on the index controllers, doing a `LIKE` on name, code or email with team scoping and eager loading unchanged. Tests follow RULES.md §9, including a cross-team case.
- **UX Reason:** Findability.
- **Files/Components Affected:** `ProjectController`, `MeetingController`, `Admin/UserController`, FormRequests, tests.
- **Routes Affected:** The same routes with an optional parameter · **APIs Affected:** Additive · **Database Impact:** Possible index (verify with EXPLAIN).
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE M · Arch ✓ · Perf M (LIKE scans) · A11y none · Regr L · Mobile none · Scope M.
- **Implementation Steps:** Backend tests first, then the UI search input in the existing FilterBar.
- **Acceptance Criteria:** Search is scoped to the team and paginated.
- **UI Tests:** Search input · **Functional Tests:** New tests · **Responsive Tests:** n/a · **Accessibility Tests:** Labelled search.
- **Regression Tests:** Filters combined with search.
- **Performance Checks:** EXPLAIN at ProfilingSeeder volume.
- **Risks:** Slow queries.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes (API addition).
- **Commit Requirement:** `feat: add search parameter to <lists>`

## [ ] UI-BE-003 — Server-side sort parameter for paginated lists
- **Objective:** Allow sortable column headers on paginated tables.
- **Current State:** Every list has a fixed order (for example `ProjectController.php:69`, `MeetingController.php:61`).
- **Problem:** Tables can't be sorted across pages.
- **Proposed Change:** A whitelisted `sort` and `direction` pair per list.
- **UX Reason:** Control.
- **Files/Components Affected:** Index controllers, tests.
- **Routes Affected:** Optional parameters · **APIs Affected:** Additive · **Database Impact:** Possible indexes.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE M · Arch ✓ · Perf M · A11y none · Regr L · Mobile none · Scope M.
- **Implementation Steps:** Whitelist the columns, add tests, then enable `sortable` in DataTable server mode.
- **Acceptance Criteria:** Unknown columns are rejected.
- **UI Tests:** Sort headers · **Functional Tests:** New tests · **Responsive Tests:** n/a · **Accessibility Tests:** `aria-sort`.
- **Regression Tests:** Default order is unchanged.
- **Performance Checks:** EXPLAIN.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `feat: add sorting to <lists>`

## [ ] UI-BE-004 — Deferred props per workspace tab
- **Objective:** A faster first load of `projects/show`.
- **Current State:** About 30 props are computed on every visit (`ProjectController.php:134-286`).
- **Problem:** A heavy query and payload whatever tab the user opens.
- **Proposed Change:** Wrap the tab-specific props (capacity, allocations, estimated vs actual, burndown, cycle time, activities) in `Inertia::defer()` or optional props, grouped per tab. The frontend uses `<Deferred>` with Skeletons. **All existing `only: [...]` reloads must keep working** (MEMORY.md:147).
- **UX Reason:** Speed.
- **Files/Components Affected:** `ProjectController.php`, `projects/show.tsx` and its tab components.
- **Routes Affected:** Same · **APIs Affected:** Prop timing changes (a contract change) · **Database Impact:** None.
- **Dependencies:** PROJ-003, CMP-014.
- **Feasibility:** Tech M · FE M · BE M · Arch ✓ · Perf + · A11y L · Regr H (partial reload keys) · Mobile + · Scope M.
- **Implementation Steps:** Measure, defer one group at a time, and update the `assertInertia` tests (deferred assertions).
- **Acceptance Criteria:** TTFB improves by at least 30% on a large project. Every mutation refreshes correctly.
- **UI Tests:** Skeletons · **Functional Tests:** `ProjectControllerTest` updated · **Responsive Tests:** n/a · **Accessibility Tests:** `aria-busy`.
- **Regression Tests:** Every CRUD in every tab.
- **Performance Checks:** Query count and time before and after.
- **Risks:** High regression risk on reloads.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes (backend contract).
- **Commit Requirement:** `perf: defer project workspace tab props`

## [ ] UI-BE-005 — My Day meeting action items query
- **Objective:** Show a user's real meeting actions on My Day.
- **Current State:** The UI card is a placeholder, removed in FIX-012. Action lists do exist (`TodoListType::MeetingActions`).
- **Problem:** A useful digest is missing.
- **Proposed Change:** Add a `meetingActionItems` prop to `MyDayController`, covering items assigned to the user in `meeting_actions` lists, open, and limited in number. A UI section follows.
- **UX Reason:** Completeness.
- **Files/Components Affected:** `MyDayController`, tests, `my-day/index.tsx`.
- **Routes Affected:** Same · **APIs Affected:** An additional prop · **Database Impact:** None (the index `todo_items(assigned_to, is_completed, due_at)` exists).
- **Dependencies:** D-7.
- **Feasibility:** Tech L · FE L · BE L · Arch ✓ · Perf L · A11y L · Regr L · Mobile L · Scope S.
- **Implementation Steps:** Test first, then the prop, then the UI.
- **Acceptance Criteria:** The items match the meeting's action list. Team scoping is enforced.
- **UI Tests:** Section · **Functional Tests:** New tests including cross-team · **Responsive Tests:** 375 · **Accessibility Tests:** Labels.
- **Regression Tests:** `MyDayControllerTest`.
- **Performance Checks:** No N+1 (`preventLazyLoading`).
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `feat: show meeting action items on my day`

## [ ] UI-BE-006 — Bulk approve and reject reason for timesheet entries
- **Objective:** Efficient approvals.
- **Current State:** Approvals are one entry at a time. Whether a reject note column exists on `time_logs` requires verification against the schema.
- **Problem:** Heavy weekly review.
- **Proposed Change:** A bulk decide endpoint that runs a policy check per entry inside a transaction, plus an optional reason field (with a schema change if the column is missing).
- **UX Reason:** Efficiency.
- **Files/Components Affected:** `TimesheetApprovalController`, request, tests, and possibly a migration.
- **Routes Affected:** A new route · **APIs Affected:** New · **Database Impact:** Possibly a new column.
- **Dependencies:** TIME-005.
- **Feasibility:** Tech M · FE M · BE M · Arch ✓ · Perf M · A11y L · Regr M · Mobile L · Scope M.
- **Implementation Steps:** Planned on approval.
- **Acceptance Criteria:** Per-entry authorization is enforced.
- **UI Tests:** Selection bar · **Functional Tests:** New tests · **Responsive Tests:** 375 · **Accessibility Tests:** Checkbox column.
- **Regression Tests:** Single decide still works.
- **Performance Checks:** Query count.
- **Risks:** Partial failures.
- **Rollback Plan:** Revert, plus a migration rollback.
- **Approval Required:** Yes.
- **Commit Requirement:** `feat: bulk timesheet decisions`

## [ ] UI-BE-007 — Meeting start and complete transitions
- **Objective:** Expose the existing `in_progress` and `completed` statuses.
- **Current State:** The enum has these statuses, but only a cancel route exists (`routes/web.php:139-144`).
- **Problem:** Meetings can't move through their lifecycle in the UI.
- **Proposed Change:** Start and complete actions with policy checks. They may be tied to the timer; that is a product decision.
- **UX Reason:** Workflow completeness.
- **Files/Components Affected:** `MeetingController`, `MeetingPolicy`, tests, `meetings/show.tsx`.
- **Routes Affected:** New routes · **APIs Affected:** New · **Database Impact:** None.
- **Dependencies:** A product decision.
- **Feasibility:** Tech L · FE L · BE M · Arch ✓ · Perf none · A11y L · Regr L · Mobile L · Scope S.
- **Implementation Steps:** Planned on approval.
- **Acceptance Criteria:** Status transitions are valid and authorized.
- **UI Tests:** Buttons · **Functional Tests:** New tests · **Responsive Tests:** 375 · **Accessibility Tests:** Labels.
- **Regression Tests:** Cancel still works.
- **Performance Checks:** None.
- **Risks:** Business rule definition.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes, a product decision.
- **Commit Requirement:** `feat: meeting start and complete transitions`
