# `medior-interview` – technical audit (English)

## Baseline and scope

| Item | Value |
|---|---|
| Repository | `gyorgybakocs/medior-interview` |
| Default / reviewed branch | `master` |
| Reviewed commit | `a6f9462727576965f40d8fbddf94e5897dd829bb` |
| Commit date | 2026-10-14 09:45:54 UTC |
| Working tree | Clean at the start of the review; this commit only was reviewed |
| Laravel | `laravel/framework` 12.69.3 |
| PHP | Composer manifest requires `^8.2`; the development Compose example selects PHP 8.4 |
| Inertia | `inertiajs/inertia-laravel` 2.0.28; the frontend lockfile pins `@inertiajs/vue3` 2.0.3 |
| Frontend | Vue 3.5.43, TypeScript 5.7.3, Vite 6.4.4 |
| PHPUnit | 11.5.57 |
| Database | Development Compose uses PostgreSQL 15.17; PHPUnit config selects an in-memory SQLite database |
| Runtime model | Development Compose runs PHP-FPM and one `queue:work` process under Supervisor; Redis is the development queue. I did not observe a live worker. |

The review covered the Laravel backend, Vue/Inertia frontend and backend–frontend data paths, migrations, configuration, lockfiles, tests, GitHub Actions workflows, Docker/Compose setup and Makefile. I used the project README only to understand expected behavior; I did not assess the checklist or implementation plan as code quality. I checked the current `master` head on GitHub; the reviewed commit has the same file tree as the earlier downloaded checkout, so the source findings are now pinned to the exact current SHA.

### Coverage matrix

| Area | Files/modules reviewed | Source review | Checks executed | Remaining verification |
|---|---|---|---|---|
| Routes, controllers, requests, middleware | `app/routes/*`, `app/app/Http/**`, `bootstrap/app.php` | Application routes and business actions reviewed; auth starter routes/controllers also checked | Source tracing | HTTP/feature tests not run |
| Models, migrations, data access | `app/app/Models/**`, `app/app/Repositories/**`, `app/database/migrations/**`, `config/database.php` | Reviewed, including unique/FK constraints and user scopes | Source tracing | PostgreSQL-specific behavior and concurrent transactions not executed |
| Services, GitHub client, queue job | `app/app/Services/**`, `app/app/src/Github/GithubClient.php`, `app/app/Jobs/SyncGithubTarget.php`, `config/queue.php`, Supervisor | Add/sync path traced from GitHub request through persistence and readback | Source tracing; GitHub API parameters compared with official documentation | No live queue worker or GitHub request executed |
| Frontend and contract | `resources/js/pages/**`, business-flow components and composables, `types/index.ts`, `resources/views/app.blade.php`, Vite config | Active target/repository/GitHub settings pages and auth pages reviewed; UI primitives inventoried and scanned for risky patterns, but not audited line by line | Static source tracing | Vite build, TypeScript check and browser check not run |
| Tests and test configuration | `app/tests/**`, `phpunit.xml`, Composer lock | Business tests, HTTP/queue fakes, database boundaries and assertion patterns reviewed | Not run | Full suite and PostgreSQL integration |
| CI, dependencies and runtime tooling | `composer.json/lock`, `package.json/lock`, `.github/workflows/*`, Dockerfiles, Compose files, CI shell scripts, `Makefile`, relevant config | Reviewed | `git diff --check` passed; reviewed checkout is clean | CI workflow, image build and npm audit not run |

**Execution limit:** the checkout had no `vendor/` or `node_modules/`, and the review environment has no PHP CLI. I therefore do not claim to have run PHPUnit, Pint, npm lint/format/build or the container workflows. I did not install dependencies, start containers, run migrations, or modify the repository.

## Summary

The code handles several important fundamentals well: sensitive routes require authentication; the server checks a user's target subscription before target mutations; the GitHub token is stored with an encrypted model cast, hidden from JSON serialization and omitted from queue job payloads. Repository search binds SQL values and allowlists sort values. The sync job does not hold a database transaction during the external request, wraps repository writes in a transaction, and dispatches the queue job after commit. Tests often use a real test database with HTTP/queue fakes and data-driven cases.

