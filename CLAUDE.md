This file defines how AI coding assistants must work in this repository.

The goal is not only to generate working code, but to preserve architecture, maintainability, runtime behavior, data integrity, test reliability, localization, security, and long-term readability.

These rules apply to every code change, refactor, test, migration, configuration change, frontend change, translation change, and generated explanation.

---

## 1. Core Working Principles

Before changing code, the assistant must understand the existing implementation.

Do not guess the architecture from Laravel conventions alone. Inspect the actual project structure first.

Before implementing anything non-trivial:

1. Identify the relevant route, controller, request, middleware, model, policy, config, migration, view, component, translation file, test, and log context.
2. Explain the intended change briefly.
3. Prefer the smallest safe change.
4. Avoid introducing new layers, patterns, events, services, jobs, traits, abstractions, or packages unless there is a concrete reason.
5. Preserve existing behavior unless the task explicitly asks to change it.
6. Do not silently rewrite architecture.

If business rules are missing or ambiguous, ask instead of inventing them.

---

## 2. Laravel Is Not an Excuse for Magic

Do not use Laravel features just because they exist.

Avoid framework-driven design where the code becomes a collection of facades, events, listeners, observers, traits, macros, and global helpers without clear ownership.

Prefer explicit, boring, readable code.

Laravel features are acceptable only when they make the code simpler, safer, and easier to test.

---

## 3. Full SOLID Principles

All SOLID principles must be respected. Do not reduce SOLID to only SRP or OCP.

### 3.1 Single Responsibility Principle

Each class, method, component, request, job, listener, middleware, command, or action must have one clear reason to change.

Do not create god classes.

Do not put unrelated workflows into controllers, models, jobs, listeners, middleware, or services.

### 3.2 Open/Closed Principle

Prefer extending behavior without modifying stable, tested code.

However, do not introduce premature abstraction.

Do not create interfaces, strategies, factories, or plugin-like systems before there are multiple real use cases.

### 3.3 Liskov Substitution Principle

Subclasses or implementations must be safely replaceable wherever the parent type or interface is expected.

Do not create child classes or implementations that:

* weaken validation unexpectedly
* change return types unexpectedly
* throw unrelated exceptions
* silently ignore required behavior
* require callers to know the concrete implementation

Do not use inheritance to share random code. Prefer composition.

### 3.4 Interface Segregation Principle

Do not create fat interfaces.

An interface should describe one focused capability.

Bad examples:

```php
interface UserServiceInterface
{
    public function authenticate(array $data): User;
    public function updateBilling(User $user, array $data): void;
    public function sendNotification(User $user): void;
    public function generateReport(User $user): array;
}
```

Good examples:

```php
interface PasswordResetSender
{
    public function send(User $user): void;
}

interface SubscriptionGateway
{
    public function cancel(string $subscriptionId): void;
}

interface LocaleResolver
{
    public function resolve(Request $request): string;
}
```

Classes must not be forced to implement methods they do not need.

### 3.5 Dependency Inversion Principle

High-level business logic must not depend directly on low-level infrastructure details.

Use dependency injection for:

* external services
* clocks/time providers when time affects behavior
* API clients
* payment providers
* mail/notification clients
* storage adapters
* AI providers
* import/export clients
* replaceable infrastructure

Do not hide infrastructure access behind facades or global helpers in core business logic.

Depend on small abstractions when there is a real boundary, not by default.

---

## 4. DRY and KISS With Judgment

Do not apply principles mechanically.

### DRY

Do not duplicate business rules.

Do not create premature abstractions from two pieces of code that only look similar.

Prefer small, understandable duplication over a bad abstraction.

### KISS

The simplest solution that protects correctness is preferred.

Do not introduce events, observers, factories, interfaces, managers, pipelines, strategies, services, or traits unless they reduce real complexity.

---

## 5. Architecture Boundaries

Respect the responsibilities of each layer.

### 5.1 Routes

Routes should define HTTP entry points only.

Do not place business logic in route closures unless the project explicitly uses that style for very small endpoints.

