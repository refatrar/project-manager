# Engineering Rules

Binding conventions for this repository. `AGENTS.md` and `CLAUDE.md` carry the Laravel Boost guidelines; this file records the decisions specific to this project. Where the two overlap, both apply.

## 1. Non-Negotiables

1. **Never cross the team boundary.** Every query that reads a tenant-owned record filters on `team_id`, or reaches it through a parent that does. A cross-team read is a security defect.
2. **Never store a secret in plain text.** Provider tokens and webhook secrets use the `encrypted` cast and are excluded from every payload sent to the browser.
3. **Never expose a model wholesale.** Build the frontend payload with an explicit method such as `toSetupArray()`.
4. **Never edit a migration that has run outside local development.** Add a new one.
5. **Run `vendor/bin/pint --dirty --format agent` before finishing any change that touches PHP.**

## 2. Migrations

- Generate with `php artisan make:migration`. Never hand-write the filename.
- Group related tables in one migration when they form a single unit of meaning, as `create_tasks_tables` does. Drop them in reverse order in `down()`.
- `down()` drops only what the same `up()` created. Never reach into another migration's tables.
- Use `->constrained()`, `->cascadeOnDelete()` and `->nullOnDelete()`. Do not write `->index()->references('id')->on(...)`; it adds an index MySQL already creates for the foreign key.
- Use `dropConstrainedForeignId()` in `down()` when the column has a foreign key. `dropColumn()` fails on MySQL while the constraint exists.
- Status columns are `string` with an explicit length and a default, cast to a PHP enum. **Do not use MySQL `ENUM`** — adding a value would require a locking `ALTER TABLE` for what is a product decision.
- Keep generated index names under 64 characters. When a composite key would exceed it, pass an explicit name as the second argument. MySQL rejects longer identifiers at migration time.
- **Do not put `deleted_at` in a unique key.** MySQL treats `NULL` as distinct, so the constraint would permit the duplicate live rows it was meant to block. Use a plain unique key and restore the soft-deleted row when re-adding.
- Add a composite index only for a query that exists, ordered by selectivity for that query. A column appearing in a `WHERE` clause is not on its own a reason for an index.
- Domain tables carry the full audit set: `created_by`, `created_at`, `updated_by`, `updated_at`, `deleted_by`, `deleted_at`. Append-only tables set `const UPDATED_AT = null` instead. Pure pivots carry only what they need.

## 3. Enums

- Live in `app/Enums`, are backed by `string`, and use `TitleCase` case names.
- Use `App\Enums\Concerns\HasOptions` for `label()`, `options()` and `values()`. Override `label()` only for cases where `Str::headline()` gets it wrong, such as `ReadyForQa` and `GitHub`.
- Put behaviour on the enum rather than in a `match` at the call site. `TaskStatus::isClosed()` and `ProjectMemberRole::canManageProject()` are the pattern.
- `options()` is the single source for frontend dropdowns. Never duplicate the option list in TypeScript.

## 4. Models

- Namespace by context: `App\Models\Setup` for configuration, `App\Models\OMS` for delivery, `App\Models\Git` for the integration.
- Declare `@property` and `@property-read` blocks. Larastan level 7 relies on them.
- Use the `#[Fillable]` attribute. Never list a foreign key that authorization depends on — `team_id`, `project_id` and `created_by` are set in the action, not mass-assigned.
- Declare a concrete return type on every relationship, with the generic docblock: `@return BelongsTo<User, $this>`.
- Casts go in the `casts()` method, not a `$casts` property.
- Use `App\Models\Concerns\HasAuditUsers` for the `creator`, `updater` and `deleter` relationships.
- Query constraints reused in more than one place become a local scope with the `#[Scope]` attribute. Reserve global scopes for soft deletes.
- **Do not name a relationship `scopes`.** It shadows Eloquent's query-builder passthrough. `ProjectModule::deliveryScopes()` exists for this reason.

## 5. Controllers