**The most serious finding** is that the application uses a user's GitHub token to request organization repositories with the API's default `type=all`, then saves private repository metadata under a globally shared target. Another user who subscribes to that organization can read those records through the shared target. This conflicts with the project's stated requirement to sync public repositories only.

## Subsystem assessments

### Laravel backend and access control

**Working well:** `routes/web.php` places repository and target operations behind `auth` middleware. `TargetController::sync()` and `destroy()` check `belongsToUser()` before acting. Repository-list queries scope through the authenticated user's targets. The GitHub settings request writes only validated fields, and the `User` model marks the token as `encrypted` and `hidden`. Password-reset and email-verification routes use the starter code's standard middleware and signed-link checks.

**No additional higher-severity backend defect was established.** The high-severity shared-data exposure arises from the interaction between GitHub integration, shared persistence and target subscriptions; see **F-01**. The queue synchronization path has a maintainability and evidence gap documented as **F-05**.

### Architecture, SOLID and layer boundaries

**Responsibilities:** HTTP validation lives in Form Requests, route-level access in middleware/controllers, external HTTP calls in `GithubClient`, data queries/writes in repositories, workflow orchestration in services and queue lifecycle handling in the job. This division is generally understandable at the current application size and does not introduce a broad framework of abstractions without a clear need. `TargetController` still serializes target and sync-operation DTOs, while `SyncGithubTarget` coordinates state transitions, access rechecks, error mapping and logging; if more business flows are added, those responsibilities could be split into mapper/workflow components.

**SOLID assessment:** SRP is mostly respected at the main boundaries, but `TargetRepositoriesSynchronizer` contains two sync paths with different state handling; one is not called by the current queue job. For OCP/LSP, I found no inheritance or extension mechanism that is demonstrably wrong today. Adding a new GitHub target type would touch endpoint selection, validation, localization and services, but a provider abstraction is not justified for this single integration yet. ISP/DIP are partial: `RequestGithubTargetSync` receives interfaces (`Dispatcher`, `ConnectionInterface`, logger), while orchestration services depend on concrete repositories and client classes; class mocks still make these testable. This is an extensibility tradeoff, not a confirmed SOLID violation.

**Repository/service justification:** `GithubRepositoryRepository` is more than a CRUD wrapper: it centralizes user scoping, filtering/sorting, aggregation and upsert behavior. `GithubTargetRepository` also owns query, subscription and access-check operations, but `latestForUser()` currently appears only in its own test. `TargetRepositoriesSynchronizer::sync()` is likewise only reached by its unit tests; the live queue path calls `syncForOperation()`. Only `fromException()` and `translationKey()` are currently used from `AddGithubTargetResult`; its `saved()`, `notFound()` and `wasSaved()` APIs are leftover unused surface. These are removable remnants. I found no other class that demonstrably only forwards one line and adds net complexity.

**Extensibility and performance:** repository filters are constrained to configured values, while target and sync statuses use enums. A new target type or data source would affect several places, but a plugin/provider architecture is not needed for this small application. A concrete growth bottleneck is the eager loading of every repository on the target page (**F-03**); the repository listing already uses server-side pagination.

### GitHub integration and data flow

`GithubClient::getRepositories()` calls the organization endpoint (`/orgs/{org}/repos`) and paginates, but sends only `per_page` and `page`. `AddGithubTargetForUser` uses the current user's token, then finds or creates a global target by type and name, attaches the user, and saves the returned repositories under that target's `github_target_id`. `github_repositories.external_id` is globally unique. Target and repository read paths expose a shared target's repository collection to every subscribed user.

**Needs correction:** organization names with different letter casing can create distinct local targets even when they identify the same GitHub organization; the globally unique repository upsert can then move rows to the other target (**F-02**).

### Database and consistency

The schema includes useful foreign keys, cascades and uniqueness constraints. A user–target subscription pair is unique, a repository's external ID is unique, and a sync operation's target reference becomes `null` if its target is deleted. Syncing an existing target performs the GitHub call first, then saves repositories and successful target state in a transaction. The mapping-failure test uses one valid and one invalid repository to check rollback of all repository writes.