Routes should point to controllers or invokable controllers when behavior deserves a named unit.

### 5.2 Controllers

Controllers coordinate HTTP flow.

Controllers may:

* receive a validated request
* call an application/domain action
* choose a response type
* redirect
* return a view or JSON response

Controllers must not:

* contain complex business rules
* build large queries inline
* perform unrelated side effects
* contain validation rules directly when a Form Request is appropriate
* contain translation text directly
* decide low-level persistence behavior

A controller should be readable as a high-level use case.

### 5.3 Form Requests

Use Form Requests for request validation and authorization when validation is non-trivial or reused.

Validation belongs on the backend even if the frontend validates the same input.

Frontend validation exists for user experience.

Backend validation exists for system integrity.

Do not trust frontend validation.

### 5.4 Middleware

Middleware is for cross-cutting request concerns.

Use middleware for:

* authentication checks
* authorization preconditions
* locale/region setup
* tenant/context setup
* request normalization
* access restrictions
* request-scoped invariants

Do not move domain business workflows into middleware.

Middleware should prepare or reject a request, not execute the main use case.

### 5.5 Models

Models represent persistence-backed domain entities.

Models may contain local, entity-specific behavior when it is genuinely tied to that model.

Models must not become god objects.

Avoid putting unrelated workflows into Eloquent models just because the model is easy to access.

### 5.6 Query Logic

Do not duplicate query conditions across controllers, jobs, commands, and views.

For reusable query logic, prefer:

* local scopes for model-specific query fragments
* query objects when queries become large or cross-cutting
* repository-like classes only if the project already uses them or if there is a real abstraction need

Do not create repositories automatically for every model.

Laravel already provides a data access abstraction through Eloquent. Add another abstraction only when it earns its place.

### 5.7 Services, Actions, and Use-Case Classes

Do not create a service class by default.

Use a service, action, or use-case class when:

* the workflow is used from multiple entry points
* the workflow is too large for a controller
* the workflow coordinates multiple models or external systems
* the workflow needs isolated unit tests
* the workflow represents a real application action

Avoid vague classes like:

* `UserService`
* `AppService`
* `DataService`
* `HelperService`
* `Manager`
* `Processor`

Prefer names that describe the use case:

* `CreateSubscription`
* `GenerateDailyReviewQueue`
* `ImportVocabularyList`
* `CalculatePlacementLevel`
* `SendPasswordResetNotification`

### 5.8 Blade and View Layer

Blade files should mostly render data.

Allowed in Blade:

* simple conditionals
* loops
* formatting already prepared by the backend
* component composition

Avoid in Blade:

* database queries
* authorization logic beyond simple directives
* business calculations
* duplicated text
* complex condition trees
* hardcoded user-facing strings

### 5.9 View Components

Use Blade/View Components for reusable UI fragments and presentation logic.

View Components are not domain services.

They may prepare display-specific values, but they should not execute business workflows or database-heavy operations unexpectedly.

---

## 6. Localization and User-Facing Text

Never hardcode user-facing text in frontend code, Blade templates, controllers, components, validation messages, notifications, emails, exceptions shown to users, or API responses intended for display.

All user-facing text must come from language files.

This applies to:

* Blade templates
* Livewire components
* Vue components
* React components
* Alpine snippets
* Inertia pages
* controllers
* Form Requests
* notifications
* mailables
* validation messages
* flash messages
* buttons
* labels
* placeholders
* page titles
* menu items
* empty states
* error messages shown to users

Use structured language files by domain or UI area.

Examples:

```txt
lang/en/auth.php
lang/en/account.php
lang/en/navigation.php
lang/en/dashboard.php
lang/en/errors.php
lang/en/validation.php
lang/en/emails.php
lang/en/common.php
lang/hu/auth.php
lang/hu/account.php
lang/hu/navigation.php
lang/hu/dashboard.php
lang/hu/errors.php
lang/hu/validation.php
lang/hu/emails.php
lang/hu/common.php
```

Do not place all translations into one huge generic file.

Before adding visible text, search existing language files first.

