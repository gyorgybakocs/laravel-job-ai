# Submission summary and known limitations

## Completed work

The application uses the official Laravel Vue starter kit with Inertia.js and TypeScript. The local development environment includes Docker, PostgreSQL, Redis and Makefile commands for setup, builds, migrations, tests and HTML test coverage reports.

Users can save their GitHub credentials in their profile; tokens are encrypted in storage. They can add GitHub users or organizations as synchronization targets, view their targets and trigger manual synchronization. Both adding a target and synchronizing an existing target use a Laravel queue job. The application records the initiating user, operation status, errors and timestamps, and the frontend refreshes while operations are active. Existing repository data remains available during synchronization.

Repository metadata is stored locally, with foreign keys and unique constraints. Synchronization creates or updates repository records and processes GitHub API pagination. The repository browser supports server-side pagination, search, filters and sorting. Target subscriptions and repository queries are scoped to the authenticated user.

Unit and integration tests cover target creation, synchronization, duplicate prevention, error handling, access control and related behavior. External HTTP requests are faked or mocked, and data providers cover variations of the same behavior. Tests were reported as passing during implementation. The final source audit did not execute tests or builds independently.

The intended scope is public GitHub repositories. The final audit identified a visibility enforcement defect described below, so public-only synchronization is not yet fully guaranteed.

## Incomplete work

- Full separation of each user's synchronized data and refresh history.
- Scheduled synchronization.
- A standalone REST API.
- Repository README storage and full-text search.
- Reconciliation of repositories that disappear from GitHub or stop being returned by its API.
- Production deployment configuration and hardening.
- Dedicated application error pages and centralized logging and monitoring.

## What I would implement next, and why

### 1. Correct the confirmed security and data-consistency defects

First, enforce public repository visibility in both the GitHub request and the persistence boundary. Check existing imported data for private repository metadata and remove it if necessary. Add a regression test proving that a token with private organization access cannot expose private metadata to another subscriber.

Next, normalize GitHub target identities before storage and uniqueness checks. GitHub treats organization names as case-insensitive; the local identity must do the same to avoid duplicate targets and repository records moving between them.

### 2. Separate synchronization results by user without duplicating all data

Currently, targets and repository records are shared. When one subscriber synchronizes a target, the repository data changes for every subscriber. Operation ownership and timestamps are already recorded per user, but the target's successful synchronization timestamp and repository metadata remain shared.

The next design should preserve shared target and repository identities while allowing each user to retain their own synchronization result. Simply adding another user relationship does not isolate mutable repository metadata. A possible approach is shared, immutable versions of repository metadata, with each user's synchronization result referencing the versions they received. Identical versions could be reused rather than copying every target and repository for every user. The storage and retention policy would need refinement before implementation.

### 3. Add scheduled synchronization after separation

Once refresh results are separated, users could choose when their targets synchronize. Laravel's scheduler could evaluate which user schedules are due and dispatch the existing queue jobs. A single cron entry would invoke the scheduler; separate operating-system cron entries for individual users would not be needed. The design should account for user time zones, overlapping requests and GitHub rate limits.

### 4. Introduce a REST API when there is a consumer

A standalone REST API would be useful when a mobile application or another independent client needs the data. I would introduce it when that requirement exists. Personally, I would have started with a separate frontend and a Laravel REST API backend, but this task explicitly required the official Vue/Inertia starter kit.

## Compromises and known limitations

This is a development system. I did not create a production-ready deployment. The environment was reused from similar projects to reduce setup time.

User scoping controls which targets users follow and read, but it does not provide independent snapshots of shared repository data. This is the main remaining design limitation.

There are no dedicated application error pages. Synchronization failures are recorded and basic logging exists, but there is no complete centralized observability setup. For a deployed system, I would consider ELK for centralized log collection and analysis, with appropriate secret redaction and retention rules.

The final source audit identified the following issues; it did not establish an actual security incident or measure performance:

| Area | Finding | Consequence / next action |
|---|---|---|
| Repository visibility | Organization requests do not explicitly restrict results to public repositories, and persistence does not reject private results. | A privileged token can import private metadata into shared records. Enforce visibility and review existing data. |
| Target identity | Target names are not normalized for case-insensitive GitHub identity. | Differently cased names can create separate targets; repository upserts can move records between them. Normalize identity and protect it with database uniqueness. |
| Target-page payload | Every repository belonging to a displayed target is loaded, even when the UI section is collapsed. | Large targets can increase response size and memory use. Fetch repositories on demand with target-specific pagination. |
| GitHub Actions | Starter workflows are under the application directory, with branch triggers and working-directory assumptions that do not match this repository. | Automated GitHub checks are not correctly connected. Fix workflow placement, triggers, paths and permissions, and use check-only lint commands. |
| Maintenance and tests | Older synchronization code and some unused methods remain alongside the operation-based flow. Some tests exercise the older path. | Remove confirmed unused code and align tests with the active queue flow and clearly defined status semantics. |

The default PHPUnit configuration uses SQLite, whereas development uses PostgreSQL. Passing tests under the default configuration alone do not establish PostgreSQL-specific locking or concurrent behavior. A production verification plan should also exercise the real database and Redis worker.

## Approximate time spent

| Activity | Approximate time |
|---|---:|
| Adapting the existing development environment | 1 hour |
| Planning and design | 2 hours |
| Implementation across pull requests | 8 hours |
| Final audit | 1 hour |
| **Total** | **12 hours** |

The environment was copied and adapted from previous similar projects. During the previous year, I also worked in an architect role, so the infrastructure approach was already familiar. Individual implementation pull requests generally took between half an hour and one hour. Documentation was written throughout planning and implementation and is included in those estimates.

The approximately 12-hour total exceeds the eight-hour timebox requested in the task; this is reported explicitly rather than presented as compliance with that limit.
