# Frontend and Backend–Frontend Integration Review

## Scope

The frontend is a required part of a full Laravel repository audit when frontend implementation exists in the repository. Review it whether it is embedded in Laravel or maintained as a separate application in the same repository.

Supported scope includes Blade, Livewire, Alpine, Vue, Inertia, React, Angular, Svelte, plain JavaScript/TypeScript, and other discovered technologies. Do not assume Vue/Inertia or require TypeScript. Apply technology-specific rules only when that technology is actually present.

Review every relevant frontend application and shared package. The absence of a frontend under resources/js does not establish that the repository has no frontend. Do not recommend a framework migration as a default remedy.

## Discovery and monorepo inventory

Inspect the whole repository tree and manifests to discover applications, packages, templates, assets, build systems and entry points. Common locations such as frontend/, web/, client/, apps/, packages/ and resources/ are search hints, not an exhaustive list.

For each application/package record:

- Root directory, responsibility, framework/library versions and language.
- Entry points, routing, pages/views and reusable components.
- State stores, hooks/composables, API clients and shared contracts.
- Dependency manifests, lockfiles, workspace membership and build/test commands.
- Runtime model: server-rendered pages, SPA, hybrid, SSR, static output or another actual model.
- Backend base URL, proxy rules, authentication approach and environment configuration.
- Shared packages and cross-application dependencies.

Distinguish generated bundles and third-party code from first-party source. Inspect relevant build metadata even if generated assets are excluded from line-by-line review. Identify production versus development configuration without assuming they behave identically.

If an external frontend is referenced but not contained in the repository, review the available backend contract and record the unavailable client scope. Do not invent the missing application's behavior.

## Layer and design review

Review pages, reusable components, state containers, request clients and presentation helpers separately. Trace their dependencies and end-to-end workflows afterward.

Evaluate responsibility, cohesion, coupling, duplicated behavior, unnecessary abstractions and testability. Apply SOLID where the language/design makes a principle relevant; do not demand class inheritance or interfaces in functional component code merely to resemble backend architecture.

Look for pages mixing rendering, provider protocol details, authorization assumptions and complicated domain state. Extract a boundary only when it has concrete value. A one-use component can improve clarity, while several forwarding components can increase complexity without value.

Check reuse of existing UI components, consistent interaction contracts, error/empty/loading states and localization. Styling preferences are not functional defects. Follow project formatter/linter conventions, not a preferred personal style.

## Blade and server-rendered views

Trace controllers/view composers/components into the rendered template. Review escaping, raw output, included templates, props/attributes, slots and data preparation.

Identify database queries, remote calls or substantial business decisions hidden in templates. Check authorization-sensitive content against backend enforcement, rather than treating a hidden button as a permission boundary.

Inspect forms for correct methods, CSRF protection where required, route generation, old input, validation messages and handling of optional/null values. Review reusable template/component structure without requiring an SPA framework.

Static template inspection can establish an unsafe raw-output path when input trust is known. It cannot prove the exact browser rendering or screen-reader experience without appropriate execution evidence.

## Livewire, Alpine and similar server/browser bridges

Review public state, action authorization, validation and persistence boundaries. Values hidden or disabled in the DOM remain untrusted input. Inspect the actual framework's handling of mutable state and action requests before asserting protection or vulnerability.

Check duplicate event listeners, lifecycle cleanup, request ordering, optimistic state, event-driven side effects and repeated rendering work. Trace frontend events to server actions and the server response back to UI state.

Avoid assuming browser-side validation replaces server-side validation. Verify relevant APIs against installed versions.

## Vue, Inertia and other component frontends

For Vue inspect props, emits, refs/reactivity, computed values, watchers, stores and component lifecycle. For React inspect props, state, hooks, effects, stale closures, dependencies and cleanup. For Angular, Svelte or another framework inspect its equivalent binding, state and lifecycle mechanisms using version-matched references.

Review optional/null values, list keys, state ownership, mutation of shared input, unnecessary recomputation and incompatible component APIs. JavaScript without TypeScript is not automatically unsafe; review contracts and runtime validation directly.

For Inertia trace controller props and shared middleware data to page/component consumers. Check partial reloads, preserved state/scroll, errors, redirects, polling lifetime and history behavior. Do not assume that Inertia page payloads constitute a separately supported public REST API.

For SSR/hydration inspect browser-only API access, server-side state isolation, environment exposure and initial-state consistency. Do not use an SSR rule against a browser-only SPA without evidence that the path runs on the server.

## Separate frontend applications

Review the Laravel backend and each frontend as separate subsystems even when they share a repository. Then review their integration as its own subsystem.