- Thin. Authorize, validate, delegate, respond. Business rules live in an action or service.
- Validation lives in a form request under a namespace mirroring the controller.
- Respond in both shapes, following `Setup\TaskTypeController`: an Inertia redirect with a flashed toast for page visits, JSON for modal forms. Keep the branch in one private helper.
- Generate links with `route()` or a Wayfinder import from `@/actions` or `@/routes`. Never hardcode a URL.
- Set `created_by` and `updated_by` from `$request->user()` in the controller or action, never from input.

## 6. Queries

- Eager load any relationship that will be read in a loop. `Model::preventLazyLoading()` is on outside production, so an N+1 fails the test rather than slowing it.
- Use `withCount()` for counts. Never load a relationship to call `->count()` on it.
- Constrain columns on eager loads that pull large text, and keep the keys Eloquent needs for matching.
- Use `chunkById()` or `lazyById()` for anything that iterates a whole table.
- Keep queries out of components. Prepare data in the controller.

## 7. Derived Columns

`tasks.logged_hours`, `projects.progress_percentage`, `project_modules.progress_percentage` and `projects.next_task_number` cache values that could be computed.

- Each has exactly one writer. Document it in `ARCHITECTURE.md` when you add another.
- Never write one from more than one place. Two writers guarantee drift.
- `next_task_number` is incremented inside the same transaction that inserts the task.
- Provide a reconciliation command for any counter that can drift, rather than trusting it indefinitely.

## 8. Git Integration

- The webhook endpoint verifies the signature, writes a `git_events` row, and returns. Nothing else.
- Parsing and ingest happen on the queue, driven by `GitEvent::unprocessed()`.
- Every ingest is idempotent. Providers retry; `unique(git_repository_id, external_event_id)` and `unique(git_repository_id, sha)` are what make retries safe.
- Record how a commit-to-task link was found in `git_commit_task.link_source`. A parsed link and a manual one are not the same fact.
- Resolve a commit author through `git_identities`. Never match on a raw email string at the call site.
- Never log a payload containing a token.

## 9. Tests

- Feature tests by default, in `tests/Feature`, at the same relative path as the class under test. Unit tests only for framework-free logic.
- Name the behaviour and its result: `test_a_user_cannot_be_added_to_the_same_project_twice`. Not `test_store` or `test_validation`.
- Use factories and their states. Add a state rather than repeating an attribute array.
- Cover the constraint, the cast and the scope. Those belong to this project, not to the framework.
- Assert raw column values through `DB::table()` when testing a cast. Reading through Eloquent applies the cast and proves nothing.
- Every endpoint gets an unauthenticated case, an unauthorized case and a cross-team case.
- Run the narrowest set that covers the change: `php artisan test --compact path/to/Test.php`.

### Local environment note

This machine has no `pdo_sqlite` extension, so the `sqlite`/`:memory:` connection configured in `phpunit.xml` fails for all 110 pre-existing tests. Until `php8.3-sqlite3` is installed, run tests against MySQL:

```bash
DB_SOCKET=/var/run/mysqld/mysqld.sock \
DB_CONNECTION=mysql \
DB_DATABASE=pm_schema_check \
vendor/bin/phpunit tests/Feature/OMS
```

MySQL on this machine listens on a Unix socket only, not on `127.0.0.1:3306`, so `DB_SOCKET` is required for every Artisan command too.

## 10. Frontend

- Pages in `resources/js/pages`, mirroring the route path.
- Every server payload has a matching type in `resources/js/types`.
- Check for an existing component before writing one.
- Enum values cross the wire as their backed string; labels come from `options()`.

## 11. Definition of Done

- [ ] `vendor/bin/pint --dirty --format agent` reports no changes.
- [ ] `vendor/bin/phpstan analyse` reports no errors.
- [ ] Tests covering the changed behaviour and its failure modes pass.
- [ ] New migrations run **and** roll back cleanly.
- [ ] No new query runs inside a loop.
- [ ] Tenant scoping is present on every new query.
- [ ] `MEMORY.md` records any decision a future contributor would otherwise have to re-derive.
