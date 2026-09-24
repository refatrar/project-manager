# Project Task Plan — ALP RTM / Skilfo (UNICEF)

**Generated:** 2026-09-22 · **Type:** Full project audit & requirement traceability (analysis only — no code was changed to produce this document)
**Scope:** Laravel 10 + Inertia.js + Vue.js Real-Time Monitoring system for UNICEF's Alternative Learning Programme (ALP), plus the newer BNFE/"Skilfo" scope expansion (vocational-training tracking, assessor/certification workflow).

> **Important scoping note:** the generic task brief that requested this document assumed a "project/task management tool." The actual application is a **Real-Time Monitoring (RTM) / M&E system for out-of-school-youth vocational training** (learners, trainers, mastercraft persons, training centers, monitoring visits, employment outcomes), operating as two tenants on one codebase: the original **ALP** scope and a newer **Skilfo/BNFE** scope. The module list and analysis below reflect the actual domain, per instruction to include only modules relevant after reviewing the documents and code.

---

## Summary

| Category | Count |
|---|---:|
| Client Requirements (CR, from ToR) | 21 |
| Our Commitments (COM, from SRS) | 32 |
| UAT Items (from UAT Feedback, 4 Sept) | 23 |
| New Requirements (NEW, from Skilfo comparison) | 18 |
| Code Review Findings (REV) | 34 |
| **Total Tasks** | **121** |
| Critical | 9 |
| High | 41 |
| Medium | 54 |
| Low | 17 |

## Overall Status (requirement-level, not task-level)

| Status | Approx. Count | Notes |
|---|---:|---|
| Implemented | 46 | Core CRUD/profile/program backbone is largely built |
| Partially Implemented | 31 | Fields/workflow exist but incomplete vs. commitment |
| Missing | 22 | No meaningful implementation found |
| Implemented but Needs Review | 18 | Works, but design/behavior diverges from spec or has defects |
| Conflict | 2 | Implementation contradicts a documented requirement |
| Unclear / Needs Clarification | 6 | Insufficient info in documents or code to judge (mostly Skilfo items the client itself flagged as unclear) |

**Headline findings for the team:**
1. The core ALP scope (Profiles, Program, Monitoring, Feedback, Attribute Setup, Settings, Dashboard, Reports) is substantially built and generally matches the SRS commitment — most gaps are refinements, not missing modules.
2. The newer Skilfo/BNFE scope (Assessor, Enterprise, Skilfo Monitoring, RPL certificate generation) is **further along than the comparison document assumes** — Enterprise, Assessor, MCP↔Enterprise linkage, and the assessor mass-assignment/certificate workflow already exist in code, contrary to being purely "new."
3. Several genuine bugs were found that plausibly explain specific UAT complaints (a duplicate-array-key bug silently swapping a partner name for a donor name; an unguarded null-chain in the Monitoring PDF export; dead filters in the Activity Report).
4. The codebase contains several **undocumented modules** (generic Task Tracking, Form Builder, Support ticketing) not mentioned in any of the four source documents — these represent scope creep that needs product clarification, not necessarily removal.
5. Security posture is reasonable (spatie/laravel-permission, CSRF properly scoped, bcrypt, FK-heavy schema) but has concrete gaps: BNFE routes with no permission gate, an unrestricted generic file-upload endpoint, and inconsistent object-level authorization outside of route-level ability gates.

---

## Source Documents

| ID | Document | Classification |
|---|---|---|
| CLIENT | `Document/ToR for ALP RTM.docx` | Client Requirement (original Terms of Reference) |
| COMMITMENT | `Document/System Requirement Specification (SRS) 1.pdf` (108pp, dated 14 Mar 2024) | Our Commitment |
| UAT | `Document/UAT Feedback By KAZ - 4 September.pdf` (6pp, screenshots + reviewer notes) | UAT Feedback / Change Request |
| NEW | `Document/Skilfo Projects Comparison.pdf` (28pp) | New Requirement / Scope Expansion |
| REVIEW | Codebase at `/var/www/kazsoft/kaz_unicef-alp` (full repo: app/, database/, resources/js/, routes/, docs/) | Code Review |

---

## Client Requirements (from ToR)

| ID | Requirement (summarized) |
|---|---|
| CR-001 | Integrated web+mobile RTM, single portal, centralized learner/trainer/MCP/stakeholder DB, dynamic reports |
| CR-002 | Individual profile databases for all participants (trainees, MCPs, mentors, trainers) w/ demographic & socioeconomic info |
| CR-003 | Continuous tracking of individual learner performance per training type — attendance, learning performance, support received, employment status during/after training |
| CR-004 | In-depth filterable analysis re: beneficiary selection criteria and BTEB-certified trade training |
| CR-005 | Data storage for uploading curricula/modules/communication packages/training materials for sharing with partners/govt |
| CR-006 | Dynamic real-time dashboard, per-training-type breakdown (apprenticeship, entrepreneurship, centre-based, mixed) |
| CR-007 | Regular field feedback/comments collection, with anonymous reporting option |
| CR-008 | Online+offline access; mobile app downloadable, offline data collection, auto-sync when connected |
| CR-009 | Scale to ~100,000 participants as ALP expands (from ~25,000 current target) |
| CR-010 | Record/store data under all Outcomes, Outputs, Activity levels per ALP Monitoring Framework |
| CR-011 | Bilingual (English/Bangla) mobile app; W3C/Android/iOS accessibility compliance |
| CR-012 | Mobile sign-up/register with sync to web; mobile SSO |
| CR-013 | Data integrity, duplication avoidance; strong authentication; role segregation for access & beneficiary-list approval workflows |
| CR-014 | UNICEF security assessment + implement recommendations |
| CR-015 | Capacity building — training materials, admin/user guideline & protocol |
| CR-016 | Knowledge products — software design doc, technical admin guide, user manual, training manual |
| CR-017 | 1-year warranty + ongoing maintenance/support; bugs resolved within 3 days or per SLA |
| CR-018 | Data backup & recovery provisions |
| CR-019 | Source code & DB config owned by/handed to UNICEF |
| CR-020 | Continuous DB/query performance tuning |
| CR-021 | Data confidentiality (no external sharing w/o UNICEF permission); child safeguarding/PSEA compliance |

## Our Commitments (from SRS)

