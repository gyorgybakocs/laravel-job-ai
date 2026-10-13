# Implementation Plan

The work is divided into environment setup and eleven incremental implementation steps. Each step should deliver a working, verifiable outcome and provide the foundation for the next step.

## Shared acceptance criteria

- Build on the official Laravel Vue starter kit and use TypeScript on the frontend.
- Use existing UI components and organize related application UI elements into application components.
- Obtain user-facing text from translations, URLs from named routes, and environment-specific settings from configuration.
- Add or update behavior tests within the relevant step. Use data providers for variations of the same behavior and fake GitHub responses.
- Verify database-dependent behavior against PostgreSQL. Run TypeScript checks and an asset build when changing the frontend.

## 00 — Development environment

**Story:** As a developer, I want a consistently runnable environment so that I can use the project's services and tools reproducibly.

**Goal:** Set up a Docker-based Laravel environment with PHP, Nginx, PostgreSQL, and Redis, and run Composer and Node tasks in containers. Expose build, installation, and startup tasks through the Makefile.

**Why:** Running the project should not depend on the PHP, Node, or database versions installed on a developer's machine. Include the PHP Redis extension in the base image from the start.

**Acceptance criteria:**

- The official Laravel Vue starter kit can be installed, and the application and database are accessible.
- Image builds, dependency installation, migrations, asset builds, and environment startup can run separately.
- Versions, ports, and connection settings come from environment configuration; secrets are excluded from the repository.

**Implementation outcome:**

- `make provision`, `make ci`, and `make upd` completed successfully. The official Vue starter kit was installed, the three initial migrations completed, and the Vite production asset build passed.
- All application containers started; PostgreSQL passed its health check.
- Compose reported unset PostgreSQL variables during interpolation, and Docker reported missing default image arguments. These warnings did not prevent image builds, migrations, or startup.
- The initial npm audit reported 42 vulnerabilities (2 low, 8 moderate, 30 high, 2 critical). After applying fixes, 10 remain (4 moderate, 6 high), with no critical vulnerabilities reported. For this development-only environment, these remaining findings are accepted as non-blocking and further remediation is outside this task's scope.
- Dependency deprecation and outdated Browserslist data warnings were also reported. Automated tests and a separate TypeScript check were not part of the supplied execution log.

## 01 — Frontend foundation and navigation

**Story:** As a user, I want a simple home page and clear navigation so that I can access the application's features.

**Goal:** Remove the starter kit's demonstration content and establish application navigation.

**Why:** The interface should support the features being built. Keep navigation in a dedicated application component and use UI kit components for its presentation.

**Acceptance criteria:**

- The home page displays application-specific content.
- Navigation is encapsulated in a reusable component that manages menu items and active states and is used by the application layout.
- Navigation for login, registration, and the profile continues to work.

**Implementation outcome:**

- The application-specific home page and shared navigation are in place. Authentication and email-verification redirects now return to the home page.
- The unused starter welcome and dashboard pages and their obsolete example tests were removed. No references to the removed pages or dashboard route remain in application code or tests.
- Home-page content and authentication navigation labels are supplied through translations. A test configuration override aligns Inertia's page lookup with the starter kit's lowercase pages directory.
- Frontend type compatibility fixes were verified, including numeric values for the two explicitly typed authentication-link tabindex properties.
- Verification passed: 24 automated tests with 69 assertions against an isolated PostgreSQL database, TypeScript checking, the Vite production asset build, and Git diff whitespace checking. The user also confirmed that the frontend shows no errors.
- One minor localization limitation remains: the profile icon's accessibility label is still a hardcoded English string. It does not block the verified navigation flow.

## 02 — GitHub profile settings

**Story:** As an authenticated user, I want to provide my GitHub details so that the application can make requests using my token.

**Goal:** Add a GitHub menu item and settings page to the profile navigation, with persistence for the GitHub username and token.

**Why:** GitHub credentials can differ between users and must be stored securely for later requests.

**Acceptance criteria:**

- The GitHub menu item is accessible from the profile settings navigation.
- Users can modify only their own GitHub settings.
- The token is encrypted in the database and excluded from user data sent to the frontend.

**Implementation outcome:**

- GitHub settings are accessible from the profile navigation. Authenticated users can save their own GitHub username and token, with backend validation and translated feedback.
- The token is encrypted in the database and excluded from serialized user data and settings page responses. Leaving the token field empty preserves the existing token.
- Manual verification: the user confirmed that GitHub credentials are successfully saved in the application.

