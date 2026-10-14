# Security and Data Boundary Review

## Evidence-based security review

For each suspected vulnerability identify the actor, reachable entry point, trust boundary, controlled inputs, required privileges, existing protections, exposed capability/data and exploit conditions. Distinguish a demonstrated exposure path from a confirmed real-world incident.

Do not label unfamiliar architecture a vulnerability. Do not assign critical severity simply because the issue mentions authentication.

## Authentication and authorization

Trace route middleware, request authorization, binding, controller/service checks and alternate callers. A bound model is not necessarily accessible to the user. A validated identifier is not necessarily authorized.

Explicit ownership/subscription checks, policies and gates can all be valid. Report missing or inconsistent behavior, not the absence of a preferred Laravel abstraction. Public shared resources may legitimately allow any authenticated user to subscribe; prior ownership is not a universal prerequisite.

For multi-tenant data, scope detail/list/count/export endpoints, cache entries, filter options and background jobs consistently. Review membership changes between dispatch and execution.

## Sensitive state and provider credentials

Inspect token storage, encryption, hidden/serialized attributes, request logs, exceptions, job payloads and exports. Encryption at rest does not prevent disclosure through a response or a worker's mutable client state.

Determine whether execution uses current credentials or a captured snapshot and whether that matches expectations. Never include tokens in findings. If alleging a committed secret, verify tracking and distinguish examples/placeholders from live secrets.

## Public/private external data

Provider credentials can broaden accessible data. An endpoint default may include private resources even when the application intends to import public data.

Trace request parameters, provider documented semantics, returned visibility fields, persistence and later readers. A shared target/subscription model is safe only if shared data is appropriate for every subscriber.

Where public-only data is required, consider explicit provider filtering and a persistence-boundary visibility check. A future filter does not remove already imported private rows; remediation should account for existing data if exposure actually occurred.

## Input and output boundaries

- Check SQL values and allowlisted dynamic identifiers; raw string interpolation can bypass binding.
- Check escaped template output, raw HTML, Markdown rendering and rich content sanitization.
- Inspect CSRF protection for cookie-authenticated mutations and separate webhook authentication rules.
- Validate outbound destinations for server-side URL fetching; assess SSRF against actual network access and redirect behavior.
- Inspect shell commands for argument injection, interpolation and exposure of credentials.
- Review uploads for type/size validation, storage paths, public exposure and execution risk.
- Review open redirects, cookie/session configuration and authentication rate limits.

Do not report XSS for normally escaped Vue/Blade interpolation without identifying a bypass. A token-authenticated API and a cookie-authenticated form may require different protections.

## Mass assignment and serialization

Follow validated data into writes. Inspect fillable/guarded attributes and explicit mapping of owner, role, privilege and relationship IDs. Broad assignment can be a real risk, but the vulnerability requires a reachable sensitive assignment path.

Review API/Inertia/resource output independently. Hidden model fields do not protect manually assembled payloads or logs. Select only intended public fields; do not expose raw provider responses by default.

## Cache and frontend data

Client-side hidden controls are not authorization. Sensitive information may leak through page props, cached pages, local storage, browser history, persisted stores and error messages even when not visibly rendered.

Inspect cache-key scope, shared HTTP caches, user switching, logout behavior and stale permission decisions. A filter scoped on the database can still leak through an unscoped cached result.

## Dependency and environment risks

Assess dependency findings against locked versions, affected functionality, exploit reachability, and runtime versus build-only use. Development-only classification does not automatically make a finding harmless; development tooling may still process untrusted input or expose a server.

Inspect debug/error output, exposed services, environment defaults and runtime secrets. Do not invent production misconfiguration from a deliberately local-only configuration. Distinguish missing production evidence from confirmed exposure.

## Reference use

Use version-matched Laravel authorization/security behavior and relevant OWASP guidance from `reference_sources.md`. Recommendations should close the demonstrated boundary, with regression coverage where practical, rather than adding a policy/interface merely for appearance.
