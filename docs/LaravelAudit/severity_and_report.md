# Classification, Severity and Report Format

## Classification comes before severity

| Classification | Meaning | Required evidence |
|---|---|---|
| Good | Correct, proportionate implementation of a concrete responsibility or guarantee. | Relevant source and explanation of why the behavior/design works. |
| Works, but needs improvement | Technical debt or a current limitation with a credible maintenance, scaling or operational cost. | Existing implementation, cost and conditions under which it matters. |
| Defective | Demonstrably incorrect behavior, integrity failure, security exposure or broken operation. | Trigger, executed path, expected/actual difference and consequence. |
| Verification needed | A specific suspicion not established by available evidence. | Known evidence, missing evidence and a bounded check to resolve it. |
| Optional enhancement | An additional product capability or preference, not necessary for current correctness. | Clearly identified as optional; no defect severity. |

Positive observations should be representative and meaningful, not a list of boilerplate compliments. A completed checklist is not evidence of quality.

## Impact-based severity

- **Critical:** Established path to severe data loss, critical security exposure or failure of an essential capability with broad impact. Explain conditions and scope.
- **High:** Substantial security, integrity, correctness or operational impact in a reachable scenario.
- **Medium:** Meaningful bounded functional/reliability/performance problem or material maintenance debt.
- **Low:** Limited-impact defect or maintainability issue.
- **Trivial:** Verified cosmetic, spelling or formatting issue with negligible behavioral effect.

Use qualitative levels or map them to 5–1 if requested. Do not use a quota. Do not automatically assign HIGH to duplication, CRITICAL to every syntax issue, or MEDIUM to every missing custom framework layer.

Evidence confidence and severity are separate. Describe established preconditions. A conditionally reachable privacy exposure can be a confirmed risk without proof that a real incident occurred. An unverified suspicion belongs under verification needed, not a scored defects table.

## Required finding content

For every defect or debt item include:

1. Stable ID and classification.
2. Severity and rationale.
3. Revision-specific path and accurate line range.
4. Exact relevant source excerpt, with related caller/configuration evidence if needed.
5. Trigger and preconditions.
6. Expected versus actual behavior, or the concrete debt cost.
7. Existing protections and why they do not resolve the issue.
8. Recommendation, trade-off and smallest appropriate scope.
9. Suggested verification or regression test.
10. Whether the conclusion is source-only or supported by an executed check.

Do not manufacture line numbers. Do not present illustrative corrected code as original source. Redact secrets without losing the explanation of the boundary.

## Report structure

### Review baseline and coverage

State repository, branch, commit, working-tree scope, framework/dependency versions, database and execution model. Summarize actual checks and their results.

Use a coverage matrix:

| Subsystem | Relevant files or modules | Source reviewed | Checks executed | Remaining verification |
|---|---|---|---|---|
| Routes/controllers/requests | Actual paths | Complete/partial/excluded with reason | Actual results or none | Specific gaps |
| Middleware/models/data access | Actual paths | Status | Results | Gaps |
| Services/clients/jobs/events | Actual paths | Status | Results | Gaps |
| Frontend/serialization/cache | Actual paths | Status | Results | Gaps |
| Tests/configuration/tooling | Actual paths | Status | Results | Gaps |

Add rows as needed. If relevant files remain unread, label the review partial. Do not call selected-file inspection a full repository audit.

### Subsystem assessments

For each subsystem report:

- What is good, with source-backed reasons.
- What works but needs improvement.
- What is defective.
- What requires additional verification.

Do not repeat the same finding under multiple categories. Cross-reference shared findings. If a subsystem has no established problem, say so briefly and explain what was examined.

### Cross-system behavior

Summarize end-to-end workflows and boundary failures: authorization to persistence, commit to worker, cache invalidation to frontend refresh, external visibility to shared data, and retry to idempotency.

### Prioritized findings table

| ID | Classification | Severity | Location | Trigger and consequence | Recommendation | Verification |
|---|---|---|---|---|---|---|

Follow with source excerpts for findings requiring more than a short inline quote. Keep evidence attached to the relevant ID.

### Remediation order and limits

Recommend a bounded order based on security, correctness, data preservation and practical cost. Identify existing acceptable trade-offs and deferred verification. Keep optional product ideas separate.

Do not promise exhaustive discovery of all possible bugs. Do report complete traversal of the declared source scope when supported by the coverage record.

## Forbidden shortcuts

- Missing hand-in files or unchecked product tasks reported as code defects.
- Policy/interface/service absence treated as proof of security or SOLID failure.
- Claims of benchmark, test or build success without execution evidence.
- Unmeasured guesses presented as demonstrated production bottlenecks.
- Generic advice attached to a file without an actual code-derived issue.
- Recommending broad refactoring solely because an example or scoring matrix prefers it.
