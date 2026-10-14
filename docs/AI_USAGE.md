# AI usage and development workflow

## Background and tools

I am a certified prompt engineer. Over the past two years, I have worked on designing, building and testing an AI framework environment. I use AI tools daily.

Rather than provide a file-by-file inventory of AI-assisted changes, I describe the custom modules I used, their contribution and the corrections their output required. Substantial AI assistance was involved in initial test generation, frontend planning and implementation, scaffolding, technical auditing and documentation drafting. Architectural decisions remained my responsibility.

The tools used were my backlog refinement simulator, my test-generation module, IntelliJ IDEA's integrated AI code completion, ChatGPT/Codex for implementation assistance and documentation, Claude Code for debugging, and my custom repository-audit module with Laravel-specific knowledge material.

## 1. Planning: backlog refinement simulation

I have several AI planning tools. For this task, I also used a hybrid tool: a backlog refinement simulator representing a six-person product team with different responsibilities and viewpoints.

Its base prompt defines a product owner, project manager, QA engineer, two developers and a marketing specialist. Their contrasting priorities create a simulated discussion about scope, cost, technical risk and product value. The simulator can move from debate to a structured decision with proposed actions.

I ran several scenarios during planning. In one, I submitted an already drafted implementation outline for discussion and validation. The purpose was to challenge assumptions and examine baseline requirements versus additional features. Simulated agreement was an input to my review, not proof that the plan was correct.

**Accompanying materials:** `GroomingMeetingSimulator.txt` (base prompt) and `BaselineVersusStretchGoalsDebate.pdf` (example result).

## 2. Tests: generated drafts and manual correction

I have used my own test-generation module for nearly two years. Testing was also one of the areas requiring the most additional work in this project.

The module proposes scenarios, identifies dependencies and generates initial test code. However, it repeatedly failed to respect my intended distinction between isolated unit tests and database-backed tests. In particular, it treated an in-memory SQLite database as if it mocked PostgreSQL access. After several attempts to correct this, I still had to rewrite a large proportion of the tests manually.

The following corrective prompt captures the intended boundary:

> SQLite `:memory:` is not a mock of a PostgreSQL connection. It is a real SQLite database running in memory.
>
> Write the tests according to the following rules:
>
> **Unit tests:** Mock database access. Do not use a real database connection, migrations, or the `RefreshDatabase` trait.
>
> **Integration tests:** For now, do not use an isolated PostgreSQL test database. The application is simple enough that I do not want to complicate the test setup with a separate database layer.
>
> **Feature tests:** Depending on the purpose of the test, mock the dependencies or use the test database.
>
> In unit tests, I want to isolate the business logic under test, not run it against a different database engine.

This is an instruction used to correct generated output, not a claim that every draft followed it successfully. Database-backed tests remain useful for integration behavior; they should not be presented as isolated unit tests.

The supplied initial `TargetControllerTest.php` provides another concrete example. In a local verification, eight of its nine tests passed. The remaining test called `getComponent()` and `getProps()` on an Inertia response, although those methods do not exist on that response object. The draft therefore needed correction before acceptance. Similarly, a mock asserting `afterCommit()` does not itself prove that a real job dispatch happens after a transaction commits.

My recurring requirements included data providers for variations of the same behavior, mocked or faked external HTTP calls, and meaningful assertions rather than coverage alone.

**Accompanying materials:** the initial `TargetControllerTest.php`, the `CodeTestArchitect.txt` base prompt and its supporting test-structure, framework-detection and coverage references. The example is a generated draft, not the final application test suite.

## 3. Backend: my architecture, AI-assisted scaffolding

I made the architectural decisions myself. AI generated initial placeholder files according to the structure I had defined. While implementing backend logic, I used IntelliJ's integrated AI tool for live code completion.

I remained responsible for the boundaries between validation, controllers, services, repositories, GitHub communication, persistence and queue processing. A generated abstraction was not sufficient reason to keep it.

For example, I rejected expanding the initial queue integration into a broader operation-tracking solution before the minimal job integration was working. I asked for a smaller working increment first; richer frontend feedback was introduced later. I also rejected a dedicated smoke-test command and disposable test job as unnecessary submission code and removed those artifacts.

## 4. Frontend: substantial AI assistance

I am primarily a backend developer. Although I also take on frontend tasks, I had not previously worked with Vue in this form. My earlier Vue experience was with Quasar and a completely separate frontend. I had also not used TypeScript for approximately six years and do not particularly enjoy working with it.

Consequently, I relied substantially on AI for both frontend planning and implementation in the Vue/Inertia starter-kit environment. The recurring instructions were to use TypeScript, reusable components and existing UI-kit components where appropriate, translated interface text, and avoid unnecessary hardcoded configuration or routes.

I cross-checked AI suggestions through Stack Overflow searches and checked their behavior in the application. I also used TypeScript checks and production builds to catch issues that were not apparent from browser checks alone.

One example was string-valued `tabindex` attributes passed to the starter kit's `TextLink` component. These caused TypeScript errors, and the relevant bindings were corrected to pass numeric values. The actual component contract needed verification even though the original code came from the starter kit.

## 5. Audit: my custom review module

The technical audit was also produced using my own module. Its Laravel-specific knowledge material covers backend layers, frontend integration, design and complexity, database consistency and concurrency, security, performance, runtime tooling and test quality.

I refined its instructions to require actual source inspection, exact revision references, evidence for findings and a search for existing safeguards that might disprove a suspected issue. The review must include configuration, Docker and the Makefile, and distinguish working code, maintainability issues, confirmed defects and unverified behavior.

Earlier generated reviews contained unsupported conclusions and inaccurate descriptions of code. I challenged those results instead of accepting them because an audit tool produced them. The final audit was used to inform the submission summary and known limitations, with its findings evaluated against the implementation.

The final report identified issues including public/private repository visibility, case-insensitive target identity, oversized target-page payloads, disconnected GitHub Actions workflows and legacy synchronization code. It was a source review: it did not independently run the application, tests, builds or a live Redis worker, and did not establish an actual security incident.

**Accompanying materials:** `medior-interview_audit_en.md` and the Laravel audit module's base instructions and supporting knowledge files. These materials are supplied separately from this document.

## 6. Debugging: Claude Code

I also used Claude Code for debugging. In my experience, it is one of the strongest AI tools for this kind of work, particularly when investigating a failure across several files and tracing its cause.

The repository-root [CLAUDE.md](../CLAUDE.md) contains the base prompt and working instructions for its use. These require inspecting the existing implementation before making changes, examining error messages, stack traces and relevant logs, and tracing the actual runtime path before proposing a fix. They also emphasize small changes, preservation of architectural boundaries and behavior, and avoiding secrets in logs.

Debugging suggestions still required my review and verification. The instructions define the expected workflow; they do not guarantee that every generated diagnosis or fix is correct.

## Validation and responsibility

Generated output was reviewed against the application, framework APIs and intended behavior. Validation during implementation included automated tests, faked external HTTP responses, TypeScript checks, builds, browser checks and inspection of persisted database data. The source audit provided an additional review; its execution limits are separate from those implementation checks.

I changed or rejected suggestions that introduced unnecessary complexity, misunderstood test isolation, used nonexistent framework methods or conflicted with the intended scope. Passing tests and coverage were useful evidence, but were not treated as proof that all behavior or architectural decisions were correct.

AI accelerated drafting and implementation, particularly for tests and the unfamiliar frontend stack. It also created correction work. I retain responsibility for the submitted code, architectural decisions and the limitations documented in the [submission summary](SUBMISSION_SUMMARY_AND_KNOWN_LIMITATIONS.md).
