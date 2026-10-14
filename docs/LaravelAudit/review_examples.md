# Calibration Examples and Expected Verdicts

These examples are review exercises, not facts about the repository being audited. Snippets are deliberately abbreviated. Read each scenario's stated contracts before evaluating it. Do not copy identifiers, findings or line numbers from here into an audit.

## How to evaluate an auditor

Run the same scenario through the auditor and compare its result with the expected verdict. Check both detection of real problems and rejection of false positives. Correct classification with fabricated evidence is a failure.

Each case records context, evidence, expected verdict and unacceptable conclusions. The examples can later be turned into isolated executable fixture projects. They are not claimed to be a runnable test suite.

## Case 1 — Shared public subscriptions

Context: authenticated users may follow any public organization. The controller obtains the user from the authenticated request and validates the organization name. The repository uses:

```php
$user->githubTargets()->syncWithoutDetaching([$publicTarget->id]);
```

Expected: this operation is not inherently mass assignment or an ownership violation. Check that the linked data is actually public and that the caller cannot substitute another user's identity.

Reject: "Critical access-control defect because the user does not already own the shared target."

## Case 2 — Explicit authorization

```php
abort_unless($targets->belongsToUser($request->user(), $target), 404);
$targets->detachFromUser($request->user(), $target);
```

Context: both sync and detach use the same repository membership check, and routes require authentication.

Expected: correct authorization on the demonstrated path. A policy may be optional organization, not a security fix by itself.

Reject: "Missing Policy means unauthorized users can remove targets."

## Case 3 — Genuine missing ownership check

```php
public function show(Invoice $invoice): JsonResponse
{
    return response()->json($invoice);
}
```

Context: authentication middleware exists, invoices contain private tenant data, binding is unscoped, and no middleware/policy/service check restricts the invoice to the caller.

Expected: confirmed unauthorized read risk. State the authenticated attacker, controllable invoice identifier and private data exposure. Contrast with Case 2.

Reject: authentication alone is sufficient, or a generic policy recommendation without tracing the data boundary.

## Case 4 — Paginated repository table

```php
$rows = $query->paginate(15)->withQueryString();
```

```vue
<tr v-for="repository in repositories.data" :key="repository.id">...</tr>
<Pagination :links="repositories.links" />
```

Expected: reject unbounded rendering and missing-pagination findings for this path. Virtualization is optional unless additional evidence establishes costly page sizes.

## Case 5 — Paginated parents, unbounded children

```php
$targets = $user->githubTargets()->with('repositories')->paginate(5);
```

Context: each target's complete repositories relation is serialized into the response, even when the frontend row is collapsed.

Expected: structural response-size/memory scaling limitation. Parent pagination does not cap child collection size. Do not call this N+1 when eager loading already handles repeated relation queries. Runtime latency remains unmeasured.

## Case 6 — Public/private provider boundary

```php
$rows = $http->withToken($userToken)->get("/orgs/{$name}/repos", [
    'per_page' => 100,
    'page' => $page,
])->throw()->json();
$sharedRepositories->saveAll($target, $rows);
```

Context: the provider endpoint's default can include private repositories visible to the token, the application promises public-only data, persistence does not inspect visibility, and every target subscriber can read the saved rows.

Expected: confirmed conditional privacy-exposure risk. Cite the provider's official default and the full persistence/read path. Recommend explicit public selection and an appropriate persistence-boundary check. Do not claim an actual incident without evidence.

