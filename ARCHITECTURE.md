# Architecture

## Stack

| Layer | Technology |
| --- | --- |
| Runtime | PHP 8.3 |
| Framework | Laravel 13 |
| Auth | Laravel Fortify (2FA and passkeys enabled) |
| Transport | Inertia.js v3 |
| Frontend | React with TypeScript, Tailwind CSS, shadcn-style components |
| Route typing | Laravel Wayfinder, generating `@/actions` and `@/routes` |
| Database | MySQL 8 |
| Formatting | Laravel Pint (`laravel` preset) |
| Static analysis | Larastan at level 7 |
| Tests | PHPUnit 12 |

## Request Flow

```
Browser
  → Route (routes/web.php, prefixed {current_team})
    → auth + verified + EnsureTeamMembership middleware
      → Controller (thin: authorize, validate, delegate, respond)
        → Form Request (validation) / Policy (authorization)
          → Action or Service (domain behaviour)
            → Eloquent model (persistence, casts, scopes)
        → Inertia::render(...) or JsonResponse
  ← React page under resources/js/pages
```

Controllers respond in two shapes, following the pattern already established by `Setup\TaskTypeController`: an Inertia redirect with a flashed toast for full page visits, and a JSON payload for modal forms. Keep that dual response in a single private helper per controller rather than branching inside each action.

## Directory Map

```
app/
  Concerns/            Reusable model traits (HasTeams, HasProjectWork)
  Data/                Readonly DTOs passed to the frontend
  Enums/               Backed string enums; Enums/Concerns/HasOptions supplies label()/options()
  Http/
    Controllers/
      Setup/           Configuration screens (scopes, task types, labels)
      OMS/             Project delivery screens (planned)
      Git/             Repository connection and webhook endpoints (planned)
    Middleware/        EnsureTeamMembership and friends
    Requests/          Form request validation, mirrored per controller namespace
  Models/
    Setup/             Scope, TaskType, Label
    OMS/               Projects, modules, tasks, meetings, to-dos, time, collaboration
    Git/               Repositories, branches, commits, pull requests, events, identities
    Concerns/          HasAuditUsers
database/
  migrations/          Schema, grouped by domain rather than one table per file
  factories/           Mirrors the model namespace (Factories\OMS, Factories\Git, Factories\Setup)
  seeders/             Reference data
resources/js/
  pages/               Inertia page components
  components/          Shared and feature components
  types/               TypeScript mirrors of server payloads
tests/
  Feature/             Default level: HTTP and model behaviour against a real database
  Unit/                Framework-free logic only
```

## Bounded Contexts

The schema is deliberately split into contexts that can be worked on independently. Arrows show the direction of dependency.

```
              ┌──────────────┐
              │   Platform   │  users, teams, team_members, team_invitations
              └──────┬───────┘
                     │
        ┌────────────┼─────────────┬────────────────┬──────────────────┐
        ▼            ▼             ▼                ▼                  ▼
  ┌──────────┐ ┌───────────┐ ┌──────────┐   ┌──────────────┐   ┌──────────────┐
  │  Setup   │ │ Delivery  │ │ Meetings │   │     Time     │   │     Git      │
  │ scopes   │ │ projects  │ │ meetings │   │ schedules    │   │ repositories │
  │ task_    │ │ modules   │ │ attendees│   │ time_off     │   │ branches     │
  │  types   │ │ members   │ │ agenda   │   │ allocations  │   │ commits      │
  │ labels   │ │ milestones│ │          │   │ time_logs    │   │ pull_requests│
  └────┬─────┘ │ sprints   │ └─────┬────┘   └──────┬───────┘   │ events       │
       │       │ tasks     │       │               │           │ identities   │
       └──────▶│ assignm.  │◀──────┴───────────────┴──────────▶└──────────────┘
               │ dependenc.│
               │ history   │
               └─────┬─────┘
                     │
        ┌────────────┴────────────┬─────────────────────┐
        ▼                         ▼                     ▼
  ┌───────────┐          ┌────────────────┐    ┌─────────────────┐
  │  To-dos   │          │ Collaboration  │    │   Reporting     │
  │ todo_lists│          │ comments       │    │ progress_       │
  │ todo_items│          │ attachments    │    │  snapshots      │
  └───────────┘          │ activities     │    │ activities      │
                         └────────────────┘    └─────────────────┘
```