**Database-specific limitation:** development uses PostgreSQL, while PHPUnit config uses SQLite. The tests therefore do not, by themselves, establish PostgreSQL uniqueness, collation, locking or concurrent-race behavior. I did not execute either engine. The README explicitly says repository-disappearance reconciliation can be left out; I do not report that as a defect.

### Queue and operations

`RequestGithubTargetSync` creates or finds the operation inside a transaction, then requests `afterCommit()` dispatch. Existing-target sync also uses a database row lock and `WithoutOverlapping` queue middleware. The job timeout is 60 seconds, the lock is 75 seconds, and Redis `retry_after` is 90 seconds. Development Compose starts the app after PostgreSQL and Redis health checks; Supervisor manages both PHP-FPM and a queue worker.

These settings are established from source, not a running environment. The Makefile CI targets run several Compose services with `run --rm` and then shut down the CI stack with `down`; `make ci` also shuts down the development stack first. I did not run these commands.

### Frontend and contract

The target page uses fields matching the server payload; the repository page uses server-side pagination, sorting, language/status filtering and search by target or description. Inertia forms disable repeat submission while `form.processing` is true. Sync polling starts only while an operation is active and stops when the server reports none. Vue interpolation uses the default escaping. `Pagination.vue` uses `v-html` only for labels produced by Laravel's paginator; repository-provided text does not reach that path.

**Current scaling limitation:** the target page paginates to five targets, but eager-loads each target's complete repository collection and serializes it into the Inertia props, even while the UI list is collapsed. For an organization with thousands of repositories, response size and server memory can grow independently of the target page size. This is a structural risk, not a measured slowdown (**F-03**).

### Test strategy and evidence strength

Feature tests use `RefreshDatabase` against the application test database, replace GitHub HTTP calls with `Http::fake()` and often prevent any non-faked outbound requests. The tests for authentication, access control, repository scoping/filtering/sorting, target subscriptions, queue operation states and transaction rollback provide meaningful coverage. PHPUnit `DataProvider` attributes exercise real scenario variations (for example user/org, HTTP error statuses and operation states), rather than merely renaming duplicate tests.

Evidence limits: the test database is SQLite, so it does not establish PostgreSQL locking, collation or unique-index behavior; jobs are invoked with direct `app()->call()` calls, not through a live Redis worker. There are no regression tests for private-data sharing or case-variant target names. `TargetRepositoriesSynchronizerTest` exercises `sync()`, but the job calls only `syncForOperation()`, so the older unit tests do not prove the target-status behavior of the current queue path. `SyncGithubTargetJobTest` explicitly asserts that on failed sync the prior `GithubTarget.sync_status` and `last_error` stay unchanged while the new failure is recorded on the operation row. The UI currently prioritizes operation status, but the split state sources create a maintenance risk (**F-05**).

### End-to-end data paths

- **Adding a target:** authenticated, validated `POST /targets` creates a sync operation associated with the user and dispatches a queue job. At execution, the job loads the user's current token, requests organization repositories, finds or creates a global target, records the user's subscription and stores repository data. `GET /targets` also returns repositories for subscribed targets. This path creates the **F-01** exposure.
- **Syncing an existing target:** the route authenticates and checks the subscription; the service locks the target and creates or reuses an active operation; the job checks the user–target relationship again at execution. After the GitHub request, repository upserts and successful target state are stored in a transaction. Queue dispatch occurs after the operation transaction commits.
- **Reading:** `/repositories` scopes the query to targets followed by the authenticated user, preserving target access control. That scope does not separate private repository metadata already saved in a global target from its other subscribers (**F-01**).

## Prioritized findings

