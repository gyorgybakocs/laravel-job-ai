# Test Quality Review

## What a test establishes

Identify the behavior under test, the real code executed, the boundaries replaced, and the assertions. Ask whether the test would fail if the claimed behavior were broken. Execution or coverage without a distinguishing assertion is weak evidence.

Evaluate test code with the same scrutiny as production code. A test using a nonexistent dependency method is broken even if its intended scenario is valuable.

## Test layers and boundaries

| Layer | Appropriate scope | Common misleading result |
|---|---|---|
| Pure unit | Logic with explicit collaborators; no database/network required. | Mocking the method being tested or all meaningful behavior. |
| Framework-backed unit | Container, route/translation helpers, real result objects; isolated mocked infrastructure. | Assuming framework boot automatically means database access, or omitting boot while using facades. |
| Feature/integration | Route, middleware, request validation, persistence, serialization and constraints as relevant. | A service mock makes a "persistence test" pass without exercising persistence. |
| Worker integration | Serialization, actual queue consumption, middleware, outcomes and failure recording. | Queue::fake verifies dispatch but is presented as proof of real worker execution. |
| Browser/component | Actual interaction, state and presentation behavior where needed. | Presence of a component is treated as proof of accessibility or interaction. |

No single layer replaces all others. Browser tests are not automatically necessary for every change. A controller unit test cannot establish middleware or database constraint behavior.

## Assertions

Check exact redirect/response status, page/component name, important payload values, secret exclusion and relevant side effects. Checking only response class or count can miss incorrect values or ownership leaks.

For errors, establish that the expected operation fails and that the observed error is the intended one. Broad exception catches can hide assertion failures or unrelated setup errors. Assert specific classes/conditions and ensure unexpected exceptions fail the test.

Do not assert only what a mock was configured to return. Expected values should represent the contract, not merely repeat implementation calculations.

When an empty collection is expected, ensure fixtures actually contain data that should have been excluded. Privacy tests need at least two users/tenants or public/private rows to distinguish correct scoping from an empty database.

## Mocking and fakes

Fake external systems and expensive boundaries. Keep the code under test real. Inspect mock signatures, return types, call counts, argument types and framework API compatibility.

Unsaved real Eloquent models can be valid fixtures for controller mapping when relationships are explicitly loaded and no persistence is needed. Check accidental lazy loading and connection access. A generic untyped mock cannot replace a parameter requiring a concrete User instance.

Use actual immutable result objects when their factories are pure. Do not bypass private constructors or invent factory signatures. Inertia response APIs must match the installed package; use supported response conversion/testing mechanisms rather than guessed getters.

A Queue fake can assert IDs and afterCommit configuration. It does not prove transaction ordering, lock sharing, timeout signals or Redis execution. Those need appropriately scoped integration checks.

## Data providers

Use providers for variants of the same behavioral contract, with descriptive case names and explicit expected outcomes. Do not force unrelated workflows into one branching test.

Review boundary coverage: null versus empty/absent, user versus organization, success versus mapped failures, repeated requests and invalid enum/filter values. Repeating several nearly identical happy paths does not replace meaningful edge cases.

Providers must match the installed PHPUnit/Pest API. In PHPUnit, providers execute before per-test setup; do not depend on a booted Laravel container, database fixtures, setUp state or side-effectful mocks there. Use simple values, enums, pure factories or closures whose side effects occur inside the test when appropriate.

## Database tests

Use an isolated test database. Exercise real constraints, rollback and deletion behavior instead of mocking the repository. Inspect RefreshDatabase/transaction handling and engine-specific behavior.

A uniqueness test expecting any QueryException can pass for an unrelated foreign-key or connection failure. Ensure setup is valid and, where material, establish which constraint was violated.

Concurrent safety is not demonstrated by calling the same method sequentially. Use independent transactions/connections or a credible concurrency harness when making a concurrency guarantee.

## Time, state and retries

Freeze time where assertions depend on it. Check timezone and precision assumptions. Ensure test isolation resets shared clients, cache, facades, static state and fake HTTP sequences.

Test replay after success, failure and interruption where the workflow supports retries. Include removal of users, targets or permissions between dispatch and execution. Verify that failed updates preserve previous successful data.

For mapping/persistence failure, include a valid earlier row followed by invalid data so rollback tests can reveal partial writes. Assert operation status as well as repository data when both are part of the contract.

## HTTP and provider tests

Prevent unintended live HTTP calls. Verify endpoints, relevant query parameters and auth behavior. Include public/private boundaries when the product is public-only and credentials can broaden access.

Test pagination across multiple pages and exact page-size boundaries. Cover malformed responses and provider failures when the code claims to handle them. Do not treat a status-code test as proof of transaction recovery.

## Coverage and mutation reasoning

Coverage helps locate unexecuted paths. It does not show whether assertions detect incorrect behavior. Consider small plausible mutations: remove authorization, change a query scope, omit rollback, swap an error key, remove the public filter. Would an existing test fail?

Mutation tools can provide additional evidence when available and authorized; do not require installation during review. Describe manual mutation reasoning as reasoning, not a tool result.

## Report test findings precisely

Separate broken tests, weak assertions, missing important scenarios and optional coverage expansion. Specify which product guarantee remains unproven and why it matters. Do not inflate every absent test into a production defect.

References: version-matched Laravel testing/mocking/database testing and PHPUnit/Pest documentation listed in `reference_sources.md`.
