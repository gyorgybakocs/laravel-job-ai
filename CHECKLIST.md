# README Requirements Checklist

Source: [README.md](README.md). Status reflects the current application, not the previous implementation.

`[x]` = completed. `[ ]` = not completed or not yet verified. Suggested fields are listed separately from mandatory behavior. Optional extensions and discussion topics are not baseline requirements.

## Application foundation

- [x] Start from a fresh Laravel application using the official Laravel Vue starter kit with Inertia.js and Vue.
- [ ] Synchronize public GitHub repositories into a local database instead of proxying GitHub responses.

## Baseline

### 1. Synchronization target

- [ ] Allow a user to add a GitHub username or organization and save it locally with basic validation.
- [ ] Store enough information to support the synchronization flow.

Suggested target fields:

- [ ] Target name.
- [ ] Target type, if users and organizations are handled differently.
- [ ] Synchronization status.
- [ ] Last successful synchronization time.
- [ ] Most recent synchronization error, if applicable.

### 2. GitHub integration boundary

- [ ] Retrieve public repositories through a dedicated integration class, client, or service.

### 3. Local repository storage

- [ ] Store a useful subset of repository data locally.
- [ ] Create new repositories during synchronization.
- [ ] Update repositories already stored locally.
- [ ] Prevent duplicates with an appropriate database constraint.
- [ ] Add a short note about handling repositories that disappear from GitHub. Full implementation is not required.

Suggested repository fields:

- [ ] External repository ID.
- [ ] Repository name and full name.
- [ ] Description and repository URL.
- [ ] Star count and open issue count.
- [ ] Archived status.
- [ ] External last-updated time.

### 4. Queued synchronization

- [ ] Run synchronization through a Laravel queue job so the user does not wait for GitHub to respond.

Short comments or notes are sufficient for the following; full implementation is not required:

- [ ] Duplicate synchronization requests.
- [ ] Job retries and failed jobs.
- [ ] Timeouts.
- [ ] Overlapping jobs.

### 5. Inertia.js and Vue UI

- [ ] View synchronization targets.
- [ ] Add a synchronization target.
- [ ] Trigger synchronization.
- [ ] View synchronized repositories.
- [ ] View each target's synchronization status and most recent error, if one occurred.
- [ ] Search, filter, or sort repositories in at least two useful ways.
- [ ] Display a useful subset of repository information, such as name, description, language, star count, open issue count, and external last-updated time.
- [ ] Provide a simple, clear interface without spending much time on styling.

### 6. Basic automated testing

- [ ] Include a few completed unit and integration tests for the feature.
- [ ] Cover creating a synchronization target.
- [ ] Cover synchronizing repositories with a faked or mocked GitHub response.
- [ ] Cover preventing or avoiding duplicate repository records.
- [ ] Fake or mock external HTTP calls in tests.

Browser E2E tests are not required. Clearly named placeholder test methods for additional scenarios are allowed, not required. The README suggests API failures, pagination, retries, scheduled sync, missing repositories, cache invalidation, and invalid targets as possible scenarios.

### 7. AI usage documentation

- [ ] Submit `AI_USAGE.md` containing the tools used.
- [ ] Include important prompts or instructions.
- [ ] Identify areas that were substantially AI-generated.
- [ ] Include examples of suggestions changed or rejected.
- [ ] Explain how generated code was validated.

If no AI was used, the file should state that instead. Be prepared to explain all submitted code, including AI-generated code.

## Technical expectations

### Third-party integration structure

- [ ] Keep controllers, synchronization logic, queue jobs, GitHub API communication, and mapping external data into local models reasonably separated.

### Database design

- [ ] Use Laravel migrations for the feature's database schema.
- [ ] Include appropriate foreign keys.
- [ ] Include appropriate unique constraints.
- [ ] Add short notes about columns worth indexing and why.

SQLite is permitted, not required.

### Error handling

- [ ] Handle GitHub failures without showing raw exceptions to the user.
- [ ] Leave enough information to identify which synchronization failed and why.

## Optional extensions — only after the baseline works

- [ ] Scheduled synchronization: synchronize existing targets at a chosen fixed interval and briefly explain the interval.
- [ ] REST API: expose target and repository data with consistent responses and appropriate status codes.
- [ ] GitHub API pagination: handle all repository pages and explain how synchronization processes them.
- [ ] README full-text search: store repository README content and support searching that content.
- [ ] Simple multitenancy: scope targets and repositories to users so each user sees only their own data.
- [ ] Reconciliation: define or implement how repositories no longer returned by GitHub are treated.
- [ ] Reliability and tests: improve error handling, logging, retries, overlap protection, or automated coverage.

The README does not require completing all optional extensions.

## Hand-in and timebox

- [ ] Create a public Git repository containing the solution.
- [ ] Send the repository link when ready to submit.
- [ ] Spend no more than eight hours in total; stop when the limit is reached, even if something remains unfinished.
- [ ] Submit within two weeks of receiving the task.

Add a short submission note with:

- [ ] Approximate time spent.
- [ ] Completed parts.
- [ ] Incomplete parts.
- [ ] What would be implemented next and why.
- [ ] Important compromises or shortcuts.

## PREPARE — follow-up discussion

These are preparation tasks for the verbal code walkthrough, not features to implement. Check them when prepared to discuss the topic. They do not indicate implementation status.

- [ ] Prepare to explain synchronization flow, integration structure, database constraints, and index choices.
- [ ] Prepare to discuss queue behavior: duplicate requests, overlapping jobs, retries, timeouts, and failed jobs.
- [ ] Prepare to discuss GitHub rate limits and API pagination.
- [ ] Prepare to discuss transaction boundaries and cache invalidation.
- [ ] Prepare to discuss API authentication and authorization.
- [ ] Prepare to discuss queue worker deployment and monitoring failed synchronizations.
- [ ] Prepare to explain the testing approach and planned tests.
- [ ] Prepare to explain weaknesses, shortcuts, unfinished parts, and AI-generated code.

- [ ] Prepare to demonstrate the app, make a small code change, or debug a realistic failure scenario.