| ID | Classification | Severity | Location | Trigger and consequence | Recommendation | Verification |
|---|---|---|---|---|---|---|
| F-01 | Defective | **High** | `app/app/src/Github/GithubClient.php:37–57`; `app/app/Services/AddGithubTargetForUser.php:23–38`; `app/app/Repositories/GithubRepositoryRepository.php:92–112`; `app/database/migrations/0001_01_01_000004_create_github_targets_table.php:21`; `app/database/migrations/0001_01_01_000006_create_github_repositories_table.php:13–27`; `app/app/Http/Controllers/TargetController.php:97–132` | A user's token can access private repositories in an organization. The organization request does not set `type=public`; GitHub's endpoint defaults to `all`. Private repository metadata is saved under a global target, then another authenticated user subscribed to the same organization can read it through the target page or repository list. | Force public visibility in the GitHub request and check visibility again at the persistence boundary. The current model stores no visibility field, so identifying and removing private records already imported requires a data remediation step. If private repositories are to be supported, use user-scoped access-controlled storage instead of globally shared records. | Confirmed from source; GitHub documentation confirms the organization endpoint's `all` default. No actual incident is established. Regression test: two users, one private and one public repo, token-authenticated first sync, then verify the second user's read path. |
| F-02 | Defective | **Medium** | `app/app/Http/Requests/StoreTargetRequest.php:19–27`; `app/app/Repositories/GithubTargetRepository.php:51–61`; `app/app/Repositories/GithubRepositoryRepository.php:92–111`; migrations `0001_01_01_000004...:21`, `0001_01_01_000006...:26` | Two users submit the same GitHub organization with different casing. Input format is validated but not normalized; local uniqueness on `(type, name)` uses exact character matching. GitHub treats organization names as case-insensitive. Two targets can therefore be created, while the upsert using globally unique `external_id` moves repositories to the most recently used target. Users subscribed to the earlier target can see their repository list disappear. | Store the GitHub identifier in a canonical form (for example, case-folded login), and keep display casing separately if needed. The database unique constraint should protect the canonical key as well. | Confirmed from source; PostgreSQL case-sensitive unique behavior was not executed. Regression test with two differently cased organization names, two users and the same external repository ID. |
| F-03 | Works, but needs improvement | **Medium** | `app/app/Repositories/GithubTargetRepository.php:27–48`; `app/app/Http/Controllers/TargetController.php:26–56, 97–132`; `app/resources/js/components/TargetList.vue:52–117` | Targets are paginated to five records, but each target's entire repository set is loaded and included in the response. The collapsed UI does not reduce backend query work or network payload. A large target can increase `/targets` response size and memory use. | Fetch repository details only when a target is expanded, ideally from a target-scoped, server-paginated endpoint. Measure with a representative large target before adding caching or more complex infrastructure. | The limit follows from the query and serialization path; no latency or memory benchmark was run. |
| F-04 | Defective | **Medium** | Workflow paths: `app/.github/workflows/lint.yml`, `app/.github/workflows/tests.yml`; workflow contents: `:3–11, 14–40` and `:3–11, 18–56`; lockfile: `app/package-lock.json`; PHP/Node manifests: `app/composer.json`, `app/package.json` | GitHub loads workflows only from the repository-root `.github/workflows/`, but both files are under `app/.github/workflows/`, so they are not loaded. Moving them alone would still not match the default `master` branch because the triggers list only `main` and `develop`. If those issues were fixed, jobs would run from the repository root by default, while `composer.json`, `package.json`, `package-lock.json` and Laravel's `database/` directory are under `app/`; install, Artisan and database steps would fail from the wrong directory. The npm cache also expects a root lockfile with the current settings. The lint commands mutate files, and the workflow requests `contents: write` despite having no active commit step. | Move workflows to root `.github/workflows/`; add `master` (or use reliable default-branch triggers); run Composer/npm/Artisan/PHPUnit commands with `working-directory: app` or `cd app`; set npm `cache-dependency-path: app/package-lock.json`. Remove unnecessary `contents: write`, and use Pint `--test`, Prettier `--check` and ESLint without fix mode. Workflow placement rule: [GitHub Actions workflows](https://docs.github.com/en/actions/concepts/workflows-and-actions/workflows). | Confirmed from the repository tree, workflow YAML and GitHub default-branch metadata; no workflow was available to run and CI was not executed. |
| F-05 | Works, but needs improvement | **Low** | `app/app/Services/TargetRepositoriesSynchronizer.php:24–72`; `app/app/Jobs/SyncGithubTarget.php:81–95`; `app/tests/Unit/Services/TargetRepositoriesSynchronizerTest.php:24–146`; `app/tests/Feature/SyncGithubTargetJobTest.php:93–127` | The synchronizer has two parallel sync methods. The older `sync()` sets target `Syncing`/`Failed` states and `last_error`, but the queue job calls only `syncForOperation()`, which writes successful state only; failures go to the operation row. The unit test covers only the unused `sync()` path, while the feature test confirms the different behavior. Similarly, `GithubTargetRepository::latestForUser()` is only called by its test, and `AddGithubTargetResult::saved()`, `notFound()` and `wasSaved()` are unused remnants. | Remove the unused sync path and its test, or consolidate it with the operation-based flow; remove demonstrably unused methods. Document whether target status represents the last successful data refresh or the latest operation. | Confirmed by static call-site search and test assertions; no runtime tests were run. |