If a suitable translation key exists, reuse it.

If not, create a new key in the correct domain-specific language file.

Bad:

```php
return back()->with('success', 'Profile updated successfully.');
```

Good:

```php
return back()->with('success', __('account.profile_updated'));
```

Bad:

```blade
<button>Save changes</button>
```

Good:

```blade
<button>{{ __('common.save_changes') }}</button>
```

Do not concatenate translated strings.

Bad:

```php
__('You have') . ' ' . $count . ' ' . __('items')
```

Good:

```php
__('cart.items_count', ['count' => $count])
```

Use pluralization rules where needed.

Do not assume English grammar, English word order, or English pluralization applies to Hungarian or any other language.

Validation attributes and custom validation messages must also be localized.

---

## 7. Frontend Localization

Frontend-visible text must not be hardcoded in JavaScript, TypeScript, Vue, React, Alpine, Blade, Livewire, or Inertia pages.

Use the project's established translation mechanism.

If frontend translations are passed from Laravel to JavaScript, keep the translation keys structured and minimal.

Do not duplicate backend translation strings manually in frontend constants.

Do not create a second translation system unless the project already uses one.

---

## 8. Code Comments

Code comments are allowed and sometimes required, but comments in the codebase must be written only in English.

Do not write Hungarian comments in source code.

This rule applies to:

* PHP comments
* PHPDoc
* JavaScript/TypeScript comments
* Blade comments
* CSS comments
* SQL comments inside migrations or seeders
* TODO comments
* FIXME comments
* inline technical notes

Comments should explain why something exists, not repeat what the code already says.

Good comments explain:

* non-obvious business rules
* dangerous edge cases
* external service quirks
* historical compatibility constraints
* database/migration risks
* security-sensitive decisions
* performance tradeoffs
* temporary workarounds with reason

Bad:

```php
// Loop through users
foreach ($users as $user) {
    //
}
```

Good:

```php
// PostgreSQL advisory lock prevents duplicate daily queue generation
// when multiple requests arrive for the same user at the same time.
```

Do not add noisy comments just to make generated code look documented.

If a comment explains a workaround, include the reason.

If the workaround is temporary, include what must happen before removing it.

Public-facing documentation may be Hungarian if the project requires it, but source code comments, PHPDoc, inline comments, commit-like explanations inside code, and technical TODOs must be English.

---

## 9. PHPDoc

Do not add useless PHPDoc that only repeats native types.

Bad:

```php
/**
 * Get the user.
 *
 * @return User
 */
public function user(): User
```

PHPDoc is useful when it adds information not expressible in PHP types:

* array shapes
* collection item types
* generic types
* non-obvious side effects
* exceptions
* domain constraints
* expected value ranges

Good:

```php
/**
 * @return Collection<int, Sentence>
 */
public function dueSentences(): Collection
```

Good:

```php
/**
 * @param array{
 *     email: string,
 *     locale: string,
 *     timezone?: string
 * } $payload
 */
public function createFromPayload(array $payload): User
```

All PHPDoc must be in English.

---

## 10. TODO and FIXME Comments

Do not leave vague TODO comments.

Bad:

```php
// TODO: improve this
```

Acceptable:

```php
// TODO: Replace this fallback after the legacy onboarding flow is removed.
```

Every TODO/FIXME must explain:

* what is wrong
* why it cannot be fixed now
* what condition allows removal
* whether it is safe in production

Do not create TODOs as a substitute for finishing the requested task.

---

## 11. Configuration and Environment Variables

Do not call `env()` outside config files.

Application code must read configuration through `config()`.

Reason:

* Laravel config caching breaks assumptions around direct `env()` usage
* config files define the public configuration surface of the app
* code becomes easier to test and reason about

Good:

```php
// config/features.php
return [
    'daily_queue_enabled' => env('DAILY_QUEUE_ENABLED', true),
];

// app code
config('features.daily_queue_enabled');
```

Bad:

```php
env('DAILY_QUEUE_ENABLED');
```

Every new environment variable must have:

* a config entry
* a safe default when possible
* documentation in `.env.example`
* a clear name
* no secrets committed to the repository

Do not introduce config values without checking whether an existing config already represents the same concept.

---

## 12. Authorization and Security

Do not confuse authentication with authorization.

Being logged in does not mean the user may perform the action.

Use policies, gates, middleware, Form Request authorization, or explicit authorization checks consistently with the existing project style.

Every action that reads, modifies, deletes, exports, or reveals user-owned data must have authorization.

Never trust user IDs, role names, ownership fields, prices, limits, permissions, statuses, or privileged flags from the request.

Do not expose records by ID without checking ownership or permission.

Do not rely on hidden form fields for security.

Mass assignment must be deliberate.

Do not blindly add fields to `$fillable`.

Do not use `$guarded = []` unless the project explicitly accepts that pattern and the model is safe.

Escape output by default.

Do not use `{!! !!}` in Blade unless the HTML is sanitized and intentionally trusted.

---

## 13. Mass Assignment and Input Mapping

Do not blindly pass request data into models.

Bad:

```php
User::create($request->all());
$model->update($request->all());
```

Use validated data only:

```php
$data = $request->validated();
$user->update($data);
```

Even validated data should be mapped intentionally when sensitive fields are involved.

Do not casually add fields to `$fillable`.

Never make sensitive fields mass assignable unless explicitly required and protected:

* `is_admin`
* `role`
* `email_verified_at`
* `password`
* `balance`
* `subscription_status`
* `owner_id`
* `user_id`
* permission flags

---

## 14. Exceptions and Error Messages

Separate internal exception messages from user-facing messages.

Internal exception messages may be technical and in English.

User-facing error messages must be localized through language files.

Do not expose raw exception messages to users.

Do not return database errors, stack traces, provider errors, internal class names, file paths, SQL errors, or debug details in UI/API responses.

Bad:

```php
throw new Exception('User subscription row missing from database.');
```

Showing this directly to the user is forbidden.

Good:

```php
throw new MissingSubscriptionStateException($user->id);
```

Then display a localized message:

```php
__('errors.subscription_state_missing')
```

---

## 15. Database Rules

Respect the actual database engine used by the project.

Do not assume SQLite behavior.

If the project uses PostgreSQL, write migrations, queries, casts, JSON operations, indexes, constraints, tests, and raw SQL with PostgreSQL compatibility in mind.

Do not use SQLite in-memory tests as a substitute for PostgreSQL behavior.

SQLite can hide real production bugs involving:

* JSON columns
* enums
* date/time behavior
* constraints
* indexes
* foreign keys
* transactions
* locking
* case sensitivity
* strict typing differences
* PostgreSQL-specific functions

Prefer real PostgreSQL for integration/feature tests when database behavior matters.

Mock external systems or expensive boundaries, not the database behavior that the feature is supposed to prove.

---

## 16. Migrations

Migrations must be safe, reversible when possible, and production-aware.

Before writing a migration, consider:

* existing data
* default values
* nullable vs non-nullable changes
* indexes
* foreign keys
* cascade behavior
* data backfills
* deployment order
* locks on large tables

Do not casually rename or drop columns without considering data migration.

Do not add a non-nullable column without a default to a table that may already contain data.

Use explicit indexes for common lookup/filter columns.

Do not add foreign keys without thinking through delete/update behavior.

---

## 17. Eloquent and Query Performance

Avoid N+1 queries.

Use eager loading intentionally.

Do not eager-load everything just in case.

Use pagination for potentially large lists.

Do not call `all()` on large models unless the dataset is guaranteed small.

Use chunking, cursors, or lazy collections for command/import processing.

Do not run database queries inside Blade loops.

---

## 18. Transactions and Concurrency

Use database transactions when multiple writes must succeed or fail together.

Consider concurrency when code can be triggered by:

* multiple browser tabs
* repeated button clicks
* queue retries
* scheduled commands
* webhooks
* parallel requests

Do not assume a request runs only once.