## 03 — GitHub client and initial request

**Story:** As a user, I want to check a GitHub username or organization so that I can determine whether its public repositories can be retrieved.

**Goal:** Introduce a dedicated GitHub client and call it from the application, without storing targets or repositories locally at this stage.

**Why:** Verify communication with the external system and its failure cases before building database persistence around it.

**Acceptance criteria:**

- The client calls the appropriate GitHub endpoint for the target type and handles repository pagination.
- Requests work with or without a token; a token is cleared after use and cannot carry over to another user's request.
- Missing targets, invalid tokens, access restrictions or rate limits, and connection or server failures produce appropriate, safe feedback.

**Implementation outcome:**

- Authenticated users can check a GitHub username or organization through the target form. The request uses a dedicated client with the current user's token, or without authentication when no token is provided. Each lookup receives a separate client instance, preventing credentials from carrying over between users.
- The client uses the appropriate user or organization endpoint. It retrieves consecutive repository pages until a page contains fewer items than the configured page size and combines the results.
- Input validation runs before GitHub retrieval. Missing targets, invalid tokens, access restrictions or rate limits, and connection or server failures produce translated feedback without exposing raw exceptions.
- Verification: the user confirmed that the workflow works and the tests pass. Unit and feature tests use faked HTTP responses and data providers for token, target type, pagination, validation, and failure scenarios.
- This step verifies GitHub retrieval only; targets and repositories are not yet persisted locally, and synchronization is not queued.

## 04 — Target and repository data model

**Story:** As a user, I want targets and repositories stored locally so that I can browse them from the application's database.

**Goal:** Establish tables, models, and relationships for targets, repositories, and user subscriptions to targets.

**Why:** Multiple users may follow the same target. Store shared GitHub data once and represent each user's subscription separately.

**Acceptance criteria:**

- Migrations create the data model and required foreign keys.
- Unique constraints prevent duplicate targets, user subscriptions, and external repository IDs.
- The model stores the data needed for repository presentation and target synchronization information.

**Implementation outcome:**

- Shared targets, repositories, and user subscriptions are represented by migrations and models with relationships, foreign keys, unique constraints, indexes, and field casts. The schema and indexing rationale are documented in the database plan.
- Verification: the user confirmed that migrations completed and tests passed. Model tests cover shared subscriptions, relationship queries, duplicate subscription prevention, target name/type uniqueness, and enum and repository field casts.
- This step establishes the schema; connecting GitHub retrieval to persistence is handled in the following step.

## 05 — Add a target and persist its repositories

**Story:** As a user, I want to add a GitHub target so that its public repositories become available in the application.

**Goal:** Connect the Add action to GitHub retrieval and persistence of the target, repositories, and user subscription. At this stage, the workflow runs synchronously within the HTTP request.

**Why:** Make the complete retrieval and persistence workflow operational before introducing the queue.

**Acceptance criteria:**

- A valid target and successful GitHub request result in locally stored target and repository data, with the target linked to the requesting user.
- A failed addition leaves no partially persisted data.
- Repeated additions or subscriptions by another user do not duplicate shared data.

**Implementation outcome:**

- Adding a target retrieves its public repositories and persists the shared target, repository data, and requesting user's subscription in a transaction. Failed additions roll back persistence and return safe, translated feedback.
- Repository records are created or updated by their external GitHub ID. Repeated additions and subscriptions by different users do not duplicate shared targets or repositories.
- Successful synchronization records the target status and last successful synchronization time. The frontend displays a simple list containing only the requesting user's followed targets.
- The obsolete client factory and its test were removed; the synchronization service sets the current user's token on the client and clears it after the request.
- Verification: the user confirmed that the frontend works, targets and repositories are stored in the database, and tests and coverage checks pass. Tests cover successful and failed additions, repository updates, duplicate prevention, and user-scoped target listing, using faked GitHub responses and data providers.
- Synchronization still runs within the HTTP request. Repository browsing, manual refresh controls, filtering, and queued execution are introduced in subsequent steps.

## 06 — Target interface and manual synchronization

**Story:** As a user, I want to view my targets and their repositories and manually refresh their data.

**Goal:** Provide a target list, an Add interface, repository presentation, Sync now, and subscription removal. Synchronization still runs within the request.

