# Database, Transactions and Concurrency Review

## Establish the engine and workload

Identify the production and test database engines and relevant versions. SQLite tests do not establish PostgreSQL/MySQL locking, type, constraint, collation or index behavior. Report the compatibility gap with concrete affected behavior rather than a blanket prohibition on SQLite.

Trace frequently used read and write queries, scope, expected cardinality, ordering and pagination. Distinguish target-level pagination from the cardinality of each target's loaded child collection.

## Query correctness and consistency

- Verify ownership/tenant predicates on both main queries and aggregates used for counts or filter options.
- Check relationship keys and subqueries, joins, selected columns, alias collisions and duplicate rows.
- Validate and allowlist dynamic sort fields/directions. Parameter binding protects values, not arbitrary SQL identifiers.
- Examine wildcard escaping and case matching. A LIKE pattern can be bound safely while still interpreting wildcard characters contrary to user expectations.
- Review null ordering, tie-breakers and stable pagination. Offset pagination over changing data can shift items; document impact before prescribing cursor pagination.
- Check options/count queries against intended semantics. They do not necessarily apply every active filter if the UI is designed to let users expand a selection.

## N+1 and response cardinality

A confirmed N+1 finding needs an initial query, repeated relation access and absent loading on that path. Eager loading eliminates repeated relation queries but can still load too much data. Separate these issues.

Inspect nested serialization: five paginated targets can each load thousands of repositories. An independent paginated repository screen does not cap the target screen's nested payload.

## Index review

Inspect actual migrations, constraints, query predicates and ordering. Do not assume an index is absent because query-builder code does not mention it.

Consider column order in compound indexes, selectivity, join paths, filters, sort direction, expression matching, null behavior and write/storage costs. Recommend an index tied to a real query. A user identifier cannot be part of an index on a table that has no such column.

A conventional B-tree on a text column does not generally solve leading-wildcard substring search. Consider engine-specific expression/trigram/full-text options only where justified and supported. Avoid adding indexes for every field mechanically.

Use a safe execution plan and representative data to confirm magnitude. EXPLAIN ANALYZE executes the query; never apply it to mutating or expensive live queries without authorization. Without measurement, distinguish structural risk from observed latency.

## Transactions

Identify which writes must succeed or fail together. Follow the complete boundary, including nested transactions, model hooks, events and dispatch. Check transaction connection consistency when multiple connections are involved.

Network retrieval inside a transaction can hold connections, locks and uncommitted state during remote latency. It may be intentional, but the cost and alternatives must be explicit. Fetching first and persisting atomically later can reduce that cost when domain invariants permit it.

Distinguish provider failure before writes, mapping failure during writes, commit failure and failure-record persistence. A catch that updates an error inside a transaction later rolled back does not durably record the error.

Transactions do not automatically prevent duplicate concurrent requests. Commit timing also matters for notifications and jobs that may read uncommitted records.

## Concurrency and idempotency

For each retriable or parallel workflow, review:

- Database unique constraints protecting shared identities and subscriptions.
- Whether read-then-create/update decisions can race.
- Lock scope, ownership, ordering and lifetime; deadlock and contention risks.
- Cache locks across job classes, container instances and cache connections.
- Job timeout, lock expiry and reservation expiry consistency.
- Releases due to contention versus retries after execution exceptions.
- Lost updates and overlapping writes from Add, Sync, scheduler and webhook paths.
- Replay of successful, failed and interrupted operations.
- Atomicity of the data write and operation-result write.

A lock on a target ID cannot identify a target that does not exist yet; inspect identity construction across creation and update flows. A file-based lock may coordinate processes sharing storage but not independent instances with separate filesystems.

A firstOrCreate-style API is not proof of race safety by itself. Inspect the installed implementation, unique constraint and handling of conflicting concurrent writes. Do not invent a race when the framework and database already handle it.

## Schema and migrations

Check data types, nullability, lengths, defaults, foreign keys, deletion cascades, unique identities and reversible changes. Distinguish documented intent from actual constraints.

For existing data, consider backfills, changing nullability, column removal, table locks and rollout ordering. Down migrations may drop data; do not call them safe merely because syntactically reversible.

Enums/casts can constrain application values without a database CHECK constraint. Decide whether alternative writers exist and whether database enforcement is needed. Check external IDs, unsigned assumptions and case-sensitive identities against provider behavior.

## Error and deletion scenarios

Examine missing users/targets, removed subscriptions during execution, malformed external data, constraint violations, failed commits and stale job references. Identify whether failures preserve previous successful data and whether deletion keeps or removes audit history intentionally.

Recommend meaningful integration tests against the relevant engine for constraints, rollback and parallel behavior. Do not verify a constraint by mocking the repository that would exercise it.
