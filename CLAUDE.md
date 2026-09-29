# CLAUDE.md

- DO NOT RUN PINT OR PHPSTAN, I will be doing it manually when reviewing changes

Laravel package (`jgawlik/laravel-journal`) that adds a private, per-user
journal JSON API (posts, categories, tags) to host Laravel apps. Supports
Laravel 12–13. There is no app skeleton here; Orchestra Testbench provides one
for tests.

## Commands

```bash
composer test       # Pest feature tests (Testbench, SQLite in memory)
composer analyse    # Larastan level 7 (phpstan.neon.dist)
composer format     # Pint; use `vendor/bin/pint --test` to check only
vendor/bin/pest --filter="hides other users"   # single test
```

Run all three before calling a change done.

CI (`.github/workflows/ci.yml`) runs the test matrix across PHP 8.2–8.5 ×
Laravel 12–13 × prefer-lowest/prefer-stable (Testbench 10/11 pinned per
Laravel version), plus Larastan and `pint --test`. New code must work on the
lowest supported versions too, so don't use framework features newer than
Laravel 12 or PHP 8.2 syntax additions without guarding.

## Layout

- `src/`: namespace `Jgawlik\LaravelJournal\`.
- `database/{migrations,factories,seeders}`: these live at the package root,
  **not** under `src/`. Namespaces are `...\Database\Factories` and
  `...\Database\Seeders`. Directory case matters, because production servers run
  Linux.
- `config/journal.php`: route prefix, middleware and name prefix, the user
  model, and pagination.
- `routes/api.php`: route definitions only. The prefix, middleware and
  `journal.` name prefix are applied in
  `JournalServiceProvider::registerRoutes()`. Host apps can set
  `journal.routes.enabled` to `false` and register their own routes; route
  registration is also skipped when routes are cached.
- `tests/`: Pest, with `tests/TestCase.php` and a `Fixtures\User` model and
  factory.

## Architecture rules

- **Ownership:** posts and tags belong to a user (`user_id`). Categories are
  global.
  - Every post or tag query in a controller must be scoped with
    `->ownedBy($request->user())`.
  - Check ownership with `$model->isOwnedBy($user)`. It compares as strings, so
    it works for int, UUID and ULID keys. Never use `===` on `user_id` directly.
  - Owned models implement `Contracts\Ownable` and use the
    `Models\Concerns\BelongsToOwner` trait, which provides `isOwnedBy()`,
    `scopeOwnedBy()` and `user()`.
- **Authorization:**
  - `JournalPostPolicy` and `JournalTagPolicy` extend `OwnedModelPolicy`, which
    returns `Response::denyAsNotFound()` for other users' records, so outsiders
    get 404, not 403. Keep it that way.
  - Form requests authorize with `Gate::inspect(...)`. Controllers call
    `Gate::authorize(...)` for `show` and `destroy`.
- **Categories:** writes are gated by
  `JournalServiceProvider::MANAGE_CATEGORIES`, which denies by default. Host
  apps override it with `Gate::define`.
- **Route parameters** are named `post`, `category` and `tag`, set with
  `->parameters()` in the routes file. Controller argument names and
  `$this->route('...')` calls must match them, or model binding silently injects
  an empty model.
- **Tags bind by slug, and slugs are unique only per user.**
  `JournalTag::resolveRouteBinding()` scopes the lookup to `auth()->id()`.
- **Post slugs** are unique per `(user_id, slug)`.
  `JournalPost::uniqueSlugFor()` generates them on create with a `-2`, `-3`
  suffix. Slugs don't change when the title changes.
- **Requests:**
  - List endpoints use `*IndexRequest` classes that extend `IndexRequest`. Add
    sortable columns to `sortable()` and searched columns to `searchable()`;
    never pass raw input to `orderBy`. `searchable()` columns are interpolated
    into raw SQL, so they must be literals. Search escapes `%`/`_` (LIKE
    wildcards match literally) and uses `ilike` on Postgres.
  - Controllers build the scoped query and hand it to
    `$request->paginate($query)`, which applies search, sort (with an id
    tie-breaker) and pagination.
  - Create and update share one request per resource: fields are `required` on
    POST and `sometimes` otherwise.
  - Validate every slug with the `Rules\Slug` rule (lowercase letters, digits,
    single hyphens), not `alpha_dash`.
- **Responses:**
  - Return API resources directly: `Resource::collection($paginator)` and
    `Resource::make($model)`.
  - For 201, use `->response()->setStatusCode(201)`.
  - `destroy` returns `response()->noContent()`.
  - Don't build `data`/`meta` arrays by hand.
  - Don't expose `user_id`; responses only ever contain the caller's own
    records.
  - Categories are shared, so any count or aggregate over their posts must be
    scoped with `ownedBy()`, or it leaks other users' activity.
- **User model:**
  - Always get it from `JournalServiceProvider::userModel()` or
    `config('journal.user_model')`. Never hard-code `App\Models\User` or the
    `users` table.
  - The migration uses `foreignIdFor()` so the key type matches the host app's
    user model.
- **Dependencies:** Sanctum is not a dependency. `auth:sanctum` is only the
  default in the config. Tests use `['api', 'auth']`.

## Conventions

- `declare(strict_types=1);` in every PHP file under `src/` and
  `database/factories`.
- Add generic docblocks to relations, for example
  `@return BelongsTo<JournalCategory, $this>`, and a `@mixin` on resources, so
  that Larastan passes. Don't add `@phpstan-ignore` or baseline entries.
- Models use the `casts()` method, not the `$casts` property, and provide
  `newFactory()` overrides.
- Wrap multi-step writes, such as a post plus its tag sync, in
  `DB::transaction`. Use `createOrFirst` for single find-or-create; batch
  lookups like `resolveTagIds()` use one `whereIn` plus `insertOrIgnore` for the
  missing rows, never a query per item.
- Unique indexes are the source of truth under concurrency. Map a
  `UniqueConstraintViolationException` on a client-supplied value to a 422 on
  that field, and retry when the value was generated (see
  `JournalPostController::store()`).

## Migrations and releases

- Before the first release, the initial migration may still be edited. After
  release, **never edit an existing migration**; add a new one.
- Users can publish migrations with `--tag=journal-migrations` and the config
  with `--tag=journal-config`. After publishing migrations they must set
  `journal.run_migrations` to `false`, or the migrations run twice.
- Record user-facing changes under `[Unreleased]` in `CHANGELOG.md`, and update
  `README.md` when endpoints, fields or config change.
- New dev-only files at the repo root need an `export-ignore` line in
  `.gitattributes`.
- `composer.lock` is ignored because this is a library. Versions come from git
  tags, so don't add a `version` field to `composer.json`.

## Testing notes

- `TestCase::defineDatabaseMigrations()` _registers_ Testbench's users migration
  path with the migrator instead of running it, because `RefreshDatabase`'s
  `migrate:fresh` would drop the tables otherwise.
- Every new endpoint or behavior needs a feature test, including one for access
  by another user (expect 404).