Use unique constraints, transactions, locks, or idempotency keys when duplicate execution would corrupt data.

For PostgreSQL-specific concurrency needs, prefer explicit PostgreSQL-compatible solutions.

---

## 19. Idempotency

Operations triggered by webhooks, queues, retries, scheduled commands, imports, or payment providers must be idempotent when possible.

The same external event or job retry must not create duplicate records, duplicate emails, duplicate charges, duplicate queue items, or invalid state transitions.

Before creating new records from external events, check for existing identifiers.

---

## 20. State Transitions

Do not change status fields casually.

State transitions must be explicit and valid.

Before modifying a status, check:

* allowed previous states
* allowed next states
* side effects
* authorization
* timestamps
* audit/logging needs
* tests for invalid transitions

Do not represent complex state transitions with scattered string comparisons.

---

## 21. Magic Strings and Enums

Avoid magic strings for statuses, roles, types, modes, providers, permissions, and state values.

Use PHP enums, constants, or existing project conventions.

Bad:

```php
if ($user->status === 'active') {
    // ...
}
```

Better:

```php
if ($user->status === UserStatus::Active) {
    // ...
}
```

Do not introduce enums without checking whether the project already has constants or enum conventions.

---

## 22. Validation and Data Integrity

Backend validation is mandatory for every external input.

External input includes:

* HTTP requests
* API payloads
* uploaded files
* query strings
* route parameters
* webhook payloads
* CLI arguments
* imported CSV/JSON/XML
* data from third-party APIs

Frontend validation is helpful, but never trusted.

Use database constraints for invariants that must never be violated.

Application validation improves user feedback.

Database constraints protect final integrity.

Use both where appropriate.

---

## 23. Events, Listeners, Observers, and Jobs

Do not use events as a default control-flow mechanism.

Use direct method calls for simple sequential workflows.

Events are acceptable when:

* multiple independent reactions must happen after something
* the producer should not know the consumers
* the reaction can be asynchronous
* the reaction is optional or replaceable
* the event represents a real domain/application event

Do not use events to hide business flow.

Bad:

* Controller dispatches event
* Listener performs the actual main operation
* Another listener mutates core state
* Debugging requires jumping through five files

Good:

* Use case creates an order
* Then dispatches `OrderCreated`
* Independent listeners send email, write audit log, notify external system

---

## 24. Model Observers

Do not hide core business behavior in Eloquent observers.

Observers are acceptable for technical side effects or simple lifecycle reactions, but not for essential business workflows that must be visible in the use case.

If an observer changes important state, creates related records, sends notifications, or triggers external effects, reconsider whether the behavior belongs in an explicit action/use-case class.

---

## 25. Queues and Jobs

Queued jobs must be safe to retry.

A failed job retry must not corrupt state or duplicate irreversible side effects.

Jobs should receive stable identifiers, not large serialized models, unless the project intentionally uses that style.

Before adding a job, decide:

* why it must be asynchronous
* what happens on retry
* what happens on failure
* whether it needs uniqueness
* whether it needs a timeout
* whether it needs backoff
* whether it must run after a database transaction commits

---

## 26. Mail and Notifications

Do not send mail, notifications, or external side effects before the database transaction commits.

Use queued notifications/mail when sending may be slow.

All user-facing mail text must be localized.

Tests must fake mail/notifications unless the rendering itself is under test.

---

## 27. Facades, Helpers, and Dependency Injection

Avoid Laravel Facades in core business logic when they make dependencies invisible.

Facades are acceptable in framework glue code, tests, simple controllers, config-adjacent code, or places where Laravel convention clearly improves readability.

Prefer dependency injection for:

* external services
* complex collaborators
* code that needs mocking
* domain/application workflows
* integrations
* time/date providers when time affects behavior
* file storage abstractions
* mail/notification clients
* API clients

Do not use global helpers for business decisions.

Do not add global helper functions unless there is a strong project-wide reason.

Global helpers hide dependencies and are hard to test.

Prefer explicit classes, value objects, or small injected collaborators.

---

## 28. Traits

