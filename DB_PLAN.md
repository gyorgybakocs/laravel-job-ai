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
- Sync status is global because the public GitHub repository list is global.
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

The global sync fields belong on `github_targets`:

- `sync_status`
- `last_synced_at`
- `last_error`

This keeps the baseline implementation simple and avoids duplicate API calls.

## Future: User-Specific Sync Request Status

If the application later needs to show who requested a sync, whether a specific user's request is still waiting, or a detailed sync history, add a separate sync run table.

Suggested table: `github_target_sync_runs`

Suggested columns:

- `id`
- `github_target_id`
- `requested_by_user_id` - nullable for scheduled/system syncs
- `status` - for example `queued`, `running`, `succeeded`, `failed`
- `started_at`
- `finished_at`
- `error`
- `created_at`
- `updated_at`

Suggested constraints and indexes:

- Foreign key on `github_target_id`
- Nullable foreign key on `requested_by_user_id`
- Index on `github_target_id, created_at`
- Index on `requested_by_user_id, created_at`
- Index on `status`

Reasoning:

- `github_targets.sync_status` represents the current global state of the shared target.
- `github_target_sync_runs` represents individual sync attempts and their requester.
- Scheduled syncs can create rows with `requested_by_user_id = null`.
- Manual syncs can store the user who requested them.
- This avoids mixing current target state with historical or user-specific workflow state.

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
- If exact historical metadata is required, use full sync snapshots instead, for example `github_target_sync_runs` plus `sync_run_repositories`.

## Implementation Order

1. Create `github_targets`.
2. Create `github_target_user`.
3. Create `github_repositories`.
4. Add Eloquent models and relationships.
5. Change the current target endpoint to find or create a shared target and attach it to the authenticated user.
6. Keep repository synchronization separate from target creation.
7. Add queued synchronization after the schema and relationships are stable.

## Trade-Offs

This model is slightly more complex than storing `user_id` directly on a target row, but it avoids duplicated targets and duplicated repository data.

The trade-off is worth it because GitHub users, organizations, and repositories are shared public resources. User ownership should be modeled as a subscription to a shared resource, not as a duplicate copy of the resource itself.