Source for this scenario: [GitHub organization repositories](https://docs.github.com/en/rest/repos/repos#list-organization-repositories).

## Case 7 — Exceptions are propagated

```php
$response->throw();
```

```php
try {
    $client->getRepositories($name, $type);
} catch (ConnectionException|RequestException $exception) {
    return Result::fromException($name, $exception);
}
```

Context: the result maps provider status codes to safe translated messages. Unhandled unexpected exceptions reach framework logging.

Expected: reject "HTTP exceptions are swallowed" and "a custom exception class is mandatory." Assess logging and failure persistence separately.

## Case 8 — Unit test guesses dependency getters

```php
$response = $controller->index($request);
$this->assertSame('targets/Index', $response->getComponent());
$props = $response->getProps();
```

Context: the installed response class has neither getter and offers a supported HTTP-response conversion API instead.

Expected: broken test due to nonexistent dependency methods. Verify installed source, not framework memory. The use of real result objects, mocks and data providers elsewhere can still be good.

## Case 9 — Dispatch flag versus real queue execution

```php
Queue::fake();
$controller->sync($target, $request);
Queue::assertPushed(SyncJob::class, fn ($job) => $job->afterCommit === true);
```

Expected: useful dispatch unit test. It proves the property and pushed job, not transaction ordering, Redis consumption or worker signals. Do not call it worthless because it is not an integration test.

## Case 10 — Misleading test mocks persistence away

```php
$repository->shouldReceive('save')->once()->andReturn($model);
$service->run($input);
$this->assertSame($expectedName, $model->name);
```

Context: the test is described as proving database rollback and uniqueness, but no database write or constraint is exercised.

Expected: weak/misclassified proof of those guarantees. It may still be a useful unit test of mapping. Recommend a real isolated database test for rollback/uniqueness, not deleting the unit test.

## Case 11 — Transaction held during remote retrieval

```php
$database->transaction(function () use ($user, $name) {
    $target = $targets->findOrCreate($name);
    $targets->attach($user, $target);
    $synchronizer->fetchAndSave($target, $user->token);
});
```

Expected: evaluate connection/lock duration under remote latency. A transaction correctly protects local atomicity but has an operational cost. Suggest retrieval outside the transaction if invariants allow it; do not falsely say a transaction is missing.

## Case 12 — Duplicate query predicates

Context: a paginated result query and a language-count query both apply user scope and archive status. Search and language selection are applied only to the result query by design.

Expected: possible small refactoring opportunity; not automatically HIGH or proof that filtering is inconsistent. Explain intended filter-option semantics before recommending shared logic.

## Case 13 — Shared cache key leaks user-specific rows

```php
return Cache::remember('my-invoices', 300, fn () =>
    $user->invoices()->get()
);
```

Context: the cache store is shared across requests/users and invoices are private.

Expected: confirmed cross-user data risk; the scoped database closure does not scope the cache key. Recommend identity/tenant-aware cache keys and invalidation consistent with writes.

## Case 14 — Formatter already accepts the file

Context: the project's configured formatter check succeeds, and no parser/type checker reports the alleged syntax issue.

Expected: reject a formatting defect based solely on another style preference. A formatter result alone does not prove all runtime or type behavior is correct.

## Case 15 — Actual failed rollback

```php
foreach ($providerRows as $row) {
    $repositories->upsert($row);
}
```

Context: the contract requires an all-or-nothing snapshot update, there is no surrounding transaction, and a malformed second row throws after the first row has been written.

Expected: confirmed partial-write integrity defect. Cite the absent outer transaction through the call graph and suggest a fixture with one valid and one invalid row. Do not claim every loop requires a transaction when no atomicity contract exists.

## Case 16 — Complexity without a boundary

Context: one controller calls a handler, which calls a manager, which calls a gateway, which calls a repository, which forwards one Eloquent lookup. There are no alternate implementations, cross-cutting policies, meaningful transformations or independent reuse.

Expected: maintainability debt if navigation and dependency cost are demonstrated. Recommend consolidating redundant forwarding layers. Do not label every service/repository interface in an unrelated codebase unnecessary by association.

## Case 17 — Environment templates versus effective runtime

Context: an example file declares Redis; the effective local configuration deliberately selects the synchronous driver to verify job wiring.

Expected: report the effective execution model. Do not claim a Redis job was consumed merely because Redis and a supervised worker exist. A deliberate integration-test stage is not an unexplained production outage.

## Case 18 — Unjustified compound index

Context: the repository table has no user_id column; user membership is represented in a pivot table. Queries use substring search and language filtering.

Expected: reject an index recommendation on nonexistent columns. Explain query-specific indexing needs, and do not claim a full_name B-tree makes all leading-wildcard searches efficient.

## Benchmark scoring

For each case record: correct classification, source fidelity, relevant boundary tracing, false-positive rejection, appropriate impact assessment, and proportional recommendation. Count fabricated evidence as a failure even when the final conclusion happens to be plausible.

A useful auditor should succeed on both defect cases and no-defect cases. Track false positives separately from missed real defects. Do not optimize for the largest number of findings.
