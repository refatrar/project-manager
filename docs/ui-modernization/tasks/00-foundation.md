# UI Tasks — 00 Foundation (TEST · FIX · DEP · DS)

Status key: `[ ]` not started · `[~]` in progress · `[x]` done (committed). All tasks start `[ ]` and **require approval before starting**.

**Shorthand used in every task:**
- **Feasibility** is written as: Tech · Frontend · Backend · Architecture compatibility · Performance risk · Accessibility risk · Regression risk · Mobile risk · Scope. Each is rated L/M/H; ✓ means compatible.
- **R-STD** is the standing regression checklist in `../02-plan.md` §P.
- **Static** means `npm run types:check && npm run check`, plus `pint --dirty --format agent && phpstan analyse` if PHP was touched.
- **Shots** means Playwright screenshots of the named pages at 375/768/1024/1440 in light and dark (UI-TEST-001 script).
- **Axe** means an axe-core scan on the same pages, with 0 new serious or critical issues.
- **Commit** uses the format `ui: implement <ID> <description>` and excludes `public/build` (D-5). It is made only after you approve the results.
- **Rollback** is `git revert <sha>` unless stated otherwise.

---

## Phase 0 — Testing foundation

## [x] UI-TEST-001 — Baseline screenshots, axe and bundle report
- **Objective:** Capture before-state evidence so every later task can show a before/after comparison.
- **Current State:** There are no frontend tests. MEMORY.md:139 documents ad-hoc `npx -p playwright@1.48` use. Bundle sizes are known only from the committed `public/build`.
- **Problem:** UI regressions and visual ripple can't be measured.
- **Proposed Change:** Add `tests/ui/capture.mjs`, run through `npx --yes -p playwright@1.48 -p @axe-core/playwright`, so `package.json` doesn't change. It logs in as a configured Team Lead, Member and Admin (credentials from environment variables, never committed). It visits a page list, saves screenshots to a gitignored `storage/ui-baseline/`, writes axe JSON, and runs `vp build` into a temporary `--outDir` to report raw and gzip chunk sizes.
- **UX Reason:** Evidence-based design review.
- **Files/Components Affected:** `tests/ui/capture.mjs` (new), `tests/ui/pages.json` (new), `.gitignore` (+`storage/ui-baseline`).
- **Routes Affected:** None (read-only visits).
- **APIs Affected:** None.
- **Database Impact:** None. It uses existing seeded accounts. Which seeded accounts map to each role requires verification.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf n/a · A11y n/a · Regr L · Mobile n/a · Scope S.
- **Implementation Steps:**
  1. Confirm the dev server on :8000 and :5173 is already running; don't start another (MEMORY.md:117).
  2. Write the script, waiting on specific selectors rather than `networkidle` (MEMORY.md:139).
  3. Capture a baseline of all 40 pages.
  4. Produce `storage/ui-baseline/report.md`.
- **Acceptance Criteria:** One command produces screenshots, axe results and a size table. The script is idempotent. No secrets are committed.
- **UI Tests:** Script runs green against the current app.
- **Functional Tests:** n/a.
- **Responsive Tests:** 4 widths are captured.
- **Accessibility Tests:** Axe baseline is recorded, with a count per page.
- **Regression Tests:** `git status` shows only the new script files.
- **Performance Checks:** Baseline gzip sizes are recorded (entry, vendor, CSS, each page).
- **Risks:** Playwright download needs network access; Chrome channel availability.
- **Rollback Plan:** Delete the script files.
- **Approval Required:** Yes, and D-4.
- **Commit Requirement:** `ui: implement UI-TEST-001 baseline capture script`