**Why:** Make retrieval and storage capabilities accessible through a usable interface.

**Acceptance criteria:**

- User and organization targets appear in separate, paginated lists.
- Repositories, synchronization status, and the last successful synchronization time are visible.
- Sync now updates stored repositories; removing a target removes only the requesting user's subscription.
- Users cannot perform target actions without the required subscription.

**Implementation outcome:**

- User and organization targets appear in separate, independently paginated lists. Expandable target rows show stored repositories with links, language, stars, open issues, and external update dates.
- The interface displays synchronization status, the last successful synchronization date, and a safe error message when the target has a recorded failure.
- Sync now refreshes stored repositories through the existing synchronization service. Subscription checks protect both synchronization and removal; removing a target preserves shared target and repository data for other users.
- The interface uses reusable target-list, pagination, and date-formatting components with translated labels. Controls are disabled while a request is being processed.
- Verification: all three new source files and eleven modified source files match the supplied implementation snapshot; no files were deleted in this step. Feature tests cover manual synchronization, subscription authorization, removal of subscriptions, independent pagination, and repository presentation.
- Synchronization still runs within the HTTP request. The separate repository browser with filters and background execution are introduced in later steps.

## 07 — User-scoped repositories and filters

**Story:** As a user, I want to search, filter, and sort repositories belonging to my followed targets so that I can find relevant repositories.

**Goal:** Provide a user-scoped, paginated repository list with search, language and archived-status filters, and sorting options.

**Why:** A plain list becomes difficult to navigate as the number of targets and repositories grows.

**Acceptance criteria:**

- The list and filter data come exclusively from targets followed by the requesting user.
- Search, filtering, and sorting happen in the database query before pagination.
- Filters can be combined and reset, and remain applied when changing pages.

**Implementation outcome:**

- A dedicated repository page is accessible through the application navigation. It lists locally stored repositories belonging to targets followed by the authenticated user, with target details and repository metadata.
- Users can search by target name or repository description, filter by archived status and multiple languages, and sort by target name, repository name, update date, or star count.
- Search, filtering, and sorting are applied in the database before pagination. Query parameters preserve the selected options across pages; filters can be combined and reset. Language options and their counts are also scoped to the user's followed targets.
- The frontend uses reusable search, filter, language-selection, sorting, and repository-list components, with TypeScript types, translated labels, and backend validation of query parameters.
- Review confirmed that all twelve new source files and seven modified source files match the supplied implementation, with no deletions. Tests cover user isolation, search, sorting, combined filters, language counts, pagination, empty states, guest access, and invalid filter values, including data-provider variations. Tests and builds were not rerun during this review.
- The user confirmed that unrelated authentication-page and Inertia configuration differences introduced during copying were resolved.

## 08 — Synchronization operation data model

**Story:** As a user, I want to see the result of my synchronization request so that another user's failure is not presented as my own.

**Goal:** Create an operation table and model recording the initiating user, target name and type, optional target relationship, status, error, and timestamps.

**Why:** A target is shared data, while a request and its outcome belong to a specific user's operation. An operation for adding a target must also exist before the target itself is created.

**Acceptance criteria:**

- Queued, running, successful, and failed operations can be stored.
- Each operation belongs to a user and can exist without a target relationship.
- The migration and data model are tested; Add and Sync now retain their existing behavior at this stage.

**Implementation outcome:**

- Synchronization operations can be stored for a requesting user before a target exists, or linked to an existing shared target. Requested target name and type are retained independently of the target relationship.
- Operation statuses, errors, and execution timestamps are stored with enum and datetime casts. User and target relationships are available in both directions.
- Foreign keys delete a user's operations when the user is deleted and clear the target relationship when a target is deleted. Indexes support user history, target lookup, and status queries; repeated attempts can be stored as separate operations.
- The database plan's operation columns, constraints, indexes, relationships, and casts match the migration and model. Its later workflow changes remain planned work.
- Verification: all four new files and three modified implementation/design files match the supplied snapshot, with no deletions. The user confirmed that tests pass. Data-provider tests cover targetless operations, both target types, all statuses and timestamps, separate user errors on a shared target, parent deletion, and invalid foreign keys.
- This step adds storage only. Add and Sync now remain synchronous and do not yet create or execute these operations.