Trace frontend routes to request-client calls, backend routes/middleware, validation, service/persistence behavior, response serialization and resulting client state. Inspect development proxies and deployed URL/path assumptions.

Check workspace builds, dependency resolution, shared types/generated clients, environment variable usage and deployment output. A backend change can break a separately built frontend even if both build independently.

Do not assume cookies, bearer tokens or a particular auth package. Determine the actual mechanism before evaluating CORS, CSRF, session expiry, refresh behavior, logout and permission handling. Native/mobile clients have different browser constraints; distinguish them where present.

## Backend–frontend contracts

For each important API/action establish:

- Route, method, parameters, authentication and authorization.
- Request shape, normalization, enums/options, null versus missing values and validation failures.
- Response shape, status codes, pagination metadata and error contract.
- Dates/timezones, integer/decimal precision, booleans and provider identifiers.
- Retry/idempotency expectations and duplicate submission behavior.
- Which party owns each invariant and how changes remain compatible.

Compare actual payload assembly with actual consumers; type declarations alone do not prove alignment. Validate generated clients against their generation source and revision where relevant.

Client-side permission checks improve UX but do not enforce access. Server-side safe serialization does not protect a separate endpoint or unscoped cache. Inspect both sides of every security-sensitive boundary.

## State, asynchronous operations and errors

Review initial load, submit, loading, success, validation failure, provider/server failure, retry and navigation away. Check repeated clicks, concurrent tabs and out-of-order responses where relevant.

Look for stale filter/search state, resetting pagination incorrectly, optimistic updates that are never rolled back, polling after completion/unmount and requests whose results overwrite newer state. Inspect cancellation or sequencing mechanisms before declaring a race.

A synchronous UI message saying a request was queued must agree with actual server behavior. A completed job needs an appropriate refresh/reconciliation mechanism if the interface promises to show its result automatically.

Error feedback should be safe, actionable and consistent with the backend contract. Do not expose stack traces, tokens or raw provider bodies. Do not require a complex global error manager when a local component already handles the workflow correctly.

## Cache, storage and privacy

Inspect HTTP caching, service workers, browser storage, persistent stores, query caches and server-rendered shared data when present. Establish cache identity, invalidation and user/tenant boundaries.

Check user switching/logout, stale permissions, cached private payloads and hydration state. Removing sensitive elements from visible rendering does not remove sensitive data already sent to the browser.

Do not prescribe clearing every cache indiscriminately. Determine which stored data is private, its lifetime and whether invalidation is actually missing. Avoid declaring a dependency's standard caching behavior unsafe without inspecting configuration and use.

## Rendering and performance

Trace cardinality from backend queries to client payloads and rendering. A v-for/map loop is not proof of unbounded rendering. Parent pagination does not bound nested child data. A collapsed component can still receive a large payload.

Review expensive calculations, duplicate requests, unnecessary hydration, overly large initial bundles, lazy-loading boundaries and redundant dependencies. Separate source-visible growth risks from measured performance defects.

Do not demand virtualization, SSR, memoization or global state by default. Explain the specific workload and trade-offs. If performance was not measured, do not invent browser timings or capacity figures.

## Accessibility and interaction

Review semantic elements, labels, keyboard interaction, focus handling, modal behavior, status/error announcements and control names. Check icon-only controls and error association. Use automated tools and real browser evidence where available.

Different valid bindings for native attributes and typed component props need not be made identical for appearance. Verify actual component contracts and compile/runtime behavior before rewriting them.

Source review can identify missing labels or unreachable controls, but cannot alone establish all contrast, layout and assistive-technology behavior. Record those verification limits explicitly.

## Frontend tests and tooling

Use test_review_rules.md for assertions and mock boundaries. Inspect component interaction tests, state/request-client tests and browser checks where present. Check that fake backend responses match actual backend shapes.

Separate asset build, type checking, linting, formatting and runtime interaction checks. Success in one does not imply success in the others. Review dependency/build scripts before execution and do not auto-fix source or install new tools during an audit.

A missing browser test is not automatically a production bug. Identify the important interaction or boundary that remains unproven, with a proportionate suggested check.

## Reporting requirements

List each frontend application/package in the coverage matrix, alongside the backend and their integration. A review that ignores a separate frontend directory is incomplete even if the Laravel files were fully read.

For each frontend subsystem report verified strengths, working limitations/debt, confirmed defects and unresolved checks. Attach exact source evidence and, for contract issues, cite both client and server code.

Do not invent a frontend finding merely to populate the report. Do not omit the frontend because the plugin specializes in Laravel; Laravel-connected frontend behavior is part of the implementation.