## [ ] UI-TEST-002 — Component test harness (optional, needs D-4)
- **Objective:** Unit-test the shared patterns and formatters.
- **Current State:** No JS test runner.
- **Problem:** Date/duration logic (a known UTC+6 trap) and pattern behaviour are untested.
- **Proposed Change:** Add the devDependencies `vitest`, `@testing-library/react`, `@testing-library/user-event` and `jsdom`, plus `vitest.config.ts` and a `test:ui` script. vite-plus may already bundle a test runner, which requires verification (`vp test`). If it does, no new dependency is needed.
- **UX Reason:** Prevents regressions in shared building blocks.
- **Files/Components Affected:** `package.json`, `package-lock.json`, `vitest.config.ts`, `resources/js/**/*.test.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** D-4 approval.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none (dev only) · A11y n/a · Regr L · Mobile n/a · Scope S.
- **Implementation Steps:**
  1. Check whether `vp test` exists.
  2. Install the minimal set, or none.
  3. Add a smoke test for `cn()`.
- **Acceptance Criteria:** `npm run test:ui` passes; production bundle is unchanged.
- **UI Tests:** Smoke test passes.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** n/a.
- **Regression Tests:** `npm run build` output is identical in size.
- **Performance Checks:** Production bundle delta is 0.
- **Risks:** Conflicts between vite-plus and vitest versions.
- **Rollback Plan:** Revert the commit and `npm ci`.
- **Approval Required:** Yes (new devDependencies).
- **Commit Requirement:** `ui: implement UI-TEST-002 component test harness`

## [ ] UI-TEST-003 — Page-contract tests for pages without `assertInertia`
- **Objective:** Each of the 40 pages has a PHP test asserting its component name and top-level prop keys.
- **Current State:** 32 test files use `assertInertia`. Coverage per page requires verification.
- **Problem:** Refactors could silently drop a prop.
- **Proposed Change:** List pages lacking a contract test, then add `test_the_<page>_page_renders_with_its_props` tests only. No production code changes.
- **UX Reason:** Guards against regressions.
- **Files/Components Affected:** `tests/Feature/**` (new or extended tests only).
- **Routes Affected:** None (GET only). **APIs Affected:** None.
- **Database Impact:** Test database only.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE none · BE L (tests) · Arch ✓ · Perf n/a · A11y n/a · Regr L · Mobile n/a · Scope M (≈10–15 tests).
- **Implementation Steps:**
  1. Grep `Inertia::render` against the test coverage.
  2. Write the missing tests following RULES.md §9 naming, including the unauthenticated and cross-team cases where missing.
- **Acceptance Criteria:** Every page component has a contract test; the suite is green on MySQL.
- **UI Tests:** n/a.
- **Functional Tests:** New tests pass.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** n/a.
- **Regression Tests:** Full `php artisan test` suite passes.
- **Performance Checks:** n/a.
- **Risks:** None to production.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-TEST-003 page contract tests`

---

## Phase 0.5 — Behavioural UI fixes (each changes visible behaviour; approve individually)

## [x] UI-FIX-001 — Projects list "Update" uses per-row `can_update`
- **Objective:** Show the Update action to exactly the users the server allows.
- **Current State:** The button is gated by team-wide `can('projects.create')` (`pages/projects/index.tsx:250`). The server sends `can_update` per row (`ProjectController.php:75-79`).
- **Problem:** A project lead without create rights can't edit from the list, and users with create rights see Update on projects they can't update, getting a 403 on save.
- **Proposed Change:** Gate on `project.can_update`.
- **UX Reason:** Actions shown should match permission.
- **Files/Components Affected:** `pages/projects/index.tsx`, and possibly `types/oms.ts` if the type lacks `can_update`.
- **Routes Affected:** None. **APIs Affected:** None (the prop already exists). **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y none · Regr L · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Change the condition.
  2. Confirm the type.
- **Acceptance Criteria:** As a Member who leads project A only, Update shows on A only. As a Team Lead, it shows on all.
- **UI Tests:** Visual check of both roles.
- **Functional Tests:** Update saves for permitted rows; `ProjectControllerTest` passes.
- **Responsive Tests:** 375px row layout unchanged.
- **Accessibility Tests:** n/a.
- **Regression Tests:** R-STD (projects list).
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes. This is a behaviour change.
- **Commit Requirement:** `ui: implement UI-FIX-001 gate project update by can_update`

## [x] UI-FIX-002 — "Overdue only" filter matches the server definition
- **Objective:** The List tab's overdue filter excludes Done and Cancelled tasks, matching `Task::overdue()` and the Overview card.
- **Current State:** `components/projects/task-list-view.tsx:83` checks only `due_at < now`.
- **Problem:** Finished tasks appear as overdue, and the counts disagree between tabs.
- **Proposed Change:** Exclude closed statuses. Use a closed flag if the payload provides one, otherwise the status values that `TaskStatus::isClosed()` treats as closed. Whether the board payload carries a closed flag requires verification, and the list must not be duplicated.
- **UX Reason:** Trustworthy numbers.
- **Files/Components Affected:** `task-list-view.tsx` (and `lib/status.ts` once CMP-002 exists).
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ (RULES.md: no duplicated business rules; if no flag exists, raise UI-BE instead of hardcoding) · Perf none · A11y none · Regr L · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Inspect `toBoardArray()`.
  2. Implement the filter.
- **Acceptance Criteria:** List overdue count equals the Overview Overdue card for the same project.
- **UI Tests:** Seed a done task with a past due date; it's excluded.
- **Functional Tests:** Other filters are unaffected.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** n/a.
- **Regression Tests:** R-STD (workspace List).
- **Performance Checks:** None.
- **Risks:** If closed statuses have to be hardcoded, that duplicates an enum rule. Mitigation: add an `is_closed` field in a UI-BE task instead.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-002 overdue filter excludes closed tasks`

## [ ] UI-FIX-003 — Publishing minutes cannot send unsaved text
- **Objective:** Prevent emailing attendees stale minutes.
- **Current State:** Publish is a separate PATCH with no body (`components/meetings/minutes-editor.tsx:50-61`). Emails go out immediately (`MeetingController.php:248-255`).
- **Problem:** Text typed but not saved is silently excluded from the published minutes.
- **Proposed Change:** Track dirty state. While dirty, the Publish button reads "Save & publish" and first performs the existing save, then publish (two existing endpoints, called in sequence). Show "Unsaved changes" hint.
- **UX Reason:** Prevents an irreversible error.
- **Files/Components Affected:** `minutes-editor.tsx`.
- **Routes Affected:** None. **APIs Affected:** None (existing PUT minutes, then PATCH publish). **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr M (email side effect) · Mobile none · Scope S.
- **Implementation Steps:**
  1. Compare the form against its initial values.
  2. Chain save then publish; publish only on save success.
  3. Surface errors.
- **Acceptance Criteria:** Unsaved text is always included in what's published. A save failure blocks publishing.
- **UI Tests:** Dirty hint appears and clears after save.
- **Functional Tests:** `MeetingMinutesControllerTest` passes. Manual check with the mail log shows the latest text.
- **Responsive Tests:** Buttons wrap at 375px.
- **Accessibility Tests:** The dirty hint is announced (`aria-live=polite`).
- **Regression Tests:** R-STD (meeting create), minutes save alone.
- **Performance Checks:** At most 2 requests.
- **Risks:** Partial success (save succeeds, publish fails) must show a clear message.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-003 save before publishing minutes`

## [ ] UI-FIX-004 — Display enum labels from server options
- **Objective:** Stop client-side label mangling.
- **Current State:** There are 21 `.replace('_',' ')` calls (first underscore only, lowercase), plus about 6 raw `{x.status}` renders (`sprint-list.tsx:63`, `allocation-list.tsx:90`, `todo-list-card.tsx:101,159`, `time-off-request-list.tsx:67`, `module-tree.tsx:126`). This violates RULES.md §3/§10.
- **Problem:** Wrong labels such as "ready_for qa", and inconsistent casing.
- **Proposed Change:** Add a helper `optionLabel(options, value)` and pass the page's existing `*Options` props down. Where a page lacks the options prop for an enum, list it for a UI-BE follow-up rather than hardcoding labels.
- **UX Reason:** Consistent, correct terminology.
- **Files/Components Affected:** About 20 feature components; `lib/enum.ts` (new).
- **Routes Affected:** None. **APIs Affected:** None, unless a missing option prop is found; that becomes a UI-BE item. **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE M (breadth) · BE none · Arch ✓ · Perf none · A11y L · Regr M · Mobile none · Scope M (≈20 files).
- **Implementation Steps:**
  1. Grep for the patterns.
  2. Map each to its options prop.
  3. Replace.
  4. List any gaps.
- **Acceptance Criteria:** `grep -r "replace('_'" resources/js` returns 0; every label matches the enum's `label()`.
- **UI Tests:** Shots of badges on the board, lists and time pages.
- **Functional Tests:** n/a.
- **Responsive Tests:** Longer labels still fit (e.g. "Ready for QA").
- **Accessibility Tests:** n/a.
- **Regression Tests:** R-STD.
- **Performance Checks:** None.
- **Risks:** Options missing in some payloads.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-004 enum labels from server options`

## [ ] UI-FIX-005 — Work schedule "today" uses local date, not UTC
- **Objective:** Fix the off-by-one default date.
- **Current State:** `pages/admin/work-schedules/index.tsx:75` uses `toISOString()`. MEMORY.md:225 documents this trap; `timesheet/index.tsx:29-39` does it correctly.
- **Problem:** Between 00:00 and 06:00 local time (UTC+6), the default effective date is yesterday.
- **Proposed Change:** Use the local-date helper (moves into `lib/format.ts` in CMP-001).
- **UX Reason:** Correct defaults.
- **Files/Components Affected:** `admin/work-schedules/index.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y none · Regr L · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Replace the function.
- **Acceptance Criteria:** With the system clock at 02:00 UTC+6, the default is today.
- **UI Tests:** Manual check with a faked clock.
- **Functional Tests:** `WorkScheduleControllerTest` passes.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** n/a.
- **Regression Tests:** Schedule save.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-005 local date default for work schedules`

## [ ] UI-FIX-006 — Decide dialogs keep their title while closing
- **Objective:** Remove the Reject → Approve title flicker.
- **Current State:** `decision ?? 'approved'` at `pages/timesheet-approvals/index.tsx:103` and `components/time-off/time-off-approval-queue.tsx:68`.
- **Problem:** A confusing flash during the close animation.
- **Proposed Change:** Keep the last decision in state until the close animation finishes.
- **UX Reason:** Predictable dialogs.
- **Files/Components Affected:** The 2 files above.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y none · Regr L · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Separate the `open` state from the `decision` state.
- **Acceptance Criteria:** No title change while closing, in either theme.
- **UI Tests:** Screen recording or visual check.
- **Functional Tests:** Approve and reject still work (`TimesheetApprovalControllerTest`, `TimeOffRequestControllerTest`).
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Focus returns to the trigger.
- **Regression Tests:** Both queues.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-006 stable decide dialog titles`

## [ ] UI-FIX-007 — Sidebar highlights the parent section on detail pages
- **Objective:** Projects stays active on `/projects/5` and `/projects/5/tasks/9`; Meetings stays active on `/meetings/3`.
- **Current State:** `components/nav-main.tsx:29` uses exact `isCurrentUrl`. `isCurrentOrParentUrl` exists in `hooks/use-current-url.ts`.
- **Problem:** The user loses their location in the navigation.
- **Proposed Change:** Use parent matching and add `aria-current="page"`. The same change goes in the admin nav (`layouts/admin-layout.tsx:50`) and the settings nav.
- **UX Reason:** Wayfinding.
- **Files/Components Affected:** `nav-main.tsx`, `admin-layout.tsx`, `layouts/settings/layout.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Swap the matcher.
  2. Guard the Dashboard `/dashboard` prefix so it doesn't over-match.
- **Acceptance Criteria:** Exactly one active item on every page.
- **UI Tests:** Shots of 5 detail pages.
- **Functional Tests:** n/a.
- **Responsive Tests:** Mobile sheet shows the same highlight.
- **Accessibility Tests:** `aria-current` present.
- **Regression Tests:** R-STD.
- **Performance Checks:** None.
- **Risks:** Prefix collisions such as `/time-logs` vs `/time-off-requests`; test all items.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-007 parent-aware active navigation`

## [ ] UI-FIX-008 — Team switcher slug replacement and no-op on the current team
- **Objective:** Switching teams rewrites the URL correctly and doesn't reload when the current team is chosen.
- **Current State:** `currentUrl.includes('/slug')` substring logic (`team-switcher.tsx:350`) plus a second visit (`:339-359`).
- **Problem:** The slug `acme` also matches `/acme-corp`; there's an unnecessary request.
- **Proposed Change:** Replace only the first path segment when it equals the old slug. Return early for the current team.
- **UX Reason:** Reliable switching.
- **Files/Components Affected:** `components/team-switcher.tsx`.
- **Routes Affected:** None (same `switch` route). **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf + · A11y none · Regr M · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Segment-based replace.
  2. Early return.
- **Acceptance Criteria:** Switching from `acme` to `acme-corp` and back lands on the equivalent page.
- **UI Tests:** Manual check with two similar slugs.
- **Functional Tests:** `Teams/*` tests pass.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** n/a.
- **Regression Tests:** R-STD (team switch).
- **Performance Checks:** One fewer request.
- **Risks:** Pages whose URL doesn't start with the slug (settings); keep the current fallback.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-008 team switcher slug handling`

## [ ] UI-FIX-009 — Natural tab order on login pages
- **Objective:** Remove positive `tabIndex` values.
- **Current State:** `pages/auth/login.tsx:71,118` (values 1–5, with 5 used twice) and `pages/admin/auth/login.tsx:32-66`.
- **Problem:** Keyboard focus order breaks (WCAG 2.4.3).
- **Proposed Change:** Remove them; DOM order already matches the visual order.
- **UX Reason:** Accessibility.
- **Files/Components Affected:** The 2 pages.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Delete the props.
  2. Tab through the page.
- **Acceptance Criteria:** Tab order is email, password, remember, submit, then links.
- **UI Tests:** Keyboard pass.
- **Functional Tests:** `Auth/*` and `Admin/AuthTest` pass.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Axe shows no `tabindex` warnings.
- **Regression Tests:** Login, passkey login.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-009 natural tab order on login`

## [ ] UI-FIX-010 — Task page gates subtask and checklist actions like the server
- **Objective:** Hide actions the user can't perform.
- **Current State:** "Add subtask" (`pages/projects/tasks/show.tsx:226`) and checklist edit/delete/promote (`task-checklist.tsx:120-156`) are ungated, while Edit is gated by `canManageTask` (`:102`).
- **Problem:** Users can open forms that then fail with 403. Server enforcement requires verification per endpoint.
- **Proposed Change:** Gate on `canManageTask`, or on the checklist's policy if it differs. Verify against `TodoListPolicy`/`TaskPolicy` first; the UI follows the server, it doesn't change it.
- **UX Reason:** Don't offer impossible actions.
- **Files/Components Affected:** `tasks/show.tsx`, `task-checklist.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y none · Regr M (could hide something a user legitimately uses; the policy check decides) · Mobile none · Scope S.
- **Implementation Steps:**
  1. Read the policies.
  2. Map each action to its ability.
  3. Gate.
- **Acceptance Criteria:** For each role, the visible actions equal the permitted actions (table in the PR notes).
- **UI Tests:** Member vs Lead check.
- **Functional Tests:** Checklist and subtask tests pass.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** n/a.
- **Regression Tests:** R-STD (task create/edit).
- **Performance Checks:** None.
- **Risks:** If the server allows actions today that the UI would hide, stop and report instead of hiding.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-010 gate task page actions`

## [ ] UI-FIX-011 — Render validation errors that are currently dropped
- **Objective:** Every server validation error is visible.
- **Current State:** Time-log form never shows `project_id` or `description` errors (`time-log-form.tsx:102,159`). Availability search shows no errors (`pages/availability/index.tsx`, controller `:40`). Time-off `decision_note` errors aren't shown. Team-role description errors aren't shown.
- **Problem:** A save fails with no explanation.
- **Proposed Change:** Add `InputError` for each missing field.
- **UX Reason:** Error recovery.
- **Files/Components Affected:** `time-log-form.tsx`, `availability/index.tsx`, `decide-time-off-request-modal.tsx`, `admin/team-roles/index.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. For each form, compare its FormRequest rules with the rendered errors.
  2. Add the missing ones.
- **Acceptance Criteria:** Submitting invalid data shows a message under every invalid field.
- **UI Tests:** Invalid submit for each form.
- **Functional Tests:** Existing tests pass.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Errors are linked once DS-009 lands.
- **Regression Tests:** Valid submits are unchanged.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-011 show missing validation errors`

## [ ] UI-FIX-012 — Remove the placeholder "Meeting actions" card from My Day
- **Objective:** Stop telling users they have no meeting actions when they may.
- **Current State:** Hardcoded text at `pages/my-day/index.tsx:106-115`; the controller comment is stale (`MyDayController.php:27-28`).
- **Problem:** The page shows false information.
- **Proposed Change:** Remove the card (D-7). Real data comes with UI-BE-005 and a later UI task.
- **UX Reason:** Trust.
- **Files/Components Affected:** `my-day/index.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** D-7.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y none · Regr L · Mobile + · Scope XS.
- **Implementation Steps:**
  1. Delete the card.
  2. Rebalance the grid.
- **Acceptance Criteria:** No placeholder remains; the layout is balanced at all 4 widths.
- **UI Tests:** Shots.
- **Functional Tests:** `MyDayControllerTest` passes.
- **Responsive Tests:** 4 widths.
- **Accessibility Tests:** n/a.
- **Regression Tests:** My Day toggles.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes, and D-7.
- **Commit Requirement:** `ui: implement UI-FIX-012 remove placeholder meeting actions card`

## [ ] UI-FIX-013 — Toast every `useHttp` success message in admin
- **Objective:** Admins get feedback after each save.
- **Current State:** These actions drop `response.message`: create user (`admin/users/index.tsx:73`); create, rename and assign lead on teams (`admin/teams/index.tsx:34,75,139`); holidays create and delete; admins create; team-role update (`team-roles/index.tsx:189`).
- **Problem:** Silent successes lead to repeated submissions.
- **Proposed Change:** Call `toast.success(response.message)` in `onSuccess`, matching `EditUserForm:155`. It gets centralised later in CMP-015.
- **UX Reason:** System status visibility.
- **Files/Components Affected:** 5 admin pages.
- **Routes Affected:** None. **APIs Affected:** None (messages already returned). **Database Impact:** None.
- **Dependencies:** None.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y L · Regr L · Mobile none · Scope S.
- **Implementation Steps:**
  1. Add the toasts.
  2. Verify the server messages exist.
- **Acceptance Criteria:** Every admin mutation shows one toast.
- **UI Tests:** Each action.
- **Functional Tests:** `Admin/*` tests pass.
- **Responsive Tests:** Toast position on mobile.
- **Accessibility Tests:** Toast is announced politely.
- **Regression Tests:** Admin CRUD.
- **Performance Checks:** None.
- **Risks:** Duplicate toast if a flash also fires; verify.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-FIX-013 admin success toasts`

---

## Phase 1a — Dependencies

## [ ] UI-DEP-001 — Add the missing shadcn/Radix primitives packages
- **Objective:** Enable accessible tabs, confirmations, popovers, switches, radios and scroll areas.
- **Current State:** 13 `@radix-ui/*` packages are installed. Tabs are hand-rolled (`projects/show.tsx:194`). Confirmations are built on Dialog.
- **Problem:** Hand-rolled widgets lack keyboard and ARIA behaviour.
- **Proposed Change:** Install `@radix-ui/react-tabs`, `react-alert-dialog`, `react-popover`, `react-switch`, `react-radio-group` and `react-scroll-area`. Nothing is used yet; that happens in CMP tasks.
- **UX Reason:** Accessible behaviour without custom code.
- **Files/Components Affected:** `package.json`, `package-lock.json`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** D-3.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ (same family) · Perf L (tree-shaken, only when imported) · A11y + · Regr L · Mobile none · Scope XS.
- **Implementation Steps:**
  1. `npm install` with versions compatible with React 19.
  2. Build.
- **Acceptance Criteria:** Build passes; bundle unchanged until the packages are imported.
- **UI Tests:** n/a.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** n/a.
- **Regression Tests:** Build and R-STD smoke.
- **Performance Checks:** Record a delta of 0.
- **Risks:** Peer-dependency warnings.
- **Rollback Plan:** Revert and `npm ci`.
- **Approval Required:** Yes (new dependencies).
- **Commit Requirement:** `ui: implement UI-DEP-001 radix primitive packages`

## [ ] UI-DEP-002 — Add `cmdk` (command palette and combobox)
- **Objective:** Searchable pickers and a keyboard navigation palette.
- **Current State:** People, project and task pickers are plain Selects without search. The dependency picker lists every task (`task-dependency-editor.tsx:174-195`).
- **Problem:** Large lists are slow to use, and there is no keyboard jump navigation (the skill's "keyboard-shortcuts" must-have for productivity tools).
- **Proposed Change:** Install `cmdk` (the shadcn `command` base).
- **UX Reason:** Speed for power users.
- **Files/Components Affected:** `package.json`, lockfile.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** D-3.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf L (≈10 KB gzip when used; lazy-load the palette) · A11y + · Regr L · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Install.
  2. Build.
- **Acceptance Criteria:** Build passes.
- **UI Tests:** n/a.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** n/a.
- **Regression Tests:** Build.
- **Performance Checks:** Delta recorded.
- **Risks:** Low.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes (new dependency).
- **Commit Requirement:** `ui: implement UI-DEP-002 cmdk dependency`

---

## Phase 1 — Design system foundation

## [ ] UI-DS-001 — Audit current tokens and finalise the design-system document
- **Objective:** Lock the token values after decisions D-1 and D-2.
- **Current State:** `docs/design-system/README.md` is PROPOSED. Tokens live at `resources/css/app.css:10-186`.
- **Problem:** Some values are marked *verify*.
- **Proposed Change:** Run contrast checks (both themes) for every foreground/background pair and update the document to FINAL with measured ratios. Documentation only.
- **UX Reason:** One source of truth.
- **Files/Components Affected:** `docs/design-system/README.md`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** D-1, D-2.
- **Feasibility:** Tech L · FE none · BE none · Arch ✓ · Perf none · A11y + · Regr none · Mobile none · Scope S.
- **Implementation Steps:**
  1. Compute ratios.
  2. Adjust values that fail.
  3. Mark FINAL.
- **Acceptance Criteria:** Every pair is documented with a ratio of at least 4.5 (text) or 3 (UI).
- **UI Tests:** n/a.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Ratios table.
- **Regression Tests:** n/a.
- **Performance Checks:** n/a.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-001 finalise design tokens document`

## [ ] UI-DS-002 — Light-mode colour tokens
- **Objective:** Implement the §2.1 tokens.
- **Current State:** Neutral shadcn palette. `--destructive-foreground` is identical to `--destructive`. There are no success, warning or info tokens.
- **Problem:** No brand accent; status meaning forced into 4 neutral badge variants; raw palette classes.
- **Proposed Change:** Update `:root` and switch to `@theme inline` mappings. Add `--success|warning|info` (+`-foreground`, `-subtle`, `-subtle-foreground`), `--subtle-foreground`, `--overlay` and `--primary-hover`. Keep the heatmap, chart and status tokens.
- **UX Reason:** Semantic, accessible colour.
- **Files/Components Affected:** `resources/css/app.css`.
- **Routes Affected:** None (visual change on every page). **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** DS-001.
- **Feasibility:** Tech L · FE M (global ripple) · BE none · Arch ✓ · Perf none · A11y + · Regr M (visual) · Mobile none · Scope S (1 file, whole app affected).
- **Implementation Steps:**
  1. Edit the tokens.
  2. Build.
  3. Take shots of all pages in light mode.
  4. Fix the 3 places misusing `destructive-foreground` as red text (`team-capacity/index.tsx:103`, `heatmap.tsx:126,238`) by switching to `text-destructive`.
- **Acceptance Criteria:** All pages render; the primary button is teal (per D-1); no contrast regressions in axe.
- **UI Tests:** Full shot diff, light mode.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Axe contrast rules.
- **Regression Tests:** R-STD visual.
- **Performance Checks:** CSS delta under 1 KB.
- **Risks:** Unintended ripple. Mitigation: full-page shot review.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-002 light colour tokens`

## [ ] UI-DS-003 — Dark-mode tokens and no-flash background
- **Objective:** Implement §2.2 and fix the sidebar-primary bug.
- **Current State:** `app.css:176-177` has identical sidebar primary and foreground values. `app.blade.php:23-31` duplicates the oklch background values. `app-logo.tsx:11` hardcodes `text-white dark:text-black`.
- **Problem:** Invisible text on sidebar-primary surfaces; the workaround is hardcoded.
- **Proposed Change:** Update `.dark` tokens, sync the blade inline background, and remove the logo workaround.
- **UX Reason:** Dark theme parity.
- **Files/Components Affected:** `app.css`, `resources/views/app.blade.php`, `app-logo.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** DS-002.
- **Feasibility:** Tech L · FE M · BE L (blade only) · Arch ✓ · Perf none · A11y + · Regr M · Mobile none · Scope S.
- **Implementation Steps:**
  1. Edit tokens.
  2. Update blade.
  3. Take dark shots.
- **Acceptance Criteria:** No flash on load for light, dark or system; the logo is readable in both themes; axe passes in dark.
- **UI Tests:** Dark shot diff.
- **Functional Tests:** Appearance switcher works for all 3 modes.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Axe in dark.
- **Regression Tests:** R-STD in dark.
- **Performance Checks:** None.
- **Risks:** Blade and CSS values drifting apart; add a comment linking them.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-003 dark colour tokens`

## [ ] UI-DS-004 — Typography tokens and font
- **Objective:** Implement §3.
- **Current State:** Instrument Sans 400–600; no scale; arbitrary `text-[10px]`/`[11px]`.
- **Problem:** Inconsistent hierarchy; unreadable small text.
- **Proposed Change:** Load the D-2 font from bunny.net, including the 700 weight if needed. Set `--font-sans`. Add `.tabular-nums` usage guidance and a `font-feature-settings` default for numbers in tables.
- **UX Reason:** Readability and hierarchy.
- **Files/Components Affected:** `app.blade.php`, `app.css`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** D-2, DS-001.
- **Feasibility:** Tech L · FE L · BE L (blade) · Arch ✓ · Perf L (one font request, swap) · A11y L · Regr M (text reflow) · Mobile L · Scope XS.
- **Implementation Steps:**
  1. Swap the font link.
  2. Update the variable.
  3. Take shots.
- **Acceptance Criteria:** No layout breakage (truncation, wrapping) on all pages at 375px.
- **UI Tests:** Shot diff.
- **Functional Tests:** n/a.
- **Responsive Tests:** 4 widths.
- **Accessibility Tests:** n/a.
- **Regression Tests:** R-STD visual.
- **Performance Checks:** Font bytes before and after.
- **Risks:** Wider glyphs overflowing fixed widths (`w-40`).
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-004 typography tokens`

## [ ] UI-DS-005 — Radius, elevation, z-index and motion tokens with reduced-motion rule
- **Objective:** Implement §4 and §21.
- **Current State:** `--radius .625rem`; no motion tokens; no reduced-motion handling.
- **Problem:** Motion ignores user preference.
- **Proposed Change:** Set `--radius .5rem`. Add motion duration and easing tokens. Add a global `@media (prefers-reduced-motion: reduce)` rule that disables transform animations. Add z-index layer variables.
- **UX Reason:** Consistency and comfort.
- **Files/Components Affected:** `app.css`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** DS-002.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Add the tokens.
  2. Verify with the OS reduced-motion setting on.
- **Acceptance Criteria:** Dialogs and sheets appear without sliding when reduced motion is on.
- **UI Tests:** Manual check.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Reduced-motion check.
- **Regression Tests:** Visual radius diff.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-005 radius motion and layering tokens`

## [ ] UI-DS-006 — Button: loading state, destructive token, coarse-pointer size, icon-only type
- **Objective:** Implement §6.
- **Current State:** `ui/button.tsx` has shadcn variants; destructive uses `text-white` (`:14`); no loading state. 8 files use native `<button>`.
- **Problem:** No consistent pending feedback; icon buttons can be unnamed.
- **Proposed Change:**
  - Add a `loading` prop (spinner, `aria-busy`, width kept).
  - Destructive uses `text-destructive-foreground`.
  - Add `pointer-coarse:` minimum sizes.
  - Export an `IconButton` wrapper whose TypeScript props require `aria-label`.
  - Existing call sites are unchanged; adoption happens in the sweeps.
- **UX Reason:** Feedback and accessibility.
- **Files/Components Affected:** `ui/button.tsx`, `ui/icon-button.tsx` (new).
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** DS-002, DS-005.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L (additive) · Mobile + · Scope XS.
- **Implementation Steps:**
  1. Extend the cva.
  2. Add the wrapper.
  3. Document it.
- **Acceptance Criteria:** The API is backward compatible; types:check passes; `loading` renders a spinner.
- **UI Tests:** Storybook-like demo page not included. Verify via one consumer: `project-form` submit.
- **Functional Tests:** n/a.
- **Responsive Tests:** Touch sizes checked in emulation.
- **Accessibility Tests:** `aria-busy` present.
- **Regression Tests:** 107 consumers checked visually via shots.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-006 button states and icon button`

## [ ] UI-DS-007 — Badge variants (success, warning, info, neutral; subtle and solid)
- **Objective:** Implement §14.
- **Current State:** 4 variants only (`ui/badge.tsx`).
- **Problem:** Status semantics are lost.
- **Proposed Change:** Add tone and style variants. Existing variant names keep their current look.
- **UX Reason:** Scannable status.
- **Files/Components Affected:** `ui/badge.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** DS-002.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile none · Scope XS.
- **Implementation Steps:**
  1. Extend the cva.
  2. Check contrast of the subtle variants in both themes.
- **Acceptance Criteria:** New variants meet 4.5:1 text contrast.
- **UI Tests:** Visual matrix.
- **Functional Tests:** n/a.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Contrast.
- **Regression Tests:** Existing badges unchanged.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-007 semantic badge variants`

## [ ] UI-DS-008 — Input, Select, Textarea and Checkbox states
- **Objective:** Consistent heights, `aria-invalid` styling, disabled vs read-only distinction, and a 3:1 boundary.
- **Current State:** shadcn defaults; `aria-invalid` styles exist but are never triggered.
- **Problem:** Invalid fields look normal; the boundary contrast is low.
- **Proposed Change:** Use the `--input` token for borders, add a `read-only` style, and make heights match the button sizes. Keep `text-base md:text-sm`.
- **UX Reason:** Clear affordance.
- **Files/Components Affected:** `ui/input.tsx`, `ui/select.tsx`, `ui/textarea.tsx`, `ui/checkbox.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** DS-002.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr M (40+ consumers) · Mobile L · Scope S.
- **Implementation Steps:**
  1. Edit the class strings.
  2. Take shots of all forms.
- **Acceptance Criteria:** Control boundary is at least 3:1 in both themes; invalid state is visible.
- **UI Tests:** Form shots.
- **Functional Tests:** n/a.
- **Responsive Tests:** iOS zoom avoided (16px below md).
- **Accessibility Tests:** Contrast.
- **Regression Tests:** All modals render.
- **Performance Checks:** None.
- **Risks:** Radix Select `'none'` sentinel untouched (no logic change).
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-008 form control states`

## [ ] UI-DS-009 — `FormField` composite and tokenised `InputError`
- **Objective:** Labels, hints and errors wired with `aria-describedby` and `aria-invalid`.
- **Current State:** `InputError` uses `text-red-600` (`input-error.tsx:12`). 0 `aria-invalid` and 1 `aria-describedby` in the app.
- **Problem:** Screen readers don't hear errors; colour is hardcoded.
- **Proposed Change:** `components/patterns/form-field.tsx`, with render-prop or `Slot` injection of `id` and aria attributes. `InputError` switches to `text-destructive`. Adopted by FORM-001…004.
- **UX Reason:** Accessible forms.
- **Files/Components Affected:** New `patterns/form-field.tsx`; `input-error.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** DS-008.
- **Feasibility:** Tech M (Slot composition with Radix Select trigger) · FE M · BE none · Arch ✓ · Perf none · A11y + · Regr L (additive) · Mobile none · Scope S.
- **Implementation Steps:**
  1. Build the component.
  2. Adopt it in one form (`label-form.tsx`) as the pilot.
- **Acceptance Criteria:** In the pilot, VoiceOver/NVDA reads the error on focus; `aria-invalid` toggles.
- **UI Tests:** Pilot form.
- **Functional Tests:** Label CRUD passes.
- **Responsive Tests:** n/a.
- **Accessibility Tests:** Screen-reader check plus axe.
- **Regression Tests:** Setup labels.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-009 form field composite`

## [ ] UI-DS-010 — Dialog layout: max height, scroll body, sticky footer, mobile width
- **Objective:** Every dialog fits the viewport.
- **Current State:** `ui/dialog.tsx:58` has no max height. Only `project-form-modal.tsx:63` handles it. The task form overflows.
- **Problem:** Save becomes unreachable on phones and short laptop screens.
- **Proposed Change:**
  - `DialogContent` gets `max-h-[min(90dvh,48rem)]` and a flex column layout.
  - Add `DialogBody` (scrolling) and a sticky `DialogFooter`.
  - Size variants sm, md and lg.
  - Full width below 640px.
  - Overlay uses the `--overlay` token.
  - Existing dialogs get scroll behaviour through a safe default of `overflow-y-auto` on content, until they adopt `DialogBody` in module tasks.
- **UX Reason:** Reachability.
- **Files/Components Affected:** `ui/dialog.tsx`, `project-form-modal.tsx` (use the shared pattern).
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** DS-005.
- **Feasibility:** Tech L · FE M (44 consumers) · BE none · Arch ✓ · Perf none · A11y + · Regr M · Mobile + · Scope S.
- **Implementation Steps:**
  1. Edit the primitive.
  2. Open all 42 modals at 375×667 and 1280×720.
- **Acceptance Criteria:** Every modal's primary button is reachable at 375×667.
- **UI Tests:** Modal shot matrix.
- **Functional Tests:** Submit from each large modal (task, project, meeting).
- **Responsive Tests:** 375 and 768.
- **Accessibility Tests:** Focus trap intact; Esc works.
- **Regression Tests:** R-STD.
- **Performance Checks:** None.
- **Risks:** Nested scroll areas in the project form; convert it to the shared pattern.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-010 scrollable dialog layout`

## [ ] UI-DS-011 — Alert variants, progress bar colour, toaster placement
- **Objective:** Implement §15–16.
- **Current State:** Alert has default and destructive only. `team-invitation-alert.tsx:14-17` hand-rolls blue. The progress bar is `#4B5563` (`app.tsx:40-42`). Sonner is bottom-right at every width.
- **Problem:** Hardcoded colours; weak progress visibility in dark mode.
- **Proposed Change:**
  - Add Alert variants info, success and warning.
  - Migrate the invitation alert.
  - Set the progress colour to `var(--primary)`.
  - Toaster goes bottom-centre below 640px.
  - Error toasts persist until dismissed.
- **UX Reason:** Consistent feedback.
- **Files/Components Affected:** `ui/alert.tsx`, `team-invitation-alert.tsx`, `app.tsx`, `ui/sonner.tsx`.
- **Routes Affected:** None. **APIs Affected:** None. **Database Impact:** None.
- **Dependencies:** DS-002.
- **Feasibility:** Tech L · FE L · BE none · Arch ✓ · Perf none · A11y + · Regr L · Mobile + · Scope S.
- **Implementation Steps:**
  1. Extend the variants.
  2. Migrate the invitation alert.
  3. Configure the toaster.
- **Acceptance Criteria:** No raw palette classes in these files; toasts are visible and don't cover focused elements on mobile.
- **UI Tests:** Shots.
- **Functional Tests:** Accepting an invitation still works.
- **Responsive Tests:** Toast at 375px.
- **Accessibility Tests:** Contrast; `aria-live` on the toaster.
- **Regression Tests:** Flash toasts on redirect.
- **Performance Checks:** None.
- **Risks:** None.
- **Rollback Plan:** Revert.
- **Approval Required:** Yes.
- **Commit Requirement:** `ui: implement UI-DS-011 alert toast and progress styling`