### F-01 – evidence and data path

```php
// GithubClient.php:37–50
$endpoint = $type === 'org'
    ? "/orgs/{$target}/repos"
    : "/users/{$target}/repos";

$response = $this->http()->get($endpoint, [
    'per_page' => $this->perPage,
    'page'     => $page,
]);
```

`AddGithubTargetForUser::add()` makes this request with `$user->github_token` (line 26), then within a transaction finds a target globally by type/name, attaches the user and saves every returned repository (lines 31–38). The repository upsert looks up the globally unique `external_id` and writes the current `github_target_id` (lines 92–111). The migration makes `external_id` globally unique; the table has no `visibility` or `private` field. `TargetController::targetPaginator()` loads and sends target repositories to every user subscribed to that target.

GitHub's official documentation says `type` defaults to `all` for `List organization repositories`, and requests are limited to public resources only when unauthenticated or without the required permissions. The project README, by contrast, requires syncing public repositories. Source: [GitHub REST API – List organization repositories](https://docs.github.com/en/rest/repos/repos#list-organization-repositories).

### F-02 – evidence and expected data consistency

`StoreTargetRequest` accepts uppercase letters but does not normalize the value; target `firstOrCreate` and the composite unique constraint use the submitted casing unchanged. GitHub documents organization names as case-insensitive on this endpoint. Therefore, two local forms that resolve to one GitHub organization can become distinct targets. Because external repository IDs are globally unique, the latest upsert can move their data to one target.

Source: [GitHub REST API – List organization repositories](https://docs.github.com/en/rest/repos/repos#list-organization-repositories), parameter table (`org` name is not case sensitive).

### F-04 – CI workflow assessment

The repository tree places both workflows under `app/.github/workflows/`; GitHub processes workflow files from the repository-root `.github/workflows/` directory, so neither workflow is currently loaded. Moving them still requires adding `master`, since only `develop` and `main` are listed. Once activated, each job would start at the repository root, but the Composer/npm/Laravel project is under `app/`: dependency install, `touch database/database.sqlite`, `php artisan`, PHPUnit and lint commands would run from the wrong directory. npm caching also needs the explicit `app/package-lock.json` path. Finally, the linters currently run in fix mode, and the job requests `contents: write` although its auto-commit step is commented out.

## Remediation order

1. **F-01 – immediately:** restrict organization retrieval to public repositories, enforce visibility at persistence, and inspect existing organization repository metadata already in the database. Treat access as separated only when private data cannot enter any shared-target read path.
2. **F-02 – next:** normalize target identity before GitHub requests and local uniqueness checks; test mixed-case repeated/concurrent additions.
3. **F-04 – next:** move workflows to repository root, set `app/` as their working directory and cache path, add the `master` trigger, reduce permissions and use check-only lint commands.
4. **F-05 – maintenance pass:** consolidate or remove unused sync code and align tests with the actual queue path.
5. **F-03 – before scaling:** move to on-demand, per-target repository retrieval if observed target sizes justify the current response structure's cost.

## Verification limits

This audit reviewed the source at the stated commit. I had no running application, database, queue worker or GitHub token, so I make no claim of a real incident, measured performance, successful test/build run or production configuration. The README says a production environment was intentionally not created; I have not reported missing production hardening as a defect. Starter-kit UI primitives were not all inspected line by line; the pages and components handling data, access and sync were reviewed.

