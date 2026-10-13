# Database Plan

## Goal

The application should store public GitHub repositories locally and allow multiple users to follow the same GitHub user or organization without duplicating the target or repository data.

The important modeling decision is that a GitHub user or organization is a shared external resource. If fifty users add `laravel` as an organization target, the application should not create fifty separate target rows or synchronize the same public repository list fifty times.

## Proposed Model

### `github_targets`

Stores one row per GitHub user or organization.

Suggested columns:

- `id`
- `name`
- `type` - `user` or `org`
- `external_id` - GitHub user/org ID, nullable until first successful lookup
- `sync_status` - for example `pending`, `syncing`, `synced`, `failed`
- `last_synced_at`
- `last_error`
- `created_at`
- `updated_at`

Suggested constraints and indexes:

- Unique index on `type, name`
- Index on `sync_status`
- Index on `last_synced_at`

Reasoning:

- `type + name` uniquely identifies the GitHub target inside this app.
- `sync_status` and `last_error` currently support the synchronous implementation. They will be removed when Add and Sync now both use user-owned operations.
- `last_synced_at` helps find stale targets for scheduled synchronization later.

### `github_target_user`

Connects users to the GitHub targets they added.

Suggested columns:

- `id`
- `user_id`
- `github_target_id`
- `created_at`
- `updated_at`

Suggested constraints and indexes:

- Foreign key on `user_id`
- Foreign key on `github_target_id`
- Unique index on `user_id, github_target_id`

Reasoning:

- This prevents duplicate subscriptions for the same user.
- Multiple users can follow the same target.
- User-specific UI preferences can be added here later without duplicating the target.

Possible future columns:

- `custom_label`
- `is_pinned`
- `last_viewed_at`

### `github_repositories`

Stores repositories returned by GitHub for a target.

Suggested columns:

- `id`
- `github_target_id`
- `external_id` - GitHub repository ID
- `name`
- `full_name`
- `description`
- `html_url`
- `language`
- `stargazers_count`
- `open_issues_count`
- `archived`
- `external_updated_at`
- `created_at`
- `updated_at`

Suggested constraints and indexes:

- Foreign key on `github_target_id`
- Unique index on `external_id`
- Index on `github_target_id`
- Index on `language`
- Index on `archived`
- Index on `stargazers_count`
- Index on `external_updated_at`

Reasoning:

- GitHub repository IDs are globally unique, so `external_id` can prevent duplicates.
- Most repository browsing screens will filter by target, language, archived status, stars, or external update time.
- Repositories belong to the shared GitHub target, not to each user.

## Synchronization Ownership

Synchronization should run against `github_targets`, not against individual users.

If User A and User B both follow the same GitHub organization, and User A triggers a sync, the shared target is synchronized. User B then sees the same refreshed repository data.

This is intentional because public GitHub repository data is not user-specific.

The last successful synchronization time belongs on `github_targets`, because the stored repository data is shared. An individual attempt's status and error belong to its requesting user: a token failure must not become another user's error.

The current synchronous paths still use `github_targets.sync_status` and `github_targets.last_error`. They remain during this first schema step and will be removed after both Add and Sync now switch to operations. `last_synced_at` remains on the target.

## User-Owned Synchronization Operations

Implemented table: `github_sync_operations`.

Columns:

- `id` - operation identifier; the future job and status endpoint use this ID
- `user_id` - required requesting user; their stored GitHub token is read at execution time, not copied into this table
- `github_target_id` - nullable, allowing an Add operation before a target exists
- `target_name` - requested GitHub username or organization name
- `target_type` - `user` or `org`
- `status` - `queued` (default), `running`, `succeeded`, or `failed`
- `error` - nullable, intended for a safe translated error key, not a raw HTTP exception or token
- `started_at` - nullable until execution begins
- `finished_at` - nullable until the operation succeeds or fails
- `created_at`
- `updated_at`

Constraints and indexes:

- Required foreign key on `user_id`, cascading on user deletion: operations belong to that user.
- Nullable foreign key on `github_target_id`, set to null on target deletion: the attempt retains its requested name/type and result.
- Index on `user_id, created_at` for a user's operation history.
- Index on `github_target_id` for target-related attempts and foreign-key lookups.
- Index on `status` for pending/running operation queries.
- No unique constraint on target or user/target: repeated attempts must be separate rows. Concurrent-request protection belongs to the later queue implementation.

Relationships:

- `User::githubSyncOperations()` and `GithubTarget::githubSyncOperations()` are one-to-many.
- `GithubSyncOperation::user()` and `GithubSyncOperation::githubTarget()` are inverse relationships.
- `target_type` and `status` use enums; execution timestamps use datetime casts.

Planned flow:

- Add saves an operation and dispatches a job after the operation transaction commits. It does not yet create a target or subscription.
- The job loads the requester and their current token, retrieves GitHub data, then saves the shared target, repositories and user subscription on success. It links the operation to the saved target.
- A failed Add keeps the operation and its error without creating a new target or subscription.
- Sync now creates an operation linked to an existing target, after checking the requester's subscription. Failure retains the previously stored repositories.
- The future status endpoint must restrict reads to the authenticated user's operations. Model relationships alone do not provide HTTP authorization.
- This step adds storage and relationships only. No controller, job, worker or frontend has been switched yet.

## Future Alternative: Partial Duplication for User-Specific Repository Membership

The baseline plan stores repository data globally. This is efficient, but every user sees the latest synchronized repository metadata for a shared target.

If the product later needs each user to keep their own last-synced repository list without duplicating full repository metadata, add a lightweight membership table.

Suggested table: `user_github_target_repositories`

Suggested columns:

- `id`
- `user_github_target_id`
- `github_repository_id`
- `seen_at`
- `missing_since`
- `created_at`
- `updated_at`

Reasoning:

- `github_repositories` still stores shared repository metadata once.
- `user_github_target_repositories` stores which repositories belonged to a user's last synced view of a target.
- If User A synchronizes a target today, only User A's membership rows change.
- If User B last synchronized two weeks ago, User B can still keep that older repository membership.

Trade-off:

- This avoids duplicating full repository rows for every user.
- It does not preserve historical repository metadata such as old star counts or old descriptions.
- If exact historical metadata is required, use full sync snapshots instead, for example a separate snapshot table plus `snapshot_repositories`.

## Implementation Order

1. Shared targets, subscriptions and repositories: implemented.
2. User-owned synchronization operation storage and model: implemented; existing synchronous behavior remains.
3. Introduce the job and running Redis worker, then switch Sync now together with its user-specific status UI.
4. Switch Add using the same operation tracking; only save target/subscription after successful retrieval.
5. Remove the legacy target status/error fields after both flows have switched.

## Trade-Offs

This model is slightly more complex than storing `user_id` directly on a target row, but it avoids duplicated targets and duplicated repository data.

The trade-off is worth it because GitHub users, organizations, and repositories are shared public resources. User ownership should be modeled as a subscription to a shared resource, not as a duplicate copy of the resource itself.
