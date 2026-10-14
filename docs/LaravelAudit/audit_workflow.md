# Audit Workflow

## Review contract

The deliverable is a technical assessment of the implementation. Include application code, frontend code where present, schemas, configuration, tests, dependency metadata, and tools that build and run the application.

Exclude product-backlog fulfillment, implementation-plan progress, checklist editing, and missing submission paperwork. Do not use a checklist checkbox as evidence. Review operational documentation only when needed to understand commands, supported environments, or externally provided behavior contracts.

## 1. Establish an immutable review baseline

Record repository, branch, commit and working-tree differences if visible. If reviewing uncommitted changes, distinguish the base revision from the additional changes. Do not combine source excerpts from different revisions without saying so.

Determine actual Laravel/PHP, PHPUnit/Pest, frontend and integration-library versions from manifests and lockfiles. Identify the database engine, queue/cache/session drivers, and long-running process model from configuration and runtime evidence. Templates do not establish effective runtime values.

## 2. Inventory before findings

Enumerate relevant first-party files and group them into:

- Routes, entry points, controllers, requests and middleware.
- Models, relationship definitions, factories, migrations and seeders.
- Application services/actions, repositories/query objects and external clients.
- Jobs, commands, scheduler definitions, events, listeners and observers.
- Resources/serializers, views, frontend pages, components, composables and types.
- Separate frontend applications and shared workspace packages anywhere in the repository, regardless of framework or directory layout.
- Configuration, dependency manifests/lockfiles, bootstrap/provider registrations.
- Tests, fixtures, test configuration and static-analysis/formatter configuration.
- Dockerfiles, Compose definitions, entrypoints, process supervision, CI workflows and Makefiles.

Exclude generated caches/assets and dependency trees from the first-party inventory. Consult dependency source selectively when it establishes behavior. Inspect environment key names/defaults without publishing secrets.

Track each relevant file as read, pending, or excluded with a reason. Do not say "full repository reviewed" while relevant files remain pending. Large reviews may require multiple passes; keep one cumulative coverage record.

## 3. Layer review

Use `frontend_review_rules.md` for every discovered frontend, whether Blade/server-rendered, embedded, or a separately built application. Assess each frontend and the backend–frontend contract separately.

Inspect every subsystem using the matching reference. For each layer, identify responsibilities, dependencies, input/output contracts, errors, ownership checks, state mutations and hidden framework hooks.

Assess all five SOLID principles where applicable, plus cohesion, coupling, KISS and DRY. Identify unjustified layers and concrete extension friction. Do not make architectural suggestions merely to resemble a preferred template.

Record positive observations as well as candidates for defects and debt. A good implementation deserves a concise explanation of why it works.

## 4. End-to-end review

Select the application's important read and write workflows, including failure cases. Trace:

1. Route and middleware.
2. Authentication, resource lookup, authorization and validated input.
3. Application action and external calls.
4. Persistence, transactions, constraints and concurrency.
5. Job/event dispatch, commit timing and retry behavior.
6. Serialization, cache/session effects and frontend state.
7. Failure reporting, logging and recovery.

Also examine alternate entrances: CLI, workers, scheduler, webhooks and internal reuse. A route-level check may not protect another caller. Do not invent alternate callers that do not exist; identify them as future extension scenarios when discussing debt.

## 5. Test and operational review

Review test assertions and mock boundaries, not only counts. Trace Makefile targets into actual commands, including environment forwarding and shutdown/destructive behavior. Check how image build, container recreation and long-lived process restart relate.

Record checks as: observed execution, source-only analysis, or not performed. Run tests only with isolated data and external boundaries faked as appropriate. Never execute an arbitrary repository script merely because its name sounds harmless.

## 6. Validate each candidate

Before reporting, establish exact evidence, trigger, impact and missing mitigation. Search related code for a counterexample or existing safeguard. Distinguish a real defect from a documented intermediate state, an acceptable trade-off, or a hypothetical future requirement. This does not excuse an existing privacy or integrity defect.

A missing benchmark leaves performance magnitude unverified. A missing custom hook does not prove absent framework handling. A syntax/formatting suspicion needs a parser/formatter result or an unambiguous language-rule explanation.

## 7. Coverage and conclusion

Produce a subsystem coverage table with representative files, checks, and verification limits. Report strengths, debt, defects and unresolved concerns by subsystem. End with prioritized, bounded recommendations and explicit unreviewed areas. Never promise that no undiscovered bugs remain.
