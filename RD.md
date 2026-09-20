# Requirements Document

Product: **Kazsoft Project Manager** — a multi-tenant project and task management tool for software delivery teams.

This document defines what the product must do. `DESIGN.md` explains how the database supports it, and `TASKS.md` tracks the work needed to deliver it.

## 1. Scope and Actors

### Actors

| Actor | Description |
| --- | --- |
| Team owner | Owns a tenant, manages billing-level settings and all projects. |
| Project manager | Plans projects, modules, milestones and sprints; assigns work; approves timesheets. |
| Team lead | Plans and reviews work inside assigned projects. |
| Contributor | Developer, designer, QA or DevOps engineer who delivers tasks and logs time. |
| Client | External stakeholder with read access to progress on a specific project. |

### Tenancy

Every record belongs to a team. A user may belong to many teams and holds a role per team and a separate role per project. All application routes are already namespaced under `{current_team}`, and that boundary must never be crossed by a query.

## 2. Functional Requirements

### FR-1 Multiple project handling

- FR-1.1 A team can run any number of concurrent projects.
- FR-1.2 Each project has a short immutable code, unique within the team, used to build task references such as `ALPHA-114`.
- FR-1.3 A project is broken into modules, and a module may nest inside a parent module to arbitrary depth.
- FR-1.4 A project tracks planned dates, actual dates, estimated hours, budget, priority and health.
- FR-1.5 Projects can be archived without losing history.
- FR-1.6 A user sees only the projects in the current team, and only those where the user is a member unless the team role grants wider visibility.

### FR-2 Task management

- FR-2.1 A task belongs to a project. A module is optional, so work can be captured before the breakdown exists.
- FR-2.2 A task may have subtasks and may declare dependencies on other tasks (`blocked_by`, `relates_to`, `duplicates`, `parent_of`).
- FR-2.3 A task carries a lifecycle status, an independent review outcome, and an independent deployment stage. These three are separate axes and must not be collapsed into one column.
- FR-2.4 Every status change is recorded with who changed it and when, so cycle time and lead time can be computed.
- FR-2.5 A task carries estimated hours, logged hours, remaining hours and a progress percentage.
- FR-2.6 Tasks can be labelled, prioritised, ordered on a board, and assigned to a milestone and a sprint.

### FR-3 Assignment across projects and users

- FR-3.1 A task can be assigned to several users at once.
- FR-3.2 Each assignment carries a role (`assignee`, `reviewer`, `qa`, `watcher`) and its own status.
- FR-3.3 A user may hold assignments on tasks in any number of projects simultaneously.
- FR-3.4 A user cannot hold the same role on the same task twice.
- FR-3.5 Reassignment preserves the previous assignment record rather than overwriting it.
- FR-3.6 Each assignment may carry allocated hours, which feeds capacity planning.

### FR-4 Progress monitoring

- FR-4.1 Project, module and milestone progress must be reportable as a percentage.
- FR-4.2 The system stores a daily snapshot per project (and optionally per sprint) with task counts, hours and a health rating, so burndown and trend charts do not require recomputation over history.
- FR-4.3 Overdue and blocked tasks must be identifiable in a single query.
- FR-4.4 A project activity feed shows what changed, who changed it, and when.

### FR-5 To-do lists

- FR-5.1 A user can keep a personal to-do list, and a dated daily list.
- FR-5.2 A task can carry a checklist.
- FR-5.3 A meeting can produce a list of action items.
- FR-5.4 A to-do item may be linked to a real task so that ticking it off and closing the task stay connected.
- FR-5.5 A to-do item may be assigned to another user, given a due date and an estimate in minutes.
- FR-5.6 Lists can be generated automatically, for example "my open tasks due this week", and the generated origin must be distinguishable from a hand-written list.

### FR-6 Meeting minutes

- FR-6.1 A meeting can be scheduled with a type, time window, location or URL, and an optional project or sprint.
- FR-6.2 Attendees may be registered users or external guests; each has a role and an attendance outcome.
- FR-6.3 An agenda is a list of ordered items, each optionally tied to a task and a presenter.
- FR-6.4 Minutes and decisions are recorded against the meeting, with a publication timestamp.
- FR-6.5 Action items arising from a meeting are to-do items, so they use the same assignment and completion machinery as all other to-dos.
- FR-6.6 Time spent in a meeting can be logged against the meeting.

### FR-7 Git integration

- FR-7.1 A team can connect repositories from GitHub, GitLab, Bitbucket or Gitea, optionally self-hosted.
- FR-7.2 A repository may be linked to a project.
- FR-7.3 Credentials and webhook secrets must be stored encrypted at rest and never returned to the client.
- FR-7.4 Incoming webhook payloads are persisted verbatim before processing, so a failed ingest can be retried without data loss.
- FR-7.5 Commits, branches and pull requests are ingested with author, timestamps and change statistics.
- FR-7.6 A commit is linked to tasks by parsing the commit message, by parsing the branch name, via its pull request, or manually. One commit may touch several tasks.
- FR-7.7 A provider account is mapped to an application user so commit history can be attributed to a person.
- FR-7.8 Push history must be queryable per repository, per project, per user and per date range.

### FR-8 Availability and occupied hours

- FR-8.1 Each user has a recurring weekly work schedule with effective-from and effective-until dates, so contract changes are historically accurate.
- FR-8.2 Approved time off reduces capacity. Pending time off does not.
- FR-8.3 Forward bookings of a user's hours are recorded as resource allocations with a date range and hours per day.
- FR-8.4 The system must answer, for a user and a date range: scheduled capacity, occupied hours, and remaining free hours.
- FR-8.5 The system must answer, for a required number of hours and a date range, which users have enough free capacity.
- FR-8.6 Actual effort is recorded as time logs, which are separate from planned allocations.
- FR-8.7 Time logs support a running timer, manual entry and import, and may require approval before billing.

### FR-9 Collaboration

- FR-9.1 Projects, modules, tasks, meetings and to-do items can be commented on, with threaded replies and user mentions.
- FR-9.2 Comments can be marked internal so they are hidden from client-role users.
- FR-9.3 Files can be attached to any of those records.

## 3. Non-Functional Requirements

| ID | Requirement |
| --- | --- |
| NFR-1 | Tenant isolation is enforced on every read and write. A cross-team read is a defect, not a UX problem. |
| NFR-2 | List screens paginate and must not issue per-row queries. Relationship counts use aggregate queries. |
| NFR-3 | Git ingest runs on queued jobs. A provider outage must not block a web request. |
| NFR-4 | Provider tokens and webhook secrets are encrypted at rest. |
| NFR-5 | Destructive actions on domain records are soft deletes with a recorded actor. |
| NFR-6 | Availability and progress queries answer for a 90-day window over a team of 50 users without a full table scan. |
| NFR-7 | Reporting reads come from stored snapshots rather than recomputing history. |
| NFR-8 | Timestamps are stored in UTC. A user's work schedule carries its own timezone. |

## 4. Out of Scope for the Current Phase

Only the data layer is being built now. The following are deliberately deferred and tracked in `TASKS.md`: HTTP controllers and Inertia pages, provider API clients and webhook endpoints, the progress and availability calculators, notifications, and invoicing.

## 5. Acceptance Signals

The data layer is complete when, for a seeded team, a single query can answer each of the following:

1. Which tasks are assigned to a given user across all of their projects, and which of those are overdue.
2. How many free hours a given user has next week.
3. What was decided in the last client meeting for a project, and which action items remain open.
4. Which commits and pull requests delivered a given task.
5. How a project's completion percentage moved over the last 30 days.
