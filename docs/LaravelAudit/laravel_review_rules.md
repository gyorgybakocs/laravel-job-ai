# Laravel Layer Review Rules

These are review questions, not mandatory architecture. Confirm behavior against the installed version and actual call graph.

## Routes and endpoints

Check method semantics, middleware groups, names, parameter binding, constraints and response contracts. Confirm authentication is applied to sensitive actions, state-changing actions are protected appropriately, and resource binding is followed by authorization where required.

Identify ambiguous or duplicated endpoint behavior and inconsistent errors. Route closures are not inherently wrong for trivial rendering; essential multi-step business flow hidden in a closure is harder to reuse and test. A JSON response does not automatically make an endpoint a stable external REST API.

## Controllers

Controllers should coordinate request inputs, application work and responses. Review business complexity, database/external integration leakage, duplicated authorization and result mapping, and inconsistent use of collaborators.

Do not require a service for every one-line action. Conversely, an action coordinating remote retrieval, multiple writes and recovery needs clear ownership. Confirm each dependency is used and testable.

## Form Requests and input mapping

Check authorization separately from validation. Verify rules for optional versus absent/null values, enum/options validation, lengths, arrays, duplicates, normalization and dynamic query input. Valid input is not automatically authorized input.

Trace only validated and intentionally mapped data to sensitive writes. Verify route middleware protects requests whose authorization method allows all callers. Do not claim that a missing custom authorize method disables authentication; inspect framework defaults and middleware.

## Middleware

Check ordering, registration, scope and state changes. Look for domain workflows hidden in middleware, accidental global database queries and sensitive shared data. Authentication, locale and cross-cutting preparation can fit here. Target creation and multi-stage synchronization usually do not.

Inspect response-side behavior as well as request-side behavior, including headers, cache control, cookies and serialization.

## Models and relationships

Review fillable/guarded choices, hidden attributes, casts, defaults, relationship keys, custom pivots, scopes and deletion behavior. Identify invariant-breaking mutation paths and unexpected model events. A cast does not establish schema constraints or authorization.

Entity-local behavior can belong on a model. Remote requests, unrelated orchestration and large business workflows can make models difficult to reason about. Do not assume every model needs a repository wrapper.

## Services and actions

Trace explicit inputs, outputs, dependencies, transactions and failure semantics. Check whether the action has a coherent responsibility, whether it is safe across callers, and whether it is retryable when required.

Look for external calls made while locks/transactions are held, mutable shared client state, inconsistent results and swallowed exceptions. Prefer safe user-facing error classification while preserving useful diagnostic context.

## Repositories and queries

Determine whether a repository encapsulates meaningful repeated queries, persistence mapping or a real replaceable boundary. A class that only forwards a single Eloquent method may add little value; prove the navigation/maintenance cost before proposing removal.

Check result shapes, pagination, eager loading, authorization scopes, configurable sorting, escaping, transaction expectations and query consistency. A list query and a filter-options aggregation can intentionally have different filtering semantics.

## Jobs and commands

Verify stable inputs, current versus snapshotted credentials, commit timing, authorization at execution, missing records, idempotency, overlap and timeout behavior. Trace exceptions through the job and framework failure handling. Review command exit codes and output consistency.

A job used with a synchronous driver is still a job but does not provide asynchronous execution. A fake dispatch test does not verify that a real worker consumes a queue.

## Events, listeners and observers

Determine what triggers them, execution timing, transaction behavior and whether core flow is visible. Optional reactions to a domain event can be appropriate. Hidden mandatory writes or network calls in an observer can obscure critical behavior.

Evaluate events against the actual number and independence of reactions. Do not recommend events just to make a small sequential workflow appear decoupled.

## Providers, container bindings and facades

Check binding lifetimes and mutable state under queue workers or long-lived HTTP processes. Identify leaking tokens, user/tenant context, cached decisions or request objects in shared services.

A facade is not automatically a dependency-inversion violation. Judge dependency visibility, replaceability and test isolation. Interfaces are justified at meaningful boundaries, not simply because dependency injection exists.

## Serialization and frontend integration

Inspect actual payloads and typed consumers together. Verify field names, nullability, relation loading and sensitive data exclusion. Separate internal exceptions from user-facing errors.

Review component responsibilities, reusable presentation, request state, filter/pagination behavior, race handling, polling cleanup and state preservation. A hidden/collapsed component may still receive a large eager-loaded payload. Match server and client date/time expectations.

## Configuration and framework compatibility

Check configuration caching assumptions, environment access, defaults, effective driver selection and package APIs. Inspect framework defaults before declaring a missing custom setting a bug. Verify feature availability in the installed version.

Review style against project configuration and PHP language compatibility. Do not use line-count thresholds as substitutes for responsibility analysis.
