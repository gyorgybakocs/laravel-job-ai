# Design, SOLID and Complexity Review

## Evaluate design through consequences

Ask what each abstraction protects, which callers depend on it, and what changes together. Judge concrete maintenance cost, behavioral risk and extension effort. Pattern names and class counts are not quality metrics.

Avoid both a monolithic workflow and needless fragmentation into forwarding classes. Recommend the smallest useful structural change, preserving existing behavior and contracts.

## All five SOLID principles

| Principle | Review question | Evidence of a problem | Counterexample to a false positive |
|---|---|---|---|
| Single responsibility | Does this unit have unrelated reasons to change? | A controller combines request handling, provider protocol details, persistence mapping and email composition. | Coordinating several steps of one coherent use case is not automatically several responsibilities. |
| Open/closed | Can a plausible supported variation be added without unsafe changes throughout unrelated code? | Adding a supported provider requires duplicated conditionals and edits in multiple independent callers. | One small switch for a fixed enum is not automatically a missing strategy hierarchy. |
| Liskov substitution | Do implementations preserve caller-visible contracts? | A replacement narrows accepted inputs, changes failure semantics or breaks return guarantees expected by callers. | No inheritance or interchangeable implementations means there may be no specific substitution issue to report. |
| Interface segregation | Are consumers forced to depend on irrelevant operations? | A client uses one capability of an interface containing unrelated writes, exports and notifications. | A cohesive client interface with several relevant operations is not automatically too large. |
| Dependency inversion | Are important boundaries explicit and replaceable? | Domain orchestration constructs provider clients/config internally in a way that prevents isolation and changes unrelated code for infrastructure substitutions. | Injecting a concrete, testable class may be sufficient when no meaningful alternative contract exists. |

Do not label a Laravel convention as a SOLID violation without a specific responsibility, dependency or contract consequence.

## DRY, KISS and cohesion

Compare duplicated rules, not just matching text. Identify both locations and explain how one can change without the other, causing divergence.

Two short query predicates shared by otherwise different queries can be acceptable. A repeated permission rule across many live mutation paths can justify centralization. A unique database constraint remains necessary even if application validation is shared.

Do not merge unrelated flows merely because their current implementations look alike. An abstraction with flags controlling several unrelated behaviors may be worse than small duplication.

## Pattern evaluation

| Pattern or layer | Useful when | Questionable when |
|---|---|---|
| Application service/action | Coordinates a meaningful use case, multiple writes or external boundaries. | Exists only to rename a trivial call without improving ownership or reuse. |
| Repository/query object | Encapsulates substantial repeated queries, persistence mapping or a genuine data boundary. | Recreates the entire Eloquent API through forwarding methods. |
| Strategy | Multiple supported algorithms share a stable caller contract. | Introduced for one implementation or to avoid one readable condition. |
| Factory | Construction varies, requires nontrivial setup, or protects lifetime/state. | Wraps a single constructor with no construction policy. |
| Adapter/client | Isolates provider protocol, authentication, mapping and failures. | Adds layers without isolating anything or leaks provider details through every caller. |
| DTO/value object | Makes meaningful structured contracts or invariants explicit. | Replaces every primitive with a class without improving valid-state guarantees. |
| Event/listener | Independent or optional reactions to a completed domain action. | Hides the mandatory main workflow across a chain of listeners. |
| Observer | Cohesive entity lifecycle behavior with understood timing. | Performs hidden network calls or unrelated transactional business work. |
| Decorator | Composes an independent capability around a stable contract. | Used to spread one inseparable workflow across many classes. |

These are considerations, not automatic verdicts. Inspect real callers and tests before removing or introducing a layer.

## Extension analysis

Use explicit scenarios supported by the existing domain: another provider, a second entry point, more users, larger datasets, or a new filter. Show the actual code changes that would be necessary and why they are risky or repetitive.

Do not penalize an MVP for lacking speculative infrastructure. Record a current limitation separately from an optional future capability. Do not call working code broken solely because a more elaborate architecture could exist.

## Maintainability evidence

Useful evidence includes inconsistent error/result contracts, hidden dependencies, divergent authorization, changes spanning unrelated modules, repeated state transitions, excessive configuration flags and inability to isolate tests without replacing internals.

Function length and nesting can help locate complexity, but assess comprehensibility and responsibility. A well-structured mapping function can be longer than a problematic, highly coupled short function.

## Style boundary

Use the repository's Pint, PHP-CS-Fixer, Prettier and ESLint settings as applicable. Formatting preferences are low-impact issues, not architectural defects. Do not report syntax or formatting errors without exact evidence. PSR guidance does not override an explicitly adopted project formatter policy unless compatibility is affected.