`Delivery` is the hub. Every other context except `Platform` and `Setup` points at it, and nothing in `Delivery` reaches back into `Git`, `Time` or `Meetings` for its own invariants. That keeps the Git integration replaceable.

## Multi-Tenancy

Teams are the tenant boundary. Three rules make it enforceable:

1. **Root records carry `team_id`.** `projects`, `labels`, `meetings`, `todo_lists`, `time_logs`, `git_repositories`, `comments`, `attachments` and `activities` all store it directly, so a tenant filter never needs a join.
2. **Descendant records inherit through their parent.** `tasks` reach the team through `project_id`; `todo_items` through `todo_list_id`. Adding `team_id` to those tables would create a second source of truth that can drift.
3. **`tasks` stores `project_id` even though `project_module_id` would imply it.** This is an intentional denormalisation: it lets a task exist before the module breakdown does, and it keeps the hot path (all tasks for a project) off a join.

Scoping is applied explicitly through local scopes such as `Project::forMember()` rather than a global scope, so background jobs and reports can opt out deliberately.

## Status Modelling

Status columns are `string` in the database and a PHP backed enum in the model. They are never MySQL `ENUM`.

A MySQL `ENUM` needs an `ALTER TABLE` to add a value, which is a locking schema migration for what is a product decision. A `string` column plus a cast keeps the value set in version-controlled PHP, gives one place to attach behaviour such as `TaskStatus::isClosed()`, and generates the frontend option list through `HasOptions::options()`.

The pre-existing `scopes` and `task_types` tables still use MySQL `ENUM`. They are left alone to avoid churning working code; new tables follow the string-plus-enum rule.

## Asynchronous Work

Three kinds of work belong on the queue, not in a request:

| Job | Trigger | Writes to |
| --- | --- | --- |
| Git webhook ingest | Webhook receipt persists a `git_events` row, then dispatches | `git_commits`, `git_branches`, `git_pull_requests`, `git_commit_task` |
| Repository backfill | Connecting a repository | Same as above, plus `git_repositories.sync_status` |
| Nightly progress snapshot | Scheduler (`oms:snapshot-project-progress`, daily) | `project_progress_snapshots`, `projects.progress_percentage`, `projects.health`, `project_modules.progress_percentage`, `milestones.progress_percentage` |

`git_events` is the durability seam. The webhook endpoint only validates the signature and stores the payload, so ingest failures are replayable from `GitEvent::unprocessed()`.

## Derived Values

Some columns cache values that could be computed. Each is a deliberate trade, and each needs one owner that writes it.

| Column | Derived from | Written by |
| --- | --- | --- |
| `tasks.logged_hours` | `sum(time_logs.duration_minutes)` | Time log observer or action (Phase 6, not yet built) |
| `projects.progress_percentage` | Task completion across the project | `SnapshotProjectProgress` (`oms:snapshot-project-progress`, scheduled daily) |
| `projects.health` | Schedule position and hours variance (`App\Actions\OMS\DetermineProjectHealth`) | `SnapshotProjectProgress`, same run as progress. A project manager can still edit `health` by hand between runs; the next nightly run recomputes and overwrites it — there is no "manual override" flag. |
| `project_modules.progress_percentage` | Task completion within the module (direct tasks only, not recursive into child modules) | `SnapshotProjectProgress` |
| `milestones.progress_percentage` | Task completion within the milestone | `SnapshotProjectProgress` |
| `projects.next_task_number` | `max(tasks.number) + 1` | Task creation action, inside a transaction |

`next_task_number` exists so a per-project task reference can be allocated without a `MAX()` scan and without a race. Increment it in the same transaction that inserts the task.

## Frontend Contract

Server payloads are shaped by explicit model methods such as `toSetupArray()`, not by serialising the whole model. Every payload shape has a matching type in `resources/js/types`. Enum values cross the wire as their backed string, and their labels come from `options()` so a single change in PHP updates both the cast and the dropdown.
