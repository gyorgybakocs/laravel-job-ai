# Performance, Cache and Operations Review

## Identify the actual bottleneck

Trace where work happens and which resources it holds: request processes, worker slots, database connections/locks, memory, external API quota and response bandwidth.

State the growth scenario and code path. An unbounded child collection is a structural risk; claiming a specific latency or user capacity requires measurement. Do not fabricate benchmark results.

Review interactions between throughput, freshness, consistency and user-visible latency. Moving work to a queue frees the request but does not inherently make the underlying work complete sooner. Load distribution, automatic capacity scaling and queue scheduling solve different operational problems.

## Database and payload cost

Check database filtering before pagination, selectivity, repeated queries, nested serialization, unnecessary relations and aggregate queries. Review both query work and transferred payload size.

For a collapsed UI, verify whether data is nevertheless loaded and sent. On-demand retrieval or independent pagination may help, but assess extra requests, authorization and UX trade-offs.

Per-record persistence can be acceptable for small workloads. For large imports, evaluate batch writes, provider pagination, memory accumulation and transaction size based on measured or clearly bounded requirements.

## Cache design

For each cache identify owner, key, namespace, TTL, creation, invalidation and freshness contract. Include tenant/user/locale/permission context when it affects results.

Review invalidation after writes, deletion and external refresh. Check stale permission decisions, negative cache entries, cache stampedes, lock lifetime and failure fallback. A global key for user-scoped data is a privacy defect, not just performance debt.

Do not demand caching without an identified repeated expensive workload. Do not infer correct invalidation merely because a TTL exists.

## Frontend behavior

Inspect request frequency, search/debounce behavior, pagination resets, racing responses, stale local state and polling lifetime. Verify cleanup on navigation/unmount and scope of partial reloads.

Review huge lists, costly computed work, transferred but unused fields and duplicate fetches. Virtualization is not automatically necessary for small paginated data. Check timestamp/timezone representation and state feedback without creating new product requirements.

## Queue operation

Distinguish source configuration from effective runtime settings. Inspect duplicate environment assignments, cached config, driver/connection selection, worker command and actual execution evidence.

Verify worker supervision, signals, graceful shutdown, code/config refresh, job timeout, reservation expiry, retry delay, failure logging and lock expiry. Long-lived workers can retain mutable user/client state.

Review starvation, contention, repeated external calls, provider throttling and recoverability after interruption. A retry must not corrupt data. A job exception hook must handle timeout paths where the business catch does not execute, if durable operation status is required.

Multiple worker instances may need a shared lock store and independent capacity control. A separate worker image is not necessarily required; process placement should match the workload and operations needs.

## External requests

Inspect connection/request timeouts, pagination bounds, malformed data, retries, rate limits and partial failure. Repeated retries across several layers can multiply provider calls.

Consider whether memory grows with every API page and whether a provider's pagination termination is reliable. Do not introduce streaming or batches mechanically; first identify volume and atomicity requirements.

## Docker, environment and Makefile

Trace every relevant Makefile target through included variables, Compose files and entrypoint scripts. Verify build arguments, service hostnames, env-file precedence, health checks, volumes, filesystem ownership and process users.

Distinguish image build from container recreation and process restart. Confirm intended extensions and binaries are present in the image actually used by the runtime. A successful build does not prove an old running container uses it.

Review fresh checkout and repeat-run behavior, dependency lock preservation, ownership repairs and whether one target unexpectedly stops other services. Identify destructive migration/reset commands, broad chmod/chown, accidental secret output and inconsistent exits.

Do not assume a command named test/ci is harmless. Inspect it before execution. Root processes in a development container are not automatically a production vulnerability; explain actual boundaries and exposure.

## Dependency and quality commands

Inspect manifests and lockfiles, not only latest package names. Review project-supported test/static-analysis/formatting commands. A Vite build is not automatically a TypeScript check; PHPUnit coverage is not a correctness proof.

Never auto-run dependency upgrades, install new tools, overwrite lockfiles, format source or rebuild/start infrastructure as part of a read-only review. Report unavailable checks precisely.

## Operational report

Separate confirmed current failure, structural scaling limit and unmeasured concern. State whether the environment was inspected from source or observed running. Recommendations should explain operational value and cost, not simply prescribe extra containers, indexes or caches.