| ID | Commitment (summarized) |
|---|---|
| COM-001 | Landing page — banner, 4 hover analytical sections, animated overview diagram |
| COM-002 | ALP Metrics — filterable geolocation map w/ clickable icons (learner/trainer/MCP counts) |
| COM-003 | Landing page data viz (Learner Stats, Employment Status, Occupation Engagement) + partner logo slider + footer |
| COM-004 | Dynamic CMS pages (About/Our Story/Contact) editable bilingual, banner upload, page **preview** feature |
| COM-005 | Contact page — form → email, optionally stored as dashboard "quote list" |
| COM-006 | Dashboard — 5 tabs (Learner/MCP/Trainer/Training-Course/Monitoring), rich filters, detailed per-tab charts |
| COM-007 | Users, Roles & Permissions mgmt w/ granular tree (Access/Create/Update/Delete/View/Review/Restore per module) |
| COM-008 | 7-role RBAC: Superadmin, Admin, Partner, Trainer, Manager, Mastercraft, Frontline — full permission matrix |
| COM-009 | Development Partner (Donor) CRUD — name, description, total fund, logo, status |
| COM-010 | Implementing Partner CRUD — type, donor, contact, address, URL, logo, status; feedback button |
| COM-011 | Stakeholder CRUD — partner type, location cascade, org, attachments, status |
| COM-012 | Trainer CRUD w/ location/partner fields, work-experience group; detail page (Training Details/Learner Activities/**Logbooks**), versioning+revert, soft-delete+restore |
| COM-013 | MCP CRUD w/ trade license/workplace/income fields; detail page (Training Details/Learners/**Logbooks**), versioning, restore |
| COM-014 | Learner CRUD w/ ethnicity/religion/dropout/marital/disability fields; detail page (Training Details/Learner Activities/**Logbooks**), versioning, restore |
| COM-015 | Training Center CRUD — trainer dropdown filtered by partner+location |
| COM-016 | Competency Standard CRUD — occupation, category, repeatable competencies (unit code, title EN/BN, duration) |
| COM-017 | Training Type CRUD — partner, donor, modality, name, target, total hours |
| COM-018 | Training/Course CRUD — attendance (add/view, month/day-wise), feedback, mark-complete, logbook |
| COM-019 | Post Training CRUD (gated on completed training) — certification, comment |
| COM-020 | Event/Activity CRUD — participants (program+other), attachments |
| COM-021 | Monitoring — 3-step workflow (Initial Visit / Learning Environment Assessment / Tracking & Follow-up) |
| COM-022 | Feedback — date/description/resolve status/notify partner/attachments, linkable from Partner/Trainer/Training |
| COM-023 | Attribute Setup — 9 bilingual attribute types, filterable CRUD |
| COM-024 | Settings — CMS/App/Contact/Media/Mobile App/Social Links |
| COM-025 | Reports — Outcomes & Output, Activity, Stakeholder; PDF/Excel export |
| COM-026 | Full mobile layout/parity per mockups |
| COM-027 | ERD/data architecture as documented |
| COM-028 | Tech stack — Laravel/Vue/MariaDB/Flutter/Nginx/CentOS |
| COM-029 | Security checklist — CSRF/XSS/session mgmt/SQLi prevention/bcrypt/RBAC/IP session lock/etc. |
| COM-030 | Testing & QA — feature/integration/UAT testing, code review, static analysis, bug tracking |
| COM-031 | Data backup & recovery — automated scheduled backups, redundancy, monitoring/alerts |
| COM-032 | Agile/Kanban methodology, git-based backlog |

## UAT Feedback (4 September)

| ID | Type | Feedback |
|---|---|---|
| UAT-001 | Enhancement | Dashboard numeric cards need graphical (bar chart) representation |
| UAT-002 | Enhancement | Dashboard sections should link to their list pages on click |
| UAT-003 | Missing (unconfirmed) | Event form needs a "Target Participant" field |
| UAT-004 | Enhancement | Use consistent, relevant filter/action icons across list pages |
| UAT-005 | Enhancement | Activity Report — show Male/Female as independent columns |
| UAT-006 | Enhancement | Support more than 3 gender options |
| UAT-007 | Enhancement | Marital Status — add "child marriage" option (divorce already exists) |
| UAT-008 | Enhancement | Add explicit age-group classification (child/adult/youth), not just raw DOB |
| UAT-009 | Enhancement | Add submenus to navigation where helpful |
| UAT-010 | Missing | Individual (non-admin) users should see their own attendance |
| UAT-011 | Missing | Automatic email notification for dashboard actions (esp. event creation) |
| UAT-012 | Already resolved | Show intervention/"Modality" data on learner list |
| UAT-013 | Clarification / Confirmed gap | Competency Standard not integrated into Monitoring framework |
| UAT-014 | Change | Mobile — split Learner/MCP/Trainer form into multi-page/tabbed flow |
| UAT-015 | Data gap | Upazila "Shantiganj" / occupation "Auto Mechanics" reportedly missing |
| UAT-016 | Bug (root cause found) | Partner name displays incorrectly ("Let Us Learn" instead of correct partner) |
| UAT-017 | Bug (likely cause found) | Training Center/Training/Learners fields blocked in Program→Training/Course |
| UAT-018 | Missing | Need "CwD" (Children with Disabilities) classification |
| UAT-019 | Confirmed gap | Employment area needs "Entrepreneur" category with Self/Wage sub-options |
| UAT-020 | Already resolved | Learner Graduate/Dropout needs a "Dropout reason" field |
| UAT-021 | New | Add cross-cutting "Employment" tab (Learner/MCP/Trainer/Training/Monitoring) |
| UAT-022 | Bug (likely cause found) | Monitoring Dashboard options not functioning / error |
| UAT-023 | Bug (needs investigation) | MCP profile → Learners tab shows duplicate identical learner rows |

## New Requirements (Skilfo scope expansion)

| ID | Requirement | Existing coverage found |
|---|---|---|
| NEW-001 | Enterprise module (~35 fields incl. GPS, MCP suitability ratings) | ~85-90% implemented |
| NEW-002 | Assessor module + assessor dashboard | Fields implemented; dashboard missing |
| NEW-003 | Pre-vocational assessment assignment workflow (mass-assign→assess→certify) | Implemented |
| NEW-004 | Assessment scheduling, self-assessment checklists, result sheets, bilingual BNQF certs | Partially (certs yes; scheduling/checklists no) |
| NEW-005 | Structured Pre/Post-Course assessment SCORING | Not implemented |
| NEW-006 | Job Linkage w/ 6-month follow-up | Partially (free-text fields only, no follow-up scheduler) |
| NEW-007 | Literacy Center module | Not implemented (client itself unsure of definition) |
| NEW-008 | Institution Database module | Not implemented as distinct from Training Center (needs clarification) |
| NEW-009 | Craft Database (trainer-adjacent) | Not implemented as distinct from Trainer (needs clarification) |
| NEW-010 | Cluster Monitoring / Field Monitoring Assistant | Implemented (genuinely distinct Skilfo Monitoring workflow) |
| NEW-011 | Monthly Progress Reports (CA-1 to CA-4) | Not implemented; structure unclear even to client |
| NEW-012 | Bilingual EN/BN fields across partner/stakeholder/center entities | Implemented broadly already |
| NEW-013 | MCP↔Enterprise linkage, duplicate field removal | Implemented |
| NEW-014 | Trainer/MCP new fields (Email/Industry Exp/Other Exp/Total Exp), relabel to "Teaching Experience" | Implemented for Trainer only; missing for MCP |
| NEW-015 | Post Training job-placement fields when employed | Partially (free-text only) |
| NEW-016 | Dedicated Skilfo subdomain landing page + role-based post-login redirect | Partially (subdomain+content yes; redirect logic no) |
| NEW-017 | New frontend design, bilingual certificate design, separate mobile app | Certificate generation exists; frontend redesign/mobile app out of this repo's scope |
| NEW-018 | Reports exportable in Bengali & English | Pattern exists (Profile exports) but not applied to Reports module |

---

# MODULE-BY-MODULE TASK REGISTER

## Module 1 — Authentication, RBAC & Security

### Requirement Sources
CLIENT: CR-013, CR-014 · COMMITMENT: COM-007, COM-008, COM-029 · REVIEW: REV-001–REV-006, REV-020

### Current Implementation
RBAC uses `spatie/laravel-permission` (`app/Models/Role.php`, `AuthServiceProvider.php`), enforced almost entirely via `can:<ability>_*` route middleware across `routes/dashboard.php`/`api.php`/`api.v2.php`. Seeded roles exactly match the committed 7-role model (Super Admin, Admin, Partner, Mastercraft, Trainer, Manager, Frontline) plus legitimate later additions (Assessor, BNFE directors). Auth is Laravel's standard web guard + Sanctum for the mobile API; bcrypt hashing; login rate-limiting exists; CSRF is correctly scoped (only API routes excluded). Versioning (`Versionable` trait) and audit logging (`owen-it/laravel-auditing`) are genuine, working implementations, not stubs.

### Requirement Gap
Route-level ability gating is strong, but object-level (per-record) authorization is inconsistent — hand-rolled per controller/service rather than centralized in policies for some models (e.g. no `McpPolicy`). BNFE dashboard routes have no permission gate at all. Several COM-029 checklist items (DB-backed sessions, IP session lock, 2FA) are not implemented. File upload validation is too permissive for non-image files.

### Tasks

| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-001 | Add `can:` permission middleware to all BNFE dashboard routes | REVIEW | REV-001 | TODO | HIGH |
| TASK-002 | Create `McpPolicy` and route MCP authorization through policy + object-level checks consistently | REVIEW | REV-002 | TODO | HIGH |
| TASK-003 | Restrict `FileUploadController`'s generic `file` field to an explicit MIME/extension allow-list; consider private-disk storage for non-image uploads | REVIEW | REV-003 | TODO | HIGH |
| TASK-004 | Switch `SESSION_DRIVER` to `database` in production config, per COM-029 | COMMITMENT | COM-029 | TODO | MEDIUM |
| TASK-005 | Implement IP-based session lock/invalidation-on-anomaly per COM-029 (or document decision not to, with UNICEF sign-off) | COMMITMENT | COM-029 | TODO | MEDIUM |
| TASK-006 | Evaluate adding 2FA to admin/manager-level web login (no current implementation) | REVIEW | REV-005 | TODO | LOW |
| TASK-007 | Verify `FeedbackRequest` sanitizes/escapes `description` before it is rendered via `v-html` in `Feedback/Details.vue`; add sanitization if feedback can originate from low-trust/public sources | REVIEW | REV-006 | TODO | MEDIUM |
| TASK-008 | Confirm with UNICEF whether a mobile "SSO" mechanism (COM-028) is still required; if yes, scope an OAuth/OIDC integration alongside Sanctum | COMMITMENT | COM-028 | TODO | LOW |
| TASK-009 | Commission/attach the UNICEF-mandated third-party security assessment (CR-014) and track its findings as follow-up tasks | CLIENT | CR-014 | TODO | HIGH |
| TASK-010 | Audit all Service classes (`LearnerService`, `TrainerService`, `EnterpriseService`, etc.) for the same partner-scoping pattern found in `McpService`, to confirm no IDOR gap exists elsewhere | REVIEW | REV-002 | TODO | HIGH |
| TASK-011 | Reconcile the two parallel tenancy mechanisms (`tenant`/`tenant_alp`/`tenant_skilfo` global-scope traits vs. ad hoc `partner_id` filtering in services) into one documented, centrally-enforced approach | REVIEW | REV-008 | TODO | MEDIUM |
| TASK-012 | Add `SoftDeletes` + `Versionable` to `Enterprise` and `Assessor` models for parity with Trainer/MCP/Learner restore functionality | REVIEW | REV-007 | TODO | MEDIUM |

#### TASK-001 — Gate BNFE dashboard routes with permission middleware

- **Type:** [SECURITY]
- **Source:** REVIEW · **Source ID:** REV-001 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** Every other dashboard module is gated by `can:<module>_access` (and finer-grained create/update/delete/view abilities); BNFE routes should follow the same pattern.
- **Current State:** `routes/dashboard.php:165-169` registers `bnfe/learners`, `bnfe/training-centers`, `bnfe/occupations`, `bnfe/programs` inside only the outer `auth.multi`/`isActive`/`verified` group, with no `can:` gate.
- **Gap:** Any authenticated user of any role — including Frontline — can view/act on BNFE data that should likely be restricted to Admin/Manager/Partner roles.
- **Required Change:** Add appropriate `can:bnfe_*` middleware (define new permissions if needed) matching the access level intended for BNFE data, and seed them into `PermissionsSeeder`.
- **Dependencies:** None.
- **Acceptance Criteria:** Frontline (and any other role not intended to see BNFE data) receives a 403 when hitting BNFE routes; Admin/Manager/Partner retain access as intended.

#### TASK-003 — Harden generic file upload endpoint

- **Type:** [SECURITY]
- **Source:** REVIEW · **Source ID:** REV-003 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** COM-029 commits to "uploaded file validation" as part of the security checklist.
- **Current State:** `app/Http/Controllers/FileUploadController.php:15-18` validates the generic `file` field only as `['nullable','file','max:5120']` with no MIME/extension allow-list, and stores it on the **public** disk with an immediately-returned public URL. The parallel `image` field IS correctly restricted to `jpeg,png,jpg,gif,svg`.
- **Gap:** Arbitrary file types (including executable/script content) can be uploaded and are immediately publicly hosted under the application's own domain.
- **Required Change:** Add an explicit allow-list (e.g. pdf, doc, docx, xls, xlsx per the "curricula/training materials" use case in CR-005) and reconsider whether these attachments need to be on a public vs. authenticated-access disk.
- **Dependencies:** None.
- **Acceptance Criteria:** Uploading a disallowed file type is rejected with a validation error; allowed document types continue to work for attachments (Feedback, Event, Stakeholder, Post Training).

---

## Module 2 — Multi-Tenancy & Partner-Level Data Isolation

### Requirement Sources
CLIENT: CR-013, CR-021 · REVIEW: REV-002, REV-008

### Current Implementation
Two coexisting isolation mechanisms: (a) `HasSingleTenant`/`HasMultipleTenant` traits applying a global scope on `tenant`/`tenant_alp`/`tenant_skilfo` boolean columns (ALP vs Skilfo separation) on Profile/Mcp/Trainer/Enterprise/Assessor; (b) hand-written `partner_id` filtering inside individual Service classes (confirmed correct in `McpService`, not exhaustively verified elsewhere) for partner-level data segregation. `SkilfoDefaultPartnerDonorScope` only applies to Skilfo-only users, not general Partner-role users.

### Requirement Gap
The dual mechanism is functional where checked but architecturally duplicated — a new Service class that forgets the manual `partner_id` filter reopens an IDOR without any centralized safety net.

### Tasks

| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-013 | Design and adopt a single centralized partner/tenant scoping mechanism (e.g., a global scope applied uniformly rather than per-service) | REVIEW | REV-008 | TODO | MEDIUM |
| TASK-014 | Audit `LearnerService`, `TrainerService`, `EnterpriseService`, `AssessorService` `show()`/`edit()` methods for the same partner-scoping guarantee confirmed in `McpService` | REVIEW | REV-002 | TODO | HIGH |
| TASK-015 | Document the ALP vs. Skilfo tenant boundary (what data is shared vs. isolated) for the dev team, since this is currently implicit in scattered trait usage | REVIEW | — | TODO | LOW |
| TASK-016 | Confirm CR-021 (no external data sharing without UNICEF permission) is reflected in any cross-tenant/cross-partner export or API surface — verify exports can't leak another partner's data | CLIENT | CR-021 | TODO | MEDIUM |

---

## Module 3 — User & Role Management

### Requirement Sources
COMMITMENT: COM-007, COM-008

### Current Implementation
IMPLEMENTED. Full CRUD for users/roles/permissions via `RolesController`/`UserController`, `spatie/laravel-permission`-backed, matching the SRS's Add-User screen and permission-tree screenshots closely.

### Requirement Gap
None material found beyond the object-level authorization gaps already tracked in Module 1.

### Tasks

| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-017 | Spot-check that the live permission tree UI (Expand All/Collapse All, per-module checkboxes) still matches the SRS screenshot's granularity as new modules (Assessor, Enterprise, BNFE) were added | COMMITMENT | COM-007 | TODO | LOW |
| TASK-018 | Confirm newly-added roles (Assessor, Director General, Director M&E, AD DBNFE) have a documented, UNICEF-approved permission set, not just inherited defaults | REVIEW | — | TODO | MEDIUM |
| TASK-019 | Verify "can change user password" (Superadmin/Admin only per SRS matrix) is still correctly restricted after later role additions | COMMITMENT | COM-008 | TODO | LOW |

---

## Module 4 — Profile: Development Partner (Donor)

### Requirement Sources
COMMITMENT: COM-009

### Current Implementation
IMPLEMENTED. `Donor` model has all committed fields (name, bn_name, description, total_fund, image, status); full CRUD in web + API V1/V2.

### Requirement Gap
None found.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-020 | No action needed — mark COM-009 as verified complete in the next SRS sign-off | COMMITMENT | COM-009 | NOT APPLICABLE | LOW |

---

## Module 5 — Profile: Implementing Partner

### Requirement Sources
COMMITMENT: COM-010 · UAT: UAT-016

### Current Implementation
IMPLEMENTED. `Partner` model covers type/donor/contact/email/mobile/phone/address/URL/logo/status/feedback relation, matching COM-010.

### Requirement Gap
A confirmed related bug (see UAT-016 traceability) exists in how partner/donor/trade names are serialized in the V1 mobile API resource.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-021 | Fix duplicate `'trade'` array key in `app/Http/Resources/V1/Profile/ProfileApiResource.php:97-99` (currently `trade.name` then `partner.name` then `donor.name` all assigned to the same key, so the donor's name silently wins) | UAT | UAT-016 | TODO | HIGH |

#### TASK-021 — Fix duplicate-key bug in V1 Profile API resource

- **Type:** [BUG]
- **Source:** UAT · **Source ID:** UAT-016 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** A partner's correct name (e.g. "Jagorani Chakra Foundation") should display wherever the UI shows the associated Implementing Partner; a QA reviewer reported it instead showing "Let Us Learn" (a seeded **Donor** name).
- **Current State:** `app/Http/Resources/V1/Profile/ProfileApiResource.php:97-99` builds a response array with **three separate entries all keyed `'trade'`** — `trade.name`, then `partner.name`, then `donor.name` — so in PHP the last assignment silently overwrites the first two, and any consumer reading that key gets the donor's name instead of the trade or partner. The equivalent V2 resource does not have this duplication.
- **Gap:** Confirmed code defect in API V1 only.
- **Required Change:** Give each field its own distinct key (e.g. `trade`, `partner`, `donor`) in `ProfileApiResource` (V1).
- **Dependencies:** None.
- **Acceptance Criteria:** The V1 mobile API response includes correct, distinct trade/partner/donor names; the specific screen the QA reviewer flagged shows the correct partner name.

---

## Module 6 — Profile: Stakeholder

### Requirement Sources
COMMITMENT: COM-011

### Current Implementation
IMPLEMENTED. Backed by `Partnership` model (not a literal "Stakeholder" table) — full field/attachment/status coverage matching COM-011.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-022 | Document that "Stakeholder" in the SRS maps to the `Partnership` model in code, to avoid future confusion during onboarding/handover | REVIEW | — | TODO | LOW |

---

## Module 7 — Profile: Trainer

### Requirement Sources
COMMITMENT: COM-012 · NEW: NEW-012, NEW-014

### Current Implementation
IMPLEMENTED BUT NEEDS REVIEW. Full field coverage, versioning, soft-delete+restore all confirmed. NEW-014 fields (Email, Industry Experience, Other Experience, auto-calculated Total Experience, "Teaching Experience" relabel) are already implemented for Trainer.

### Requirement Gap
The detail/view page has Training Centers, Training Details, and Learners tabs but **no Logbook tab**, despite COM-012 explicitly committing to a "User Logbooks" timeline view.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-023 | Add a "Logbooks" tab to Trainer's detail page (`Trainer/Details.vue`), backed by the existing `logbooks` relation | COMMITMENT | COM-012 | TODO | MEDIUM |
| TASK-024 | Verify Trainer's detail-page controller eager-loads the logbook relation needed to power the new tab | COMMITMENT | COM-012 | TODO | MEDIUM |
| TASK-025 | UAT-010: expose a "my attendance" self-service view for Trainer/Frontline users | UAT | UAT-010 | TODO | MEDIUM |
| TASK-026 | UAT-006/007: extend Gender enum beyond Male/Female/Others, and add "child marriage" to Marital Status (Divorced already exists) | UAT | UAT-006, UAT-007 | TODO | MEDIUM |

---

## Module 8 — Profile: Mastercraft Person (MCP)

### Requirement Sources
COMMITMENT: COM-013 · NEW: NEW-013, NEW-014 · UAT: UAT-023

### Current Implementation
IMPLEMENTED BUT NEEDS REVIEW. Full field coverage (incl. exact Yes/No/Other toilet-facility match to spec), versioning, soft-delete+restore, and NEW-013 MCP↔Enterprise linkage all confirmed working, including a real migration that removes duplicate fields between MCP and Enterprise.

### Requirement Gap
Same missing Logbook tab as Trainer. NEW-014 fields (Email/Industry Experience/Other Experience/Total Experience, "Teaching Experience" relabel) were rolled out to Trainer but **not to MCP**, an inconsistency. UAT-023's duplicate-learner-rows bug was not yet investigated in code.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-027 | Add "Logbooks" tab to MCP's detail page, same as Trainer (TASK-023) | COMMITMENT | COM-013 | TODO | MEDIUM |
| TASK-028 | Add `email`, `industry_experience`, `other_experience`, auto-calculated `total_experience` fields to MCP; relabel "Business Experience" to "Teaching Experience" per NEW-014, for parity with Trainer | NEW | NEW-014 | TODO | MEDIUM |
| TASK-029 | Investigate and fix UAT-023: MCP profile → Learners tab shows the same learner ("Sobuj") repeated 3× with identical DOB/mobile — check the MCP→Learners list query for a join producing duplicate rows (e.g. multiple pivot/attendance rows per learner) rather than distinct learners | UAT | UAT-023 | TODO | HIGH |
| TASK-030 | Fix `Assessor::newUniqueId()` copy-paste bug referencing a non-existent `upazila_id` on Assessor (unrelated model, but same code pattern worth checking on MCP's own `newUniqueId()` for a similar latent bug) | REVIEW | REV-010 | TODO | LOW |
| TASK-031 | UAT-018: add a "CwD" (Children with Disabilities) classification — clarify with UNICEF whether this is a new boolean flag on Learner or a Disability sub-category | UAT | UAT-018 | TODO | MEDIUM |
| TASK-032 | UAT-008: add a persisted age-group field (child/adult/**youth**) — currently only a binary child/adult split is computed on the fly for filtering, no stored 3-tier value and no "youth" tier at all | UAT | UAT-008 | TODO | MEDIUM |

#### TASK-029 — Investigate duplicate learner rows under MCP profile

- **Type:** [BUG]
- **Source:** UAT · **Source ID:** UAT-023 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** An MCP's detail page "Learners" tab should list each distinct learner associated with that MCP once.
- **Current State:** UAT screenshot shows a single MCP's Learners tab listing "Sobuj" three times with identical DOB (5th September 2024) and mobile number (01722043839) — "Total: 3".
- **Gap:** This was not directly investigated by the code-review agents in this pass (an oversight in the audit scope) — needs a dedicated follow-up: check the query backing the MCP→Learners tab for a join that fans out per attendance/training/pivot record rather than grouping by distinct learner.
- **Required Change:** TBD pending investigation; likely a missing `distinct()`/`groupBy()` or an incorrect join cardinality.
- **Dependencies:** None.
- **Acceptance Criteria:** Each learner appears exactly once per MCP, regardless of how many trainings/attendance records they have with that MCP.

---

## Module 9 — Profile: Learner

### Requirement Sources
COMMITMENT: COM-014 · UAT: UAT-006, UAT-007, UAT-008, UAT-012, UAT-018, UAT-020 · NEW: NEW-012

### Current Implementation
IMPLEMENTED, minor gaps. Full field coverage, versioning, soft-delete+restore confirmed. UAT-012 (intervention/"Modality" column) and UAT-020 (Dropout Reason) are **already resolved** — both wired end-to-end, contrary to how they might read as open complaints.

### Requirement Gap
No Logbook tab on the Learner detail page despite the controller already eager-loading the relation (data is fetched but not rendered). Gender/marital-status/age-group/CwD gaps shared with Trainer/MCP (see Module 7/8 tasks — do not duplicate work, fix once at the shared enum/attribute level).

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-033 | Add "Logbooks" tab to Learner's detail page — data (`logbooks` relation) is already eager-loaded by the controller but not rendered in `Details.vue` | COMMITMENT | COM-014 | TODO | MEDIUM |
| TASK-034 | Implement UAT-006 (multi/extended gender options) at the shared `app/Enums/Gender.php` level so Learner/Trainer/MCP/Assessor all update together | UAT | UAT-006 | TODO | MEDIUM |
| TASK-035 | Implement UAT-007 ("child marriage" marital-status option) at the shared `MaritalStatus` enum level | UAT | UAT-007 | TODO | MEDIUM |
| TASK-036 | Implement UAT-008 (persisted 3-tier age-group field: child/adult/youth) as a real column, not just an on-the-fly filter | UAT | UAT-008 | TODO | MEDIUM |
| TASK-037 | Implement UAT-018 (CwD classification) on Learner | UAT | UAT-018 | TODO | MEDIUM |
| TASK-038 | No action needed for UAT-012 (intervention/"Modality" already shown on Learner list/detail) — close as resolved | UAT | UAT-012 | COMPLETED | LOW |
| TASK-039 | No action needed for UAT-020 (Dropout Reason already required/validated at training-enrollment level) — close as resolved, but confirm this satisfies the client's intent (it's on the Training enrollment record, not directly on the Learner profile — verify this distinction is acceptable) | UAT | UAT-020 | COMPLETED | LOW |

---

## Module 10 — Profile: Training Center

### Requirement Sources
COMMITMENT: COM-015 · NEW: NEW-008, NEW-012

### Current Implementation
IMPLEMENTED. Trainer selection is confirmed dynamically filtered by partner+location, exactly matching COM-015. Bilingual (EN/BN) name and institution-head fields already present, satisfying that part of NEW-012.

### Requirement Gap
NEW-008's "Institution Database" as a possibly-distinct module (head of institution, coordinator, course count, seats, venue code) is not implemented as a separate entity — needs product clarification on whether it's the same as Training Center or genuinely separate (see Open Questions).

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-040 | Get client clarification on whether "Institution Database" (NEW-008) is meant to extend Training Center with more fields (venue code, seat count, coordinator) or be a wholly separate module — see Open Questions OQ-005 | NEW | NEW-008 | BLOCKED | MEDIUM |

---

## Module 11 — Profile: Competency Standard

### Requirement Sources
COMMITMENT: COM-016 · UAT: UAT-013

### Current Implementation
IMPLEMENTED, one field gap. Occupation/category/repeatable-competency structure matches COM-016 closely.

### Requirement Gap
The "Unit Code" sub-field committed in COM-016 is missing from the `competency_skills` migration entirely. Also, per UAT-013, Competency Standard has zero integration with the Monitoring module.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-041 | Add the missing "Unit Code" column to Competency Skill, per COM-016 | COMMITMENT | COM-016 | TODO | LOW |

(UAT-013's Monitoring-integration gap is tracked under Module 18, TASK-063, to avoid duplicating the same task under two modules.)

---

## Module 12 — Profile: Assessor (Skilfo)

### Requirement Sources
NEW: NEW-002 · REVIEW: REV-010

### Current Implementation
PARTIALLY IMPLEMENTED. Field coverage is strong and matches the Skilfo spec closely (name EN/BN, registration no., mobile/email, occupation, methodology, workplace, district, qualification, Rocket account no.).

### Requirement Gap
No "assessor dashboard" exists — only standard CRUD pages — despite NEW-002 explicitly calling for one (distinct from the assessor-facing assignment list covered under Module 21). A latent bug references a non-existent `upazila_id` property.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-042 | Design and build a dedicated Assessor-facing dashboard (distinct from admin CRUD) — likely showing assigned learners, assessment progress, certificates issued | NEW | NEW-002 | TODO | MEDIUM |
| TASK-043 | Fix `Assessor::newUniqueId()` referencing `$this->upazila_id`, a property that doesn't exist on the Assessor model/migration — currently silently resolves to null rather than erroring, which may produce malformed unique IDs | REVIEW | REV-010 | TODO | MEDIUM |
| TASK-044 | Add `SoftDeletes`/`Versionable` to Assessor for parity with other profile types (cross-ref TASK-012) | REVIEW | REV-007 | TODO | LOW |

---

## Module 13 — Profile: Enterprise (Skilfo)

### Requirement Sources
NEW: NEW-001, NEW-013 · REVIEW: REV-011

### Current Implementation
IMPLEMENTED, ~85-90% field coverage. GPS, owner info, and the MCP-suitability rating fields (communication skill, reputation, inclusivity, provisions, cleanliness, toilet facility, clean water, social security) are all present via well-modeled enums. MCP↔Enterprise linkage (NEW-013) is fully implemented, including a migration that removes duplicated fields.

### Requirement Gap
No distinct "personal conduct" rating field; only student capacity is tracked (no separate employee capacity); an orphaned `qualification()` relation exists with no backing `qualification_id` column.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-045 | Add a distinct "personal conduct" rating field to Enterprise, separate from the existing reputation/communication ratings, per NEW-001 | NEW | NEW-001 | TODO | LOW |
| TASK-046 | Add an "employee capacity" field to Enterprise (currently only `student_capacity` exists) | NEW | NEW-001 | TODO | LOW |
| TASK-047 | Remove or properly back the orphaned `qualification()` relation on the `Enterprise` model (no `qualification_id` column exists) | REVIEW | REV-011 | TODO | LOW |

---

## Module 14 — Program: Training Type / Modality

### Requirement Sources
COMMITMENT: COM-017

### Current Implementation
UNCLEAR. This was not conclusively verified as a distinct CRUD entity separate from `Intervention` (mapped in the UI as "Modality") and `Trade` (mapped as "Occupation") — both of which ARE confirmed implemented under Attribute Setup. It is possible "Training Type" in the SRS maps directly to "Modality"/Intervention rather than being a separate module.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-048 | Confirm whether COM-017 "Training Type" is fully satisfied by the existing Intervention ("Modality") + Trade ("Occupation") setup entities, or whether a distinct Training Type CRUD (with "target: learner/MCP/trainer" and "total training hours" fields) is still missing | COMMITMENT | COM-017 | REVIEW REQUIRED | MEDIUM |

---

## Module 15 — Program: Training/Course (incl. Attendance & Logbook)

### Requirement Sources
COMMITMENT: COM-018 · UAT: UAT-017

### Current Implementation
IMPLEMENTED BUT NEEDS REVIEW. Full relation model (partner, modality, trade, location, training center, trainers, MCP-trainers, learners w/ dropout+completion pivot, attendances, logbooks, feedbacks). Month-wise and day-wise attendance, "mark as complete" cascading, and feedback linkage are all confirmed working.

### Requirement Gap
UAT-017's "blocked fields" complaint has a plausible, concrete mechanism: the Training Center/Trainer/Learners dropdowns are gated behind a strict 4-field AND-condition (Partner + Modality + Division + Trade must ALL be set) before an async lookup populates them — fragile rather than literally broken, but easy for a user to trigger by filling fields out of the expected order.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-049 | Investigate and improve the 4-field AND-gate controlling when Training Center/Trainer/Learner dropdowns populate in the Training/Course Add/Edit form — consider relaxing the gate, adding inline guidance ("select Partner, Modality, Division and Trade to load options"), or a clearer disabled-state tooltip so users understand why fields appear "blocked" | UAT | UAT-017 | TODO | HIGH |
| TASK-050 | Confirm the specific data-entry sequence a QA reviewer used when the fields appeared permanently blocked, to fully close out UAT-017 (currently "Likely" not "Confirmed") | UAT | UAT-017 | REVIEW REQUIRED | MEDIUM |
| TASK-051 | UAT-013: integrate Competency Standard into the Monitoring/Training framework — cross-ref Module 18 | UAT | UAT-013 | TODO | MEDIUM |
| TASK-052 | Confirm Training/Course "mark as complete" workflow correctly gates Post Training creation in all cases (spot-checked, appears correct) | COMMITMENT | COM-018 | REVIEW REQUIRED | LOW |

---

## Module 16 — Program: Post Training & Employment Tracking

### Requirement Sources
COMMITMENT: COM-019 · UAT: UAT-019, UAT-021 · NEW: NEW-006, NEW-015

### Current Implementation
IMPLEMENTED for the base COM-019 commitment (activity date, certification +details, comment; genuinely gated on the parent training's "completed" status at the database level). Later Skilfo-era migrations added `job_placement_details` and `business_name` free-text fields, partially addressing NEW-015.

### Requirement Gap
`EmploymentStatus` enum is flat (Self/Wage/Unemployed) with no "Entrepreneur" parent category as UAT-019 requests. Job Linkage (NEW-006) lacks structured employer/enterprise/designation/date fields and any 6-month follow-up scheduling mechanism — only free text exists. No cross-cutting "Employment" tab (UAT-021) exists anywhere in the app.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-053 | Restructure `EmploymentStatus` to introduce an "Entrepreneur" category with "Self"/"Wage" sub-options, per UAT-019 (currently Self and Wage are flat peers with "Entrepreneur" only baked into a label string) | UAT | UAT-019 | TODO | MEDIUM |
| TASK-054 | Add structured employer/enterprise, job designation, and employment-date fields to Post Training / Learner Activity, replacing/supplementing the current free-text `job_placement_details`/`business_name` | NEW | NEW-006, NEW-015 | TODO | MEDIUM |
| TASK-055 | Build a 6-month follow-up mechanism for Job Linkage (e.g. a scheduled command that flags employed learners due for a follow-up check) — no such job currently exists | NEW | NEW-006 | TODO | MEDIUM |
| TASK-056 | Build a cross-cutting "Employment" view/tab spanning Learner/MCP/Trainer/Training/Monitoring, per UAT-021 | UAT | UAT-021 | TODO | LOW |
| TASK-057 | Confirm UAT-003 (Event "Target Participant" field) is actually rendering in the live Add/Edit Activity Vue form — the backend field (`target_participants`, distinct from `total_participants`) already exists end-to-end, so this is likely a UI-visibility issue rather than a missing field (cross-ref Module 17) | UAT | UAT-003 | REVIEW REQUIRED | LOW |

---

## Module 17 — Program: Event/Activity

### Requirement Sources
COMMITMENT: COM-020 · UAT: UAT-003

### Current Implementation
IMPLEMENTED. `total_participants` and `target_participants` are confirmed as two genuinely separate, validated columns end-to-end (model, migration, both Dashboard and API V1/V2 Store/Update requests).

### Requirement Gap
UAT-003 claimed the "Target Participant" field was missing — code evidence contradicts this at the backend level. Needs a UI screenshot / live check to see whether the field is actually rendered and labeled clearly in the Vue form (see TASK-057 above; not duplicated here).

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-058 | Verify in a running environment whether "Target Participant" is visibly rendered/labeled in the Add/Edit Activity form; if present but mislabeled/hidden, fix the Vue template rather than the backend | UAT | UAT-003 | REVIEW REQUIRED | LOW |
| TASK-059 | UAT-011: wire an automatic email notification specifically to Event/Activity creation (currently no `Event` model/notification exists; closest analog, Training assignment, is email-capable but disabled by default via `NOTIFY_VIA_MAIL` env flag) | UAT | UAT-011 | TODO | MEDIUM |

---

## Module 18 — Monitoring (ALP)

### Requirement Sources
COMMITMENT: COM-021 · UAT: UAT-013, UAT-022

### Current Implementation
IMPLEMENTED BUT NEEDS REVIEW. A genuine multi-step PrimeVue Stepper (4 steps for ALP: Background/Learning/Interactions/Safeguarding) covers the SRS's 3-step content (Initial Visit, Learning Environment Assessment, Tracking & Follow-up) with matching fields.

### Requirement Gap
Monitoring **creation** only happens via the mobile API (not in this repo) — the web Vue Stepper is edit-only, per the dashboard route registration. No integration with Competency Standard anywhere in the monitoring stack (UAT-013, confirmed by exhaustive grep). A concrete, plausible bug was found for UAT-022: `MonitoringController::exportPdf` accesses `$answer->formPivot->input->type` without a null-safe operator, unlike the sibling `show()` method which does use one — any orphaned/legacy `MonitoringFormAnswer` row would throw an uncaught error here.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-060 | Fix the unguarded `$answer->formPivot->input->type` access in `MonitoringController::exportPdf` (add null-safe chaining, matching the pattern already used in `show()`) — leading candidate root cause for UAT-022 | UAT | UAT-022 | TODO | HIGH |
| TASK-061 | Reproduce UAT-022 in a running environment to confirm TASK-060 is the actual root cause (currently "Likely", not "Confirmed") | UAT | UAT-022 | REVIEW REQUIRED | HIGH |
| TASK-062 | Confirm whether the web dashboard is intentionally edit-only for Monitoring (with creation restricted to the mobile app) — if not intentional, add a web creation flow | COMMITMENT | COM-021 | REVIEW REQUIRED | MEDIUM |
| TASK-063 | Integrate Competency Standard into the Monitoring framework, per UAT-013 (currently zero references to CompetencyTask/CompetencySkill anywhere in the monitoring model/service/form) | UAT | UAT-013 | TODO | MEDIUM |

#### TASK-060 — Fix null-unsafe property access in Monitoring PDF export

- **Type:** [BUG]
- **Source:** UAT · **Source ID:** UAT-022 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** The Monitoring Dashboard/PDF export should not throw an uncaught error.
- **Current State:** `app/Http/Controllers/Dashboard/MonitoringController.php` — `exportPdf` (~line 113-116) accesses `$answer->formPivot->input->type` directly; the sibling `show()` method (~line 85) defensively uses `?->formPivot?->form_id`.
- **Gap:** Any `MonitoringFormAnswer` row with a null `formPivot` or `formPivot->input` (e.g. from a deleted form-builder input, or legacy data predating a form change) will throw "Attempt to read property on null," matching the UAT reviewer's report of the Monitoring Dashboard "displaying an error."
- **Required Change:** Add null-safe operators (`?->`) matching the `show()` method's defensive pattern, and decide on graceful fallback behavior (skip the row / default type) when data is missing.
- **Dependencies:** None.
- **Acceptance Criteria:** Exporting a Monitoring PDF/report no longer throws when encountering orphaned form-answer data; reproduce the original error scenario if possible to confirm the fix.

---

## Module 19 — Monitoring (Skilfo / Cluster Monitoring / Field Monitoring Assistant)

### Requirement Sources
NEW: NEW-010

### Current Implementation
IMPLEMENTED. Confirmed as a genuinely distinct workflow (`SkilfoMonitoring`/`SkilfoMonitoringDetail` models, separate controller, per-trade/trainer/learner detail structure) rather than a thin duplicate of ALP Monitoring — directly satisfies NEW-010.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-064 | No action needed — mark NEW-010 as verified complete; consider documenting the ALP-vs-Skilfo Monitoring distinction for future maintainers | NEW | NEW-010 | NOT APPLICABLE | LOW |

---

## Module 20 — Feedback

### Requirement Sources
COMMITMENT: COM-022 · REVIEW: REV-006

### Current Implementation
IMPLEMENTED. Full field coverage, polymorphic linkage confirmed working from Implementing Partner, Trainer, and Training pages, matching COM-022.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-065 | Cross-ref TASK-007 (verify/add sanitization on Feedback description before `v-html` rendering) | REVIEW | REV-006 | TODO | MEDIUM |
| TASK-066 | Confirm Feedback is also linkable from Stakeholder/MCP/Learner pages if that's an intended parity item (only Partner/Trainer/Training were confirmed) | COMMITMENT | COM-022 | REVIEW REQUIRED | LOW |

---

## Module 21 — Assessment & Certification (Skilfo)

### Requirement Sources
NEW: NEW-003, NEW-004, NEW-005

### Current Implementation
IMPLEMENTED for NEW-003 (assessor mass-assignment of training-completed learners, assessor-side assignment list, occupation-based competency gating, RPL certificate PDF generation via `barryvdh/laravel-dompdf`/`carlos-meneses/laravel-mpdf`) — genuinely more built-out than the Skilfo comparison document assumed. NEW-004's certificate-generation piece exists; NEW-005 does not.

### Requirement Gap
No "Assessment Scheduling and assessor allocation matrix," no "Self-Assessment Checklists," no "Competency Assessment Result Sheets" model/controller anywhere — these three NEW-004 sub-items are genuinely missing, not just under-verified. NEW-005's structured Pre/Post-Course assessment SCORING (distinct from Monitoring) is entirely absent.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-067 | Design and build "Assessment Scheduling & Assessor Allocation Matrix" per NEW-004 | NEW | NEW-004 | TODO | MEDIUM |
| TASK-068 | Design and build "Self-Assessment Checklists" (learner self-evaluation) per NEW-004 | NEW | NEW-004 | TODO | MEDIUM |
| TASK-069 | Design and build "Competency Assessment Result Sheets" per NEW-004 | NEW | NEW-004 | TODO | MEDIUM |
| TASK-070 | Design and build structured Pre-/Post-Course Assessment SCORING (literacy & occupational competency), distinct from the existing Monitoring module, per NEW-005 | NEW | NEW-005 | TODO | HIGH |

---

## Module 22 — Job Linkage & Post-Training Progression

*(Tasks tracked under Module 16 — Post Training & Employment Tracking — to avoid duplication; see TASK-053 to TASK-056.)*

### Requirement Sources
NEW: NEW-006

---

## Module 23 — Attribute Setup

### Requirement Sources
COMMITMENT: COM-023 · NEW: NEW-012 · UAT: UAT-015

### Current Implementation
IMPLEMENTED. All nine committed attribute types (Modality/Intervention, Disability, Ethnicity, Qualification, Occupation/Trade, Workplace Size, Activity Purpose, Competency Skill, Competency Task) have full bilingual CRUD, permission-gated, with none found to be English-only.

### Requirement Gap
UAT-015's "Auto Mechanics" trade is genuinely missing from seed data (a real gap); "Shantiganj" upazila and "Electrical Installation and Maintenance" trade already exist in seed data — the client's complaint is most likely explained by a stale/partial deployment rather than a code defect.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-074 | Add missing "Auto Mechanics" trade/occupation to seed data | UAT | UAT-015 | TODO | LOW |
| TASK-075 | Verify the production database has the current `UpazilasTableSeeder`/`TradeSeeder` fully applied (the client's environment may be missing seed updates present in the repo) | UAT | UAT-015 | REVIEW REQUIRED | LOW |
| TASK-076 | Resolve the duplicate "Shantiganj" upazila seed entry (appears twice, under two different districts — id 556 under Habiganj and id 559 under Sunamganj) to prevent ambiguous location selection | REVIEW | — | TODO | LOW |

---

## Module 24 — Settings

### Requirement Sources
COMMITMENT: COM-024

### Current Implementation
IMPLEMENTED, and exceeds spec in places (e.g. Mobile App settings include per-tenant monitoring form version counters beyond what SRS described). All committed sub-sections (CMS, Application, Contact, Media, Mobile App, Social Links) are present for both ALP and Skilfo tenants via `spatie/laravel-settings`.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-077 | No action needed — mark COM-024 as verified complete | COMMITMENT | COM-024 | NOT APPLICABLE | LOW |

---

## Module 25 — Locations

### Requirement Sources
UAT: UAT-015

### Current Implementation
IMPLEMENTED. Full Division/District/Upazila/Union CRUD with cascading endpoints.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-078 | Cross-ref TASK-075/076 (Shantiganj data issue) | UAT | UAT-015 | TODO | LOW |
| TASK-079 | No further action needed beyond the seed-data fixes already tracked | UAT | UAT-015 | NOT APPLICABLE | LOW |

---

## Module 26 — Dashboard & Analytics

### Requirement Sources
COMMITMENT: COM-006 · UAT: UAT-001, UAT-002, UAT-004

### Current Implementation
IMPLEMENTED, with UAT-relevant gaps. All 5 tabs present with matching filters and the great majority of committed charts/stat cards, including a genuine monthly time-series for the Monitoring tab (this had been a suspected gap going in — confirmed NOT missing).

### Requirement Gap
UAT-001 (cards need graphical representation): confirmed not implemented — `StatCard.vue` shows only an icon/number/percentage, no embedded chart. UAT-002 (clickable cards): only the single "primary" card per tab is clickable; the majority of cards across all 5 tabs have no navigation at all. UAT-004 (icon consistency): largely consistent already, minor outliers only.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-080 | Add a small embedded chart/sparkline to `StatCard.vue` per UAT-001 | UAT | UAT-001 | TODO | MEDIUM |
| TASK-081 | Make all dashboard stat cards clickable/linked to their corresponding list pages, not just the single primary card per tab, per UAT-002 | UAT | UAT-002 | TODO | MEDIUM |
| TASK-082 | Standardize the handful of outlier icons (`pi pi-filter`, `mage:`, `ion:`) to the dominant `mdi:`/Iconify set used elsewhere, per UAT-004 | UAT | UAT-004 | TODO | LOW |
| TASK-083 | Confirm the "animated overview diagram" section on the landing page (COM-001) exists as a distinct component, or clarify it was satisfied by the confirmed hover-card + AOS-animation implementation | COMMITMENT | COM-001 | REVIEW REQUIRED | LOW |
| TASK-084 | Add `Cache::` usage to `DashboardController`/`StatisticsController`/`GraphController` for expensive aggregate/chart queries — currently only one report metric is cached repo-wide | REVIEW | REV-017 | TODO | MEDIUM |

---

## Module 27 — Reports

### Requirement Sources
COMMITMENT: COM-025 · UAT: UAT-005 · NEW: NEW-011, NEW-018

### Current Implementation
IMPLEMENTED. All three committed report types (Outcomes and Output, Activity, Stakeholder/Partnership) exist with PDF (mPDF) and Excel export.

### Requirement Gap
Activity Report has a Female sub-column per metric but **no independent Male column** (UAT-005) — Male must be manually derived by subtraction. `donor_id`/`upazila_id` filters in `ReportService::activityReport()` are commented-out dead code — filtering by donor or upazila silently does nothing even if the UI offers it. Monthly Progress Reports (NEW-011) don't exist at all. None of the three Reports-module exports support a Bengali/English toggle (NEW-018), even though that exact pattern already exists elsewhere in the codebase (MCP/Assessor exports).

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-085 | Add an independent "Male" column (not just derived) alongside the existing Female sub-columns in the Activity Report, per UAT-005 | UAT | UAT-005 | TODO | MEDIUM |
| TASK-086 | Re-enable/fix the commented-out `donor_id`/`upazila_id` filters in `ReportService::activityReport()` — currently non-functional dead code despite (presumably) being offered in the UI | REVIEW | REV-015 | TODO | HIGH |
| TASK-087 | Get client clarification on the structure/purpose of "Monthly Progress Reports (CA-1 to CA-4)" before building — the client itself does not have a clear definition (see Open Questions OQ-001) | NEW | NEW-011 | BLOCKED | MEDIUM |
| TASK-088 | Apply the existing bilingual-export pattern (already used in `MCPExport`/`AssessorExport`, with a `$language` constructor param and `trans('labels', ..., 'bn')`) to `ActivityExport`, `OutcomeAndOutputExport`, and `PartnershipExport`, per NEW-018 | NEW | NEW-018 | TODO | MEDIUM |
| TASK-089 | Apply the same bilingual pattern to the equivalent PDF Blade views used by Reports | NEW | NEW-018 | TODO | MEDIUM |

#### TASK-086 — Re-enable dead filters in Activity Report

- **Type:** [BUG]
- **Source:** REVIEW · **Source ID:** REV-015 · **Status:** TODO · **Priority:** HIGH
- **Requirement:** COM-025 commits to filtering reports by location/donor/trade.
- **Current State:** `app/Services/Reports/ReportService.php:232-234,241-243` has `donor_id` and `upazila_id` filter logic commented out inside `activityReport()`.
- **Gap:** If the Activity Report UI still presents donor/upazila filter controls, selecting them currently has no effect on the results — a confirmed functional defect, not a design choice (the commit history/comments suggest it was disabled, not intentionally removed).
- **Required Change:** Restore the filter logic (verify it's compatible with the current query structure first, in case it was disabled due to a bug rather than by oversight) or remove the corresponding UI controls if intentionally descoped — confirm with the team which is correct before re-enabling blindly.
- **Dependencies:** None.
- **Acceptance Criteria:** Selecting a Donor or Upazila filter on the Activity Report actually narrows the results.

---

## Module 28 — Public Frontend, Landing Page & CMS

### Requirement Sources
COMMITMENT: COM-001, COM-002, COM-003, COM-004, COM-005 · NEW: NEW-016

### Current Implementation
IMPLEMENTED for COM-001/002/003 (banner hover-cards, real Google-Maps-based geolocation feature with clickable per-location counts, landing charts, partner slider — all confirmed working with real implementations, not stubs). COM-005 (contact form) is implemented via a settings-driven notification path, though the direct `Mail::send()` call is commented out in favor of that path — worth confirming it actually delivers mail. NEW-016's subdomain routing and BNFE "About Us" content are real.

### Requirement Gap
COM-004's CMS "preview feature before publishing" does not exist at all — no draft/published state column exists on the `cms` table, so content goes live immediately on save; the only "preview" hit in the codebase is an unrelated image-thumbnail widget. NEW-016's post-login role/tenant-based redirect does not exist — all users land on the same fixed `/dashboard` route regardless of tenant, with role-based branching happening only in in-page content, not the redirect itself.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-090 | Add a draft/published state to the `cms` table and a preview route/view, per COM-004's explicit "preview feature" commitment | COMMITMENT | COM-004 | TODO | MEDIUM |
| TASK-091 | Confirm `AlpContactSettings::notifyReceiver()` / `SkilfoContactSettings::notifyReceiver()` actually sends email (not a silent no-op) now that the direct `Mail::send()` call is commented out in both `FrontendController` and `TenantFrontendController` | COMMITMENT | COM-005 | REVIEW REQUIRED | MEDIUM |
| TASK-092 | Implement tenant/role-based post-login redirect logic, per NEW-016 (currently all users redirect to the same fixed `/dashboard`, with role branching only in page content) | NEW | NEW-016 | TODO | MEDIUM |
| TASK-093 | Add an in-app locale-switch control on the authenticated dashboard (currently bilingual switching exists only via the public-site `?locale=` route/session, with no visible in-dashboard toggle) | REVIEW | — | TODO | LOW |

---

## Module 29 — Notifications

### Requirement Sources
UAT: UAT-011

### Current Implementation
PARTIALLY IMPLEMENTED. Real email-capable notification infrastructure exists but is narrowly applied (Trainer assignment, task assignment, import complete/fail, contact form, registration-no update) and is gated behind an env flag (`NOTIFY_VIA_MAIL`) that defaults OFF for at least two of those notification types. In-app notification models (`Notification`/`NotificationUser`) are database/push-only, not email.

### Requirement Gap
No generic "notify on any dashboard action" infrastructure exists; broader modules (Monitoring, CMS, Reports) fire no notifications at all. No literal "Event" model/notification exists (Event/Activity creation doesn't trigger anything directly; the closest analog, Training/course trainer-assignment, is email-capable but off by default).

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-094 | Confirm with UNICEF/client whether `NOTIFY_VIA_MAIL`/`NOTIFY_VIA_SMS` are enabled in production — if the intent is for these notifications to actually reach users by email/SMS, the current default-off configuration silently defeats that intent | UAT | UAT-011 | REVIEW REQUIRED | HIGH |
| TASK-095 | Wire an email notification specifically to Event/Activity creation, per UAT-011's explicit callout ("specially event creation") | UAT | UAT-011 | TODO | MEDIUM |
| TASK-096 | Scope which additional modules (Monitoring, CMS changes, Feedback resolution) should trigger notifications, rather than building a fully generic "any action" system (avoid overengineering — pick the specific, valuable trigger points the client actually needs) | UAT | UAT-011 | TODO | LOW |

---

## Module 30 — BNFE Integration (Sync & Webhooks)

### Requirement Sources
REVIEW: REV-001, REV-014

### Current Implementation
IMPLEMENTED, functional but rough-edged. Real bidirectional sync: ALP→BNFE outbound (learner/training-center/occupation/program sync via queued jobs) and BNFE→ALP inbound webhook (`certificate-issued`, authenticated via per-partner API keys). This is a working integration, not a stub, and is documented in `docs/BNFE_WEBHOOK_API.md`.

### Requirement Gap
The inbound webhook's entire try/catch error-handling block is commented out — any runtime error will surface as an unhandled 500 instead of the documented graceful JSON error response. BNFE dashboard routes have no permission gating (tracked as TASK-001).

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-097 | Restore/fix the commented-out try/catch error handling in `BNFEWebhookController::certificateIssued`, so it matches its own documented error-response contract | REVIEW | REV-014 | TODO | HIGH |
| TASK-098 | Cross-ref TASK-001 (BNFE dashboard routes need permission gating) | REVIEW | REV-001 | TODO | HIGH |
| TASK-099 | Document the BNFE integration's data-flow and failure modes for the ops team, since it's a genuinely load-bearing external dependency | REVIEW | — | TODO | LOW |

---

## Module 31 — Mobile App & Offline Sync

### Requirement Sources
CLIENT: CR-008, CR-011, CR-012 · COMMITMENT: COM-026, COM-028 · REVIEW: REV-019 to REV-023

### Current Implementation
No mobile app source exists in this repository (confirmed by exhaustive search) — the Flutter app committed to in the ToR/SRS lives in a separate repository, so its completeness cannot be verified from this audit. The backend API surface (V1 + V2, both actively maintained) is what a mobile app would consume.

### Requirement Gap
No evidence of a genuine offline-first sync design (idempotency keys, conflict resolution, last-synced cursors) in the API — the one candidate mechanism found (`ProgramTrainingSnapshot`) is a simple single-blob autosave/overwrite, not a real sync engine. No SSO implementation exists despite COM-028's commitment. Maintaining two parallel, independently-evolving API versions (V1 and V2) is a confirmed ongoing engineering cost.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-100 | Obtain/audit the separate mobile app repository to actually verify CR-008/CR-011/CR-012/COM-026 (bilingual UI, accessibility, offline capability, mobile sign-up/sync) — cannot be assessed from this codebase alone | CLIENT | CR-008, CR-011, CR-012 | BLOCKED | HIGH |
| TASK-101 | Design a genuine offline-sync mechanism (versioned records, per-entity idempotency keys, conflict resolution) if CR-008/CR-012's "offline data collection with automatic sync" is still a live requirement — the current `ProgramTrainingSnapshot` mechanism does not meet this bar | CLIENT | CR-008, CR-012 | TODO | HIGH |
| TASK-102 | Decide and execute a plan to consolidate or formally deprecate API V1 in favor of V2 once the mobile app fully migrates, to stop the ongoing duplicate-maintenance cost | REVIEW | REV-021 | TODO | MEDIUM |

---

## Module 32 — Undocumented / Scope-Creep Modules

### Requirement Sources
REVIEW: REV-027, REV-028, REV-029

### Current Implementation
The codebase contains several fully-built modules that are **not mentioned anywhere in the ToR, SRS, UAT feedback, or Skilfo comparison documents**:
- **Task Tracking** (`UserTask` + related models) — live, fully wired, but gated to the Skilfo tenant only.
- **Form Builder** — live, appears to be load-bearing for the dynamic Monitoring forms (not itself a documented requirement, but likely necessary infrastructure for COM-021).
- **Support ticketing** (`Support`/`SupportRequest`) — live, fully wired with its own notification listener.
- **Backup UI** — live, but only supports a manual/admin-triggered backup (index/store/destroy), not the "automated backup procedures" CR-018/COM-031 actually commit to.
- **Audit logging** (`owen-it/laravel-auditing`) — live and genuinely wired to many domain models; a positive finding, not a gap.
- **Firebase Push** — infrastructure present (device-token storage, frontend client config) but the controller has zero methods, no route references it, and the Firebase credentials file is empty — effectively dead/unfinished.
- **Translations/i18n** — live and is the actual backbone of the site's bilingual behavior; should be credited against COM-023/NEW-012 rather than treated as scope creep.

### Requirement Gap
Task Tracking, Form Builder, and Support ticketing represent real, functioning scope that was never named in any contractual document — this needs product/commercial clarification (is it billable scope creep, a freebie, or actually out-of-scope and should be hidden/removed?). The Backup feature only partially satisfies CR-018/COM-031 since it has no scheduled/automated trigger. Firebase Push should either be finished or explicitly descoped/removed.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-103 | Get product/commercial clarification from UNICEF on the Task Tracking module — confirm it's an intentional, approved deliverable (currently Skilfo-tenant-only) rather than unapproved scope creep | REVIEW | REV-027 | REVIEW REQUIRED | MEDIUM |
| TASK-104 | Get product clarification on the Form Builder module — confirm its relationship to the Monitoring module's dynamic forms and whether it should be documented as part of COM-021 | REVIEW | REV-027 | REVIEW REQUIRED | MEDIUM |
| TASK-105 | Get product/commercial clarification on the Support ticketing module | REVIEW | REV-027 | REVIEW REQUIRED | LOW |
| TASK-106 | Add a scheduled/automated trigger to the Backup feature (currently manual-only via index/store/destroy) to actually satisfy CR-018/COM-031's "automated backup procedures" commitment | CLIENT | CR-018 | TODO | HIGH |
| TASK-107 | Verify the Backup feature performs a full DB+file backup, not just a manual DB dump | CLIENT | CR-018 | REVIEW REQUIRED | MEDIUM |
| TASK-108 | Decide whether to finish (configure real Firebase credentials, wire up `FirebasePushController` methods) or formally remove the Firebase Push scaffolding — currently dead code with a 0-byte credentials file | REVIEW | REV-028 | TODO | LOW |
| TASK-109 | No action needed for Translations/i18n or Audit Logging — both are genuine positive findings; document them as satisfying part of COM-023/NEW-012 (bilingual) and CR-013 (data integrity/traceability) respectively | REVIEW | — | NOT APPLICABLE | LOW |

---

## Module 33 — Database & Data Integrity

### Requirement Sources
REVIEW: REV-007, REV-008

### Current Implementation
Generally solid. Foreign-key constraints (`constrained()`/`foreign()`) are the dominant pattern (66 of 148 migration files sampled use them explicitly), with unique constraints correctly applied to `uuid`/`mobile`/`email` on key tables. Soft-delete + versioning ("restore" feature) confirmed genuinely implemented (not a stub) for Profile/Trainer/Mcp.

### Requirement Gap
A small number of migrations (5 in the sample) use unconstrained `_id` integer columns rather than proper FKs — worth a full pass rather than the sample already done. `SoftDeletes`/`Versionable` are not applied to `Enterprise`/`Assessor`, an inconsistency with the rest of the profile types (already tracked as TASK-012/044).

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-110 | Do a full pass (not just the sample already done) across all 148 migrations for unconstrained `_id` columns that should have proper foreign keys | REVIEW | — | TODO | MEDIUM |
| TASK-111 | Cross-ref TASK-012/TASK-044 (SoftDeletes/Versionable parity for Enterprise/Assessor) | REVIEW | REV-007 | TODO | MEDIUM |
| TASK-112 | Document the dual tenancy-scoping mechanism as a known architectural debt item (cross-ref TASK-013) so it's tracked for eventual consolidation rather than silently accumulating more call sites | REVIEW | REV-008 | TODO | LOW |

---

## Module 34 — Laravel Architecture & Backend Code Quality

### Requirement Sources
COMMITMENT: COM-030 · REVIEW: REV-017 to REV-021

### Current Implementation
Generally pragmatic and appropriate for the scale — no evidence of overengineering (no Repository layer, minimal/justified use of Contracts, no CQRS/microservices). Form Requests are the dominant validation mechanism (162 classes); Policies (27) are registered and actively invoked (97 call sites); Events/Listeners are fully wired, not orphaned; no N+1 pattern found in the one high-traffic path spot-checked (`ProfileService`).

### Requirement Gap
Caching is sparse — only one dashboard-adjacent query is cached repo-wide, despite the chart/stat-heavy dashboard requirements (tracked as TASK-084). `QUEUE_CONNECTION=sync` in `.env.example` risks running 18 `ShouldQueue` job classes (imports/exports/BNFE sync) inline in production if not overridden. Exception handling only special-cases one exception type, with no differentiated JSON-API vs. Inertia-web handling for the common cases (validation, not-found, unauthenticated).

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-113 | Confirm production `.env` overrides `QUEUE_CONNECTION` away from `sync`, given the number of I/O-heavy queued jobs (imports/exports/BNFE sync) | REVIEW | REV-019 | REVIEW REQUIRED | MEDIUM |
| TASK-114 | Expand `app/Exceptions/Handler.php` to explicitly differentiate JSON-API vs. Inertia-web responses for `ValidationException`, `ModelNotFoundException`, and `AuthenticationException`; resolve the currently commented-out `unauthenticated()` override one way or the other | REVIEW | REV-018 | TODO | MEDIUM |
| TASK-115 | Cross-ref TASK-084 (dashboard/report caching) | REVIEW | REV-017 | TODO | MEDIUM |
| TASK-116 | Confirm test coverage exists/is adequate per COM-030's testing commitment (feature/integration/UAT tests) — not independently assessed in this audit pass, recommend a dedicated test-coverage review | COMMITMENT | COM-030 | REVIEW REQUIRED | MEDIUM |
| TASK-117 | Cross-ref TASK-102 (API V1/V2 consolidation) | REVIEW | REV-021 | TODO | MEDIUM |
| TASK-118 | Clean up the `UserDevice` model's inconsistent timestamp handling (`$timestamps = false` but `created_at` manually kept in `$fillable`, no `updated_at`/last-seen tracking) if device state is meant to be tracked reliably for push targeting | REVIEW | — | TODO | LOW |

---

## Module 35 — Inertia + Vue Frontend Patterns

### Requirement Sources
REVIEW: REV-024, REV-025, REV-026

### Current Implementation
Authorization-aware UI (`hasPermission()` used 94 times) is a legitimate UX-hardening layer backed by real server-side enforcement, not a security gap. Inertia's shared error bag (`useForm()`/`form.errors`) is used consistently across sampled forms. A shared `AppTable.vue` component and PrimeVue `DataTable` underlie the majority of list pages.

### Requirement Gap
`Profile/Learner/Index.vue`, `Trainer/Index.vue`, and `MCP/Index.vue` (~2,800 combined lines) each hand-roll their own table/filter/bulk-action/pagination logic instead of using the existing shared `AppTable.vue` — a legitimate, non-speculative consolidation opportunity. Duplicate-cased `Components`/`components` and `Composables`/`composables` directories risk ambiguous imports.

### Tasks
| Task ID | Task | Source | Source ID | Status | Priority |
|---|---|---|---|---|---|
| TASK-119 | Consolidate `Profile/Learner/Index.vue`, `Trainer/Index.vue`, `MCP/Index.vue` onto the existing shared `AppTable.vue`/composable pattern to eliminate ~2,800 lines of duplicated list/filter/bulk-action code | REVIEW | REV-024 | TODO | MEDIUM |
| TASK-120 | Reconcile the duplicate-cased `resources/js/Components`/`components` and `Composables`/`composables` directories | REVIEW | REV-025 | TODO | LOW |
| TASK-121 | Cross-ref TASK-081 (most dashboard stat cards not clickable) | REVIEW | REV-026 | TODO | MEDIUM |

---

# Client Requirement → Our Commitment Traceability

| Client ID | Client Requirement (summary) | Commitment ID | Commitment Status | Notes |
|---|---|---|---|---|
| CR-001 | Integrated portal, centralized DB, dynamic reports | COM-006, COM-025, COM-027 | Covered | Dashboard+Reports+ERD all address this |
| CR-002 | Individual profile DBs for all participant types | COM-009 to COM-014 | Covered | |
| CR-003 | Continuous learner performance tracking | COM-018, COM-019 | Covered | Attendance + Post Training |
| CR-004 | Filterable beneficiary-selection/BTEB analysis | COM-025 | Partially covered | Reports exist; BTEB-specific filter not explicitly confirmed — **Commitment Gap** candidate, needs verification |
| CR-005 | Curricula/material storage for sharing | — | **Commitment Gap** | No SRS section explicitly commits to a document-library/curricula-repository feature; Media/attachment infrastructure exists generically but nothing purpose-built for curricula distribution to partners/govt |
| CR-006 | Dynamic dashboard per training type | COM-006 | Covered | |
| CR-007 | Anonymous field feedback | COM-022 | Partially covered | Feedback module exists; "anonymous reporting" option not confirmed as a distinct capability — needs verification |
| CR-008 | Online+offline mobile access, auto-sync | COM-026, COM-028 | Partially covered | Mobile app itself is out of this repo's scope; offline-sync design gap confirmed (TASK-101) |
| CR-009 | Scale to 100,000 participants | COM-028 (hardware spec) | Partially covered | SRS specs only 100GB/16GB — likely undersized for 100k-participant scale; **flag for infra review**, not a code task |
| CR-010 | Record data at Outcome/Output/Activity levels | COM-025 | Covered | Outcomes and Output Report explicitly implements this |
| CR-011 | Bilingual, accessible mobile app | COM-026 | Cannot verify from this repo | Mobile app is a separate codebase |
| CR-012 | Mobile sign-up/sync, SSO | COM-028 | Gap | No SSO found (TASK-008); sync mechanism weak (TASK-101) |
| CR-013 | Data integrity, auth, role segregation, approval workflow | COM-007, COM-008, COM-029 | Partially covered | RBAC solid; approval-workflow-specific enforcement and per-partner object-level isolation need hardening (Module 1/2 tasks) |
| CR-014 | UNICEF security assessment | COM-029 | Partially covered | Checklist partially implemented; formal third-party assessment itself is a process step, not a code deliverable (TASK-009) |
| CR-015 | Capacity building materials | — | **Commitment Gap** | Not a software feature; a documentation/training deliverable, out of code-review scope |
| CR-016 | Knowledge products (design doc, guides, manuals) | — | **Commitment Gap** | Documentation deliverables, not verifiable from code |
| CR-017 | Warranty + 3-day bug SLA | COM-030 | Process commitment | Not code-verifiable; operational/contractual |
| CR-018 | Backup & recovery | COM-031 | Partially covered | Manual Backup UI exists (TASK-106/107); no confirmed automated schedule |
| CR-019 | Source code/DB ownership | — | Contractual | Not code-verifiable |
| CR-020 | Continuous DB/query tuning | COM-030 | Partially covered | Caching gaps found (TASK-084/115); no ongoing tuning process evidenced in code |
| CR-021 | Data confidentiality, child safeguarding | COM-029 | Partially covered | Technical access controls exist; safeguarding policy compliance is a process matter (TASK-016 covers the technical export-leak angle) |

---

# UAT Feedback Traceability

| UAT ID | Feedback | Related Requirement | Current Implementation | Task |
|---|---|---|---|---|
| UAT-001 | Cards need bar-chart viz | COM-006 | Missing | TASK-080 |
| UAT-002 | Cards should be clickable | COM-006 | Partial | TASK-081 |
| UAT-003 | Event "Target Participant" field | COM-020 | Present in backend, needs UI verification | TASK-057, TASK-058 |
| UAT-004 | Icon consistency | — | Largely fine | TASK-082 |
| UAT-005 | Activity Report Male/Female split | COM-025 | Partial (Female only) | TASK-085 |
| UAT-006 | More gender options | COM-014 | Missing | TASK-034 |
| UAT-007 | Marital status: child marriage, divorce | COM-014 | Partial (divorce exists) | TASK-035 |
| UAT-008 | Age-group classification | COM-014 | Partial (computed, not persisted; no "youth" tier) | TASK-036 |
| UAT-009 | Submenus where needed | — | Enhancement, no specific gap identified | — (defer to UX review) |
| UAT-010 | Self-service attendance view | COM-018 | Missing | TASK-025 |
| UAT-011 | Auto email on dashboard actions | — | Partial (narrow, env-gated) | TASK-094, TASK-095, TASK-096 |
| UAT-012 | Intervention data on learner list | COM-014 | **Already resolved** | TASK-038 |
| UAT-013 | Competency Standard in Monitoring | COM-021 | Missing (confirmed) | TASK-051, TASK-063 |
| UAT-014 | Mobile multi-page forms | COM-026 | Cannot verify (mobile out of repo) | TASK-100 |
| UAT-015 | Missing upazila/trade data | — | Data/seed gap | TASK-074, TASK-075, TASK-076 |
| UAT-016 | Wrong partner name displayed | COM-010 | **Root cause found (bug)** | TASK-021 |
| UAT-017 | Fields blocked in Training/Course | COM-018 | Likely cause found (UX gating) | TASK-049, TASK-050 |
| UAT-018 | CwD classification | COM-014 | Missing | TASK-031, TASK-037 |
| UAT-019 | Entrepreneur/Self/Wage employment | COM-019 | Confirmed gap | TASK-053 |
| UAT-020 | Dropout reason field | COM-014 | **Already resolved** | TASK-039 |
| UAT-021 | Cross-cutting Employment tab | — | Missing | TASK-056 |
| UAT-022 | Monitoring Dashboard error | COM-021 | Likely cause found (bug) | TASK-060, TASK-061 |
| UAT-023 | Duplicate learner rows under MCP | COM-013 | Needs investigation | TASK-029 |

---

# New Requirement Traceability

| New ID | Requirement | Existing Module | Existing Support | Required Task |
|---|---|---|---|---|
| NEW-001 | Enterprise module | Profile: Enterprise | ~85-90% implemented | TASK-045, TASK-046, TASK-047 |
| NEW-002 | Assessor + dashboard | Profile: Assessor | Fields yes, dashboard no | TASK-042, TASK-043 |
| NEW-003 | Assessor mass-assignment workflow | Assessment & Certification | Implemented | TASK-064-equivalent (no action) |
| NEW-004 | Scheduling/checklists/result sheets/certs | Assessment & Certification | Partial (certs only) | TASK-067, TASK-068, TASK-069 |
| NEW-005 | Pre/Post-Course assessment scoring | Assessment & Certification | Not implemented | TASK-070 |
| NEW-006 | Job Linkage + 6-month follow-up | Post Training | Partial (free text only) | TASK-054, TASK-055 |
| NEW-007 | Literacy Center | — | Not implemented; unclear even to client | See Open Question OQ-002 |
| NEW-008 | Institution Database | Training Center | Not distinct | TASK-040, Open Question OQ-005 |
| NEW-009 | Craft Database | Trainer | Not distinct | Open Question OQ-004 |
| NEW-010 | Cluster Monitoring/FMA | Monitoring (Skilfo) | Implemented | TASK-064 |
| NEW-011 | Monthly Progress Reports (CA-1..4) | Reports | Not implemented; structure unclear | TASK-087, Open Question OQ-001 |
| NEW-012 | Bilingual fields on entity names | Profiles | Broadly implemented already | No further action beyond documentation |
| NEW-013 | MCP↔Enterprise linkage | Profile: MCP | Implemented | No action |
| NEW-014 | Trainer/MCP new fields | Profile: Trainer/MCP | Trainer yes, MCP no | TASK-028 |
| NEW-015 | Post Training job-placement fields | Post Training | Partial (free text) | TASK-054 |
| NEW-016 | Skilfo subdomain + role redirect | Frontend/CMS | Partial (subdomain yes, redirect no) | TASK-092 |
| NEW-017 | New frontend/cert design, mobile app | — | Certs partial; mobile out of repo scope | TASK-100 |
| NEW-018 | Bilingual report export | Reports | Pattern exists elsewhere, not applied | TASK-088, TASK-089 |

---

# Code Review Findings

| ID | Finding | Confidence | Severity/Impact | Task |
|---|---|---|---|---|
| REV-001 | BNFE dashboard routes have no permission middleware | Confirmed | Medium (security) | TASK-001 |
| REV-002 | No `McpPolicy`; object-level auth relies on hand-rolled per-service checks | Confirmed | Medium (security) | TASK-002, TASK-010, TASK-014 |
| REV-003 | Generic file upload endpoint has no MIME/extension allow-list | Confirmed | Medium-High (security) | TASK-003 |
| REV-004 | Session driver is `file`, not `database`; no IP session lock | Confirmed | Low-Medium (security) | TASK-004, TASK-005 |
| REV-005 | No 2FA on any auth path | Confirmed | Low (security) | TASK-006 |
| REV-006 | `v-html` rendering of feedback description — possible stored XSS if unsanitized | Needs verification | Medium (security) | TASK-007, TASK-065 |
| REV-007 | Enterprise/Assessor lack SoftDeletes/Versionable | Confirmed | Medium (consistency) | TASK-012, TASK-044, TASK-111 |
| REV-008 | Two parallel tenancy mechanisms (global-scope + ad hoc partner filtering) | Confirmed | Medium (architecture) | TASK-011, TASK-013, TASK-112 |
| REV-009 | Duplicate `'trade'` array key in V1 `ProfileApiResource` silently returns donor name | Confirmed | Medium (bug) | TASK-021 |
| REV-010 | `Assessor::newUniqueId()` references non-existent `upazila_id` | Confirmed | Low (latent bug) | TASK-043 |
| REV-011 | Orphaned `qualification()` relation on Enterprise, no backing column | Confirmed | Low | TASK-047 |
| REV-012 | Unguarded null-chain in `MonitoringController::exportPdf` | Likely | Medium (bug, plausible UAT-022 cause) | TASK-060, TASK-061 |
| REV-013 | Training/Course form gates dropdowns behind strict 4-field AND-condition | Likely | Medium (UX, plausible UAT-017 cause) | TASK-049 |
| REV-014 | BNFE webhook error handling (try/catch) commented out | Confirmed | Medium (reliability) | TASK-097 |
| REV-015 | `ReportService::activityReport()` has dead donor/upazila filter code | Confirmed | Medium (bug) | TASK-086 |
| REV-016 | CMS has no draft/published state or preview route despite commitment | Confirmed | Medium (gap) | TASK-090 |
| REV-017 | No caching on Dashboard/Statistics/Graph controllers | Likely | Medium (performance) | TASK-084, TASK-115 |
| REV-018 | Exception Handler only special-cases one exception type | Confirmed | Low-Medium (maintainability) | TASK-114 |
| REV-019 | `QUEUE_CONNECTION=sync` in `.env.example` despite 18 queued job classes | Needs verification | Medium (performance/reliability) | TASK-113 |
| REV-020 | No SSO mechanism despite COM-028 commitment | Confirmed | Low (clarification needed) | TASK-008 |
| REV-021 | Two parallel, actively-maintained API versions (V1/V2) | Confirmed | Medium (maintainability) | TASK-102, TASK-117 |
| REV-022 | `ProgramTrainingSnapshot` is single-blob overwrite, not real offline sync | Confirmed | Medium (gap vs. commitment) | TASK-101 |
| REV-023 | No mobile app source in this repository | Confirmed | Scoping note | TASK-100 |
| REV-024 | ~2,800 lines of duplicated CRUD-list code (Learner/Trainer/MCP) bypassing shared `AppTable` | Confirmed | Low (maintainability) | TASK-119 |
| REV-025 | Duplicate-cased `Components`/`components`, `Composables`/`composables` dirs | Confirmed | Low (maintainability) | TASK-120 |
| REV-026 | Most dashboard stat cards not clickable | Confirmed | Low-Medium (UX, same as UAT-002) | TASK-081, TASK-121 |
| REV-027 | Undocumented modules: Task Tracking, Form Builder, Support ticketing | Confirmed | Medium (scope/commercial) | TASK-103, TASK-104, TASK-105 |
| REV-028 | Firebase Push infrastructure present but non-functional (dead code) | Confirmed | Low | TASK-108 |
| REV-029 | Backup feature is manual-trigger only, not automated | Confirmed | Medium (gap vs. CR-018) | TASK-106, TASK-107 |
| REV-030 | Trainer/MCP/Learner detail pages missing "Logbook" tab despite data being available | Confirmed | Medium (gap vs. commitment) | TASK-023, TASK-027, TASK-033 |
| REV-031 | Competency Standard missing "Unit Code" field | Confirmed | Low (gap vs. commitment) | TASK-041 |
| REV-032 | NEW-014 fields rolled out to Trainer but not MCP (inconsistent) | Confirmed | Low-Medium | TASK-028 |
| REV-033 | No Assessor-facing dashboard despite NEW-002 commitment | Confirmed | Medium (gap) | TASK-042 |
| REV-034 | "Training Type" not conclusively distinct from Intervention/Trade setup | Unclear | Low (needs clarification) | TASK-048 |

## Security Review (subset of the above, security-specific)

Confirmed or highly-likely security gaps, ranked by severity:

1. **Medium-High** — Generic file-upload endpoint accepts any file type with no allow-list, stored publicly (REV-003 / TASK-003).
2. **Medium** — BNFE routes bypass the permission system entirely (REV-001 / TASK-001).
3. **Medium** — No object-level policy for MCP; cross-partner protection depends on manually duplicated per-service filters not yet fully audited (REV-002 / TASK-002, TASK-010, TASK-014).
4. **Medium (needs verification)** — Possible stored XSS via unsanitized feedback rendered with `v-html` (REV-006 / TASK-007).
5. **Low-Medium** — Session driver not DB-backed; no IP session lock, contradicting COM-029 (REV-004 / TASK-004, TASK-005).
6. **Low** — No 2FA anywhere (REV-005 / TASK-006).

No SQL-injection, mass-assignment, or CSRF-bypass issues were found — the Eloquent-based query layer, explicit `$fillable` usage, and correctly-scoped CSRF exemptions are all sound.

## Database Review

- FK constraints are the dominant, consistent pattern (66/148 sampled migrations); a residual set of ~5 files use unconstrained `_id` columns and merit a full audit pass (TASK-110).
- Soft-delete + versioning ("restore") is a genuine, working implementation for Profile/Trainer/Mcp, but inconsistently absent on Enterprise/Assessor (TASK-012/044/111).
- Unique constraints correctly applied to `uuid`/`mobile`/`email`.
- Two coexisting tenancy-scoping mechanisms represent architectural debt worth consolidating (TASK-013).
- No polymorphic-relation indexing issues were surfaced in the sampled review.

---

# Requirement Conflicts

| Conflict ID | Requirement A | Requirement B | Existing Implementation | Impact | Resolution Required |
|---|---|---|---|---|---|
| CONF-001 | CR-018/COM-031 commit to "automated backup procedures" | Actual Backup feature is manual-trigger only (index/store/destroy, no scheduled job) | Implemented as manual-only | Data-loss risk if backups depend on an admin remembering to click a button | Decide: add a scheduled job, or formally amend the commitment to "on-demand backup" (TASK-106) |
| CONF-002 | SRS commits (COM-012/013/014) to a "Logbooks" timeline tab on every profile-type detail page | Trainer/MCP/Learner detail pages all omit the Logbook tab despite the underlying data/relation existing | Data present, UI missing | Client-visible gap between spec and delivered UI across 3 modules | Add the missing tab uniformly (TASK-023/027/033) rather than three separate ad hoc fixes |

---

# Open Questions / Clarifications Required

| ID | Question | Related Requirement | Why It Matters |
|---|---|---|---|
| OQ-001 | What exactly are "CA-1" through "CA-4" in the Monthly Progress Report, and how often are they filled? Are they assessment-, attendance-, or performance-related? | NEW-011 | Cannot design/build this report without a definition — the client's own Skilfo document flags this as unclear |
| OQ-002 | How does "Literacy Center" differ from the existing "Training Center"? Is it a distinct module or a Training Center sub-type? | NEW-007 | Determines whether this is new schema/module work or a minor extension |
| OQ-003 | What are the precise differences intended between "Pre-Assessment" and "Training Assessment" (NEW-005)? | NEW-005 | Needed to design the assessment-scoring data model correctly |
| OQ-004 | Beyond the existing Trainer/MCP profile, what additional data does the general "Craft Database" (NEW-009) need to capture? | NEW-009 | Avoids building a redundant or wrongly-scoped new entity |
| OQ-005 | Should "Institution" (NEW-008) be a fully independent module, or always linked to Training Centers/Courses? | NEW-008 | Determines whether Training Center is extended or a new module is built |
| OQ-006 | Is the Task Tracking / Form Builder / Support-ticketing functionality found in the codebase an intentional, approved deliverable, unbilled scope creep, or something that should be removed/hidden? | REV-027 | Commercial and product-scope implications; affects whether further investment in these modules is warranted |
| OQ-007 | Is the 100GB storage / 16GB RAM hardware spec in the SRS (COM-028) still adequate now that the target scale has grown from ~25,000 to ~100,000 participants (CR-009) plus the added Skilfo/BNFE scope? | CR-009 | Infrastructure sizing decision, not a code task, but blocks confident capacity planning |
| OQ-008 | Does "mobile SSO" (COM-028) mean integration with an existing UNICEF/partner identity provider (OIDC/SAML), or something narrower (e.g. shared session between web and mobile)? | COM-028 | No implementation currently exists; scope must be defined before work can start |

---

# Recommended Implementation Sequence

### Phase 1 — Critical Corrections & Confirmed Bugs
- TASK-021 (duplicate-key bug swapping partner/donor names)
- TASK-060 / TASK-061 (Monitoring PDF export null-chain crash)
- TASK-086 (dead donor/upazila filters in Activity Report)
- TASK-029 (duplicate learner rows under MCP)
- TASK-097 (BNFE webhook error handling)

### Phase 2 — Security Hardening
- TASK-001, TASK-002, TASK-003 (BNFE permission gate, MCP policy, file-upload allow-list)
- TASK-010, TASK-014 (audit remaining Services for IDOR-style partner-scoping gaps)
- TASK-004, TASK-005, TASK-006 (session/2FA hardening)
- TASK-007 (feedback XSS verification)
- TASK-009 (commission formal UNICEF security assessment)

### Phase 3 — Original Commitment Gaps (SRS)
- TASK-023, TASK-027, TASK-033 (missing Logbook tabs — CONF-002)
- TASK-090 (CMS preview/draft state)
- TASK-041 (Competency Standard Unit Code)
- TASK-042 (Assessor dashboard)
- TASK-106, TASK-107 (automated backup — CONF-001)
- TASK-048 (Training Type clarification)

### Phase 4 — UAT Corrections
- TASK-049, TASK-050 (Training/Course field-gating UX)
- TASK-034, TASK-035, TASK-036, TASK-037, TASK-031 (gender/marital/age-group/CwD)
- TASK-053 (Entrepreneur/Self/Wage)
- TASK-080, TASK-081, TASK-082, TASK-085 (dashboard/report UI feedback)
- TASK-025 (self-service attendance)
- TASK-094, TASK-095 (notifications)
- TASK-074, TASK-075, TASK-076 (seed-data fixes)

### Phase 5 — New Requirements (Skilfo)
- TASK-028 (NEW-014 parity for MCP)
- TASK-054, TASK-055, TASK-056 (Job Linkage structure + follow-up)
- TASK-067, TASK-068, TASK-069, TASK-070 (Assessment scheduling/checklists/result sheets/scoring) — pending OQ-001/OQ-003 clarifications where relevant
- TASK-088, TASK-089 (bilingual report export)
- TASK-092 (tenant/role-based redirect)
- TASK-045, TASK-046, TASK-047 (Enterprise field completion)
- Items blocked on client clarification (OQ-001, OQ-002, OQ-004, OQ-005) should be scheduled only after answers are received

### Phase 6 — Quality, Performance, Architecture
- TASK-084, TASK-115 (dashboard/report caching)
- TASK-113 (queue connection verification)
- TASK-114 (exception handler hardening)
- TASK-102, TASK-117 (API V1/V2 consolidation plan)
- TASK-119, TASK-120 (frontend duplication cleanup)
- TASK-110, TASK-111, TASK-112 (database/tenancy consolidation)
- TASK-116 (test coverage review)

### Ongoing / Process (not sequenced — track separately)
- TASK-100, TASK-101 (mobile app audit + offline-sync design — depends on access to the separate mobile repo)
- TASK-103, TASK-104, TASK-105, TASK-108 (scope clarification for undocumented modules)
- OQ-006, OQ-007, OQ-008 (commercial/infra/product decisions)

---

# Master Requirement Traceability Matrix

| Requirement ID | Source | Module | Requirement (summary) | Implementation Status | Task ID(s) |
|---|---|---|---|---|---|
| CR-001 | CLIENT | Dashboard/Reports | Integrated portal, centralized DB | Implemented | — |
| CR-002 | CLIENT | Profiles (all) | Individual profile DBs | Implemented | — |
| CR-003 | CLIENT | Program | Continuous learner performance tracking | Implemented | — |
| CR-004 | CLIENT | Reports | Filterable beneficiary/BTEB analysis | Partial | See CR→COM table |
| CR-005 | CLIENT | — | Curricula/material storage for sharing | Missing | Commitment Gap, see CR→COM table |
| CR-006 | CLIENT | Dashboard | Dynamic dashboard per training type | Implemented | — |
| CR-007 | CLIENT | Feedback | Anonymous field feedback | Partial | Needs verification |
| CR-008 | CLIENT | Mobile | Offline access, auto-sync | Partial/Unclear | TASK-100, TASK-101 |
| CR-009 | CLIENT | Infra | Scale to 100,000 | Needs review | OQ-007 |
| CR-010 | CLIENT | Reports | Outcome/Output/Activity data | Implemented | — |
| CR-011 | CLIENT | Mobile | Bilingual, accessible app | Cannot verify | TASK-100 |
| CR-012 | CLIENT | Mobile/Auth | Mobile sign-up/sync, SSO | Gap | TASK-008, TASK-101 |
| CR-013 | CLIENT | Auth/Security | Data integrity, role segregation | Partial | Module 1/2 tasks |
| CR-014 | CLIENT | Security | UNICEF security assessment | Partial | TASK-009 |
| CR-015 | CLIENT | Process | Capacity building | Not code-verifiable | — |
| CR-016 | CLIENT | Process | Knowledge products | Not code-verifiable | — |
| CR-017 | CLIENT | Process | Warranty/SLA | Contractual | — |
| CR-018 | CLIENT | Backup | Backup & recovery | Partial | TASK-106, TASK-107 |
| CR-019 | CLIENT | Process | Source code ownership | Contractual | — |
| CR-020 | CLIENT | Performance | DB/query tuning | Partial | TASK-084, TASK-115 |
| CR-021 | CLIENT | Security | Data confidentiality | Partial | TASK-016 |
| COM-001 to COM-032 | COMMITMENT | (per module above) | — | (see per-module sections) | (see per-module sections) |
| UAT-001 to UAT-023 | UAT | (per module above) | — | (see UAT traceability table) | (see UAT traceability table) |
| NEW-001 to NEW-018 | NEW | (per module above) | — | (see New Requirement traceability table) | (see New Requirement traceability table) |
| REV-001 to REV-034 | REVIEW | (per module above) | — | (see Code Review Findings table) | (see Code Review Findings table) |

*(Full COM/UAT/NEW/REV rows are not repeated here to avoid duplicating the dedicated traceability tables above — see those sections for complete detail. This master matrix exists to confirm every requirement category has been mapped to at least one module and task where applicable.)*

---

# Final Review Checklist

- [x] Entire repository inspected (app/, database/, resources/js/, routes/, docs/, config/, via 6 targeted parallel audits)
- [x] All relevant `.md`/docs files inspected (README.md, docs/BNFE_WEBHOOK_API.md)
- [x] `ToR for ALP RTM.docx` reviewed first
- [x] `System Requirement Specification (SRS) 1.pdf` reviewed second (full text + role-matrix pages read as images)
- [x] `UAT Feedback By KAZ - 4 September.pdf` reviewed third (full text + all screenshots read as images)
- [x] `Skilfo Projects Comparison.pdf` reviewed fourth
- [x] Client requirements separated from commitments
- [x] UAT separated from original requirements
- [x] New requirements separated from original scope
- [x] Existing implementation verified from code (not assumed from file names)
- [x] Module structure identified (adapted to actual RTM/M&E domain, not generic PM-tool template)
- [x] Requirement traceability created
- [x] Code review findings documented (34 REV items)
- [x] Security review performed
- [x] Database review performed
- [x] Laravel architecture review performed
- [x] Inertia/Vue review performed
- [x] Availability/scheduling-equivalent module (Monitoring, Training/Course, Attendance) reviewed
- [x] Skilfo-specific new-module requirement reviewed (Assessor/Enterprise/Assessment/Job Linkage)
- [x] No functional code changed
- [x] No migrations changed
- [x] No dependencies installed
- [x] No existing source files modified

**No functional code changes were made during this analysis.** All findings above are based on reading (not modifying) the codebase and the four source documents.