## 09 — Queue infrastructure and synchronization job integration test

**Story:** As a developer, I want to verify the actual synchronization job through the existing manual action before enabling background execution and operation tracking.

**Goal:** Prepare Redis queue configuration and a supervised worker in the application container, then connect Sync now to the actual synchronization job and validate that integration using synchronous execution.

**Why:** First verify the job's inputs, authorization checks, GitHub retrieval, persistence, and failure behavior through the existing workflow. Redis-backed background execution and user-specific operation feedback follow in the next step.

**Acceptance criteria:**

- The PHP image includes Supervisor and PCNTL. The worker runs alongside PHP-FPM in the existing container, with restart and shutdown settings.
- Redis connection settings are available in the environment templates. Local integration testing can use the synchronous queue driver, which executes the job immediately within the request.
- Sync now checks the user's subscription and dispatches the synchronization job. Add continues to call the existing addition service directly.
- The job receives target and user identifiers, reads the user's current token at execution time, and skips inaccessible or deleted records.
- Successful execution creates or updates repositories without duplicates and records the last successful synchronization time, including the time of day in the interface.
- Failed execution preserves existing repositories and the last successful synchronization time, records the failure, and propagates the exception.
- Job and dispatch behavior are tested with faked GitHub responses. Verification uses the actual synchronization job, without a separate smoke command or disposable test job.
- This step does not claim Redis-backed background execution or operation tracking is complete.

**Implementation outcome:**

- The actual synchronization job is connected to Sync now. The local configuration deliberately uses `QUEUE_CONNECTION=sync` for this integration test, so the job runs immediately rather than being consumed from Redis. Add remains synchronous and does not dispatch a job.
- The PHP image was rebuilt. PCNTL is available, and Supervisor reports PHP-FPM and the worker process running. This process check does not establish that a Redis-backed job has executed.
- Environment templates define `QUEUE_CONNECTION=redis` and `REDIS_HOST=redis` for later background execution. The corrected application environment template was verified. Template changes do not automatically replace settings in an existing application environment file.
- Tests cover current credentials, user and organization targets, repeated execution without duplicate repositories, provider failures, deleted users or targets, and removed subscriptions.
- Verification passed: 167 tests with 1,408 assertions against PostgreSQL, TypeScript checking, and the Vite production asset build. No files were deleted in this step.
- Redis-backed execution and user-specific operation tracking remain work for the next step.

## 10 — Track synchronization operations and display their outcomes

**Story:** As a user, I want to refresh an existing target's repositories in the background and see the outcome of my request.

**Goal:** Enable Redis-backed execution for the validated Sync now job, record a user-specific operation, associate job execution with it, and display its status and outcome on the frontend.

**Why:** The job integration has been validated synchronously. Move execution to the worker and provide feedback about each user's request without manual page refreshes or exposure of another user's errors.

**Acceptance criteria:**

- The HTTP request returns after recording the operation and dispatching the job; it does not call GitHub.
- The job receives stable identifiers and reads the initiating user's current token at execution time.
- Repeating the same user's active Sync now request does not dispatch another job; jobs for the same target do not overlap.
- Failed synchronization preserves existing repositories and the last successful synchronization time; the error is recorded on the user's operation.
- The frontend polls only while operations are active, refreshes the list after success, and does not expose another user's operation errors.
- Add still follows the previous synchronous workflow at this stage.

## 11 — Move Add to the queue

**Story:** As a user, I want to add a target in the background and see the request's status or error before the target exists.

**Goal:** Have Add record an operation without a target relationship and dispatch the existing synchronization job. The worker retrieves data from GitHub and, on success, persists the target and links it to the user.

**Why:** The worker should perform external retrieval for additions as well. A failed GitHub request should not create a target, while the user should still receive feedback.

**Acceptance criteria:**

- The Add HTTP request only records an operation and dispatches a job; it neither calls GitHub nor creates a target.
- The worker retrieves data first, then persists the target, repositories, and user subscription in one transaction.
- Successful operations are linked to the persisted target. Failed operations receive a safe, user-specific error without partial persistence.
- Repeated execution and shared targets do not produce duplicate targets, subscriptions, or repositories; Add and Sync now jobs for the same target use the same lock.
- The frontend displays the user's request before the target exists and automatically refreshes the target list after success.
- Successful and failed execution of the complete Add workflow is verified through a real Redis queue.