Do not use traits as dumping grounds for shared logic.

Traits are acceptable for small, cohesive, reusable behavior.

Do not use traits to avoid designing proper dependencies.

Avoid traits that require hidden properties or implicit methods on the consuming class.

If a trait needs many assumptions about the class using it, it is probably the wrong abstraction.

---

## 29. Value Objects

Use value objects for concepts that have rules and meaning beyond primitive strings or integers.

Good candidates:

* money
* date ranges
* locale
* language level
* review interval
* email address
* percentage
* score
* external provider identifier

Do not pass loosely related primitive values through many methods when a value object would make invalid states impossible.

Do not overuse value objects for trivial one-off values.

---

## 30. DTOs

Use DTOs when passing structured data across layers would otherwise rely on unclear arrays.

DTOs are useful for:

* external API payloads
* form/application data
* import rows
* complex command inputs
* data passed into actions

Avoid anonymous associative arrays for important domain/application data.

If using arrays, document array shapes with PHPDoc.

---

## 31. API Responses

Do not return raw Eloquent models from APIs unless the project explicitly does so.

Prefer API Resources or existing response transformers.

Do not accidentally expose internal fields.

API response shapes must remain stable unless the task explicitly asks to change them.

Do not rename JSON keys casually.

Do not mix different response formats for the same type of endpoint.

Use correct HTTP status codes.

Validation errors should follow Laravel conventions unless the project defines another response format.

---

## 32. Route Model Binding

Route model binding does not replace authorization.

When using route model binding, always ensure the bound model belongs to or is accessible by the current user when required.

Do not expose predictable IDs without access checks.

---

## 33. Time and Timezone Handling

Be explicit about timezones.

Do not assume server timezone equals user timezone.

Use Laravel/Carbon helpers consistently.

When behavior depends on current time, tests must control time using Laravel/Carbon time freezing.

Do not scatter `now()` calls across complex workflows if a single consistent timestamp should be used.

Use one captured timestamp:

```php
$now = now();
```

Then pass it through the workflow.

---

## 34. File Storage and Uploads

Do not assume local disk storage.

Use Laravel Storage abstractions.

Validate uploaded files by:

* size
* MIME/type
* extension where relevant
* content expectations if security matters

Do not trust original filenames.

Do not store user uploads in public paths unless they are meant to be public.

---

## 35. External Integrations

External services must be isolated behind explicit clients/adapters.

Do not scatter raw HTTP calls throughout controllers, jobs, models, or views.

Integration code should handle:

* timeouts
* retries where appropriate
* failed responses
* malformed responses
* logging without leaking secrets
* test fakes/mocks

Do not put API keys outside config.

---

## 36. Testing Rules

Write or update tests when changing behavior.

Do not only test the happy path.

Consider tests for:

* authorization failure
* validation failure
* missing records
* ownership boundaries
* edge cases
* empty states
* duplicate submissions
* transaction rollback
* localized output when relevant
* PostgreSQL-specific behavior when relevant

### Do not use SQLite in-memory as fake PostgreSQL

If the project runs on PostgreSQL, tests that depend on database behavior must use PostgreSQL.

Do not fix failing tests by switching to SQLite.

### Mocking and faking

Mock external systems:

* payment providers
* email APIs
* HTTP APIs
* AI APIs
* storage services
* queues when the job execution itself is not under test
* notifications when only dispatch is under test

Do not mock the code being tested.

Do not overmock Laravel internals.

Prefer fakes where Laravel provides reliable fakes:

* `Mail::fake()`
* `Notification::fake()`
* `Queue::fake()`
* `Storage::fake()`
* `Http::fake()`
* `Event::fake()` only when events are not the behavior under test

Be careful with `Event::fake()` because it can hide important model/event behavior.

---

## 37. Factories

Factories must create valid default records.

Use named factory states for meaningful scenarios.

Bad:

```php
User::factory()->create(['status' => 'x']);
```

Good:

```php
User::factory()->suspended()->create();
```

Do not make tests depend on obscure factory defaults.

---

## 38. Seeders

Seeders must not contain production business logic.

Seeders are for development, demo, or test data.

Do not rely on seeders for required production configuration unless the project explicitly uses database seeders for that purpose.

Test data should usually come from factories, not global seeders.

---

## 39. Caching

Do not add caching unless there is a clear reason.

When adding cache, define:

* cache key
* TTL
* invalidation strategy
* user/tenant/locale scope
* stale data risk

Never cache user-specific data under a global key.

Do not cache authorization-dependent responses without including the permission/user context.

---

## 40. Rate Limiting and Abuse Protection

Public endpoints, authentication endpoints, password reset flows, email sending, imports, AI calls, and expensive operations should be rate limited where appropriate.

Do not add expensive unauthenticated endpoints without abuse protection.

Use Laravel's existing rate limiting mechanisms unless the project has a custom approach.

---

## 41. Performance Rules

Avoid obvious performance problems:

* N+1 queries
* unpaginated large lists
* loading entire tables into memory
* repeated expensive calculations
* unnecessary external API calls
* synchronous slow operations during user requests
* missing indexes for common filters/lookups

Do not prematurely optimize.

But do not write code that is clearly unsafe for production data sizes.

---

## 42. File Generation Restraint

Do not generate migrations, models, services, interfaces, events, listeners, jobs, policies, factories, seeders, and tests all at once unless the task truly requires them.

Every new file must have a clear reason.

Before creating a new class, check whether an existing class should be extended or modified instead.

Prefer fewer, clearer files over a generated architecture explosion.

---

## 43. Package and Dependency Rules

Do not add new Composer or NPM packages without strong justification.

Before adding a package, check whether Laravel or the existing project already provides the needed capability.

A package must be justified by:

* meaningful complexity reduction
* active maintenance
* security posture
* compatibility with the current Laravel/PHP version
* testability
* long-term cost

Do not add packages for trivial helpers.

---

## 44. Logging and Debugging

Logs are a primary debugging and behavior-understanding source.

Do not exclude logs from debugging analysis when they are relevant.

When fixing bugs:

1. Inspect the error message.
2. Inspect stack traces.
3. Inspect relevant logs.
4. Identify the failing request/action.
5. Trace the actual runtime path.
6. Then change code.

Do not guess from code alone when logs are available.

Log messages should be useful, contextual, and safe.

Include relevant context:

* user ID when safe
* request ID or correlation ID when available
* model IDs
* action name
* external service name
* non-sensitive payload metadata

Do not log:

* passwords
* tokens
* API keys
* full secrets
* credit card data
* sensitive personal data unless explicitly required and protected

Do not swallow exceptions silently.

If catching an exception, either handle it meaningfully or log it with context.

---

## 45. Final AI Self-Check

Before finalizing any change, verify:

* Did I inspect the existing implementation before changing it?
* Did I avoid inventing architecture?
* Did I use the full SOLID principles correctly?
* Did I avoid hardcoded frontend/user-facing text?
* Did I place translations in the correct language files?
* Are code comments and PHPDoc in English only?
* Did I avoid useless comments?
* Did I avoid vague TODO/FIXME comments?
* Did I avoid direct `env()` usage outside config?
* Did I preserve config cache compatibility?
* Did I avoid unnecessary facades in business logic?
* Did I avoid hiding flow in events, listeners, or observers?
* Did I avoid unnecessary services, interfaces, traits, and helpers?
* Did I check authorization, not only authentication?
* Did I avoid mass-assignment risks?
* Did I use validated input only?
* Did I respect PostgreSQL instead of assuming SQLite?
* Did I avoid N+1 queries?
* Did I consider transactions and concurrency?
* Did I make queued/retried operations idempotent?
* Did I localize validation and error messages?
* Did I avoid exposing internal exception messages?
* Did I add or update tests where behavior changed?
* Did I fake/mock external services in tests?
* Did I avoid mocking the behavior actually under test?
* Did I keep the diff as small as possible?
* Is the code easy for a human Laravel developer to understand?

If any answer is no, revise the solution before finalizing.

