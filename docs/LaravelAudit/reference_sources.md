# Official Reference Sources

## How to use references

Identify installed versions from the reviewed manifests/lockfiles before opening documentation. URLs below use Laravel 12 and PHPUnit 11.5 as concrete examples, not a requirement to audit all applications against those versions. Choose the matching documentation version for each repository.

Use official sources to establish framework/provider behavior. The project's actual dependency source is useful when exact methods, signatures or defaults determine a finding. A URL in this file is not proof that the auditor retrieved and read its contents.

Do not silently use latest-version behavior for an older installation. Record source/version when it materially supports a finding. Distinguish framework documentation from this package's normative review policy.

## Laravel: application layers

- [Request lifecycle](https://laravel.com/docs/12.x/lifecycle)
- [Routing](https://laravel.com/docs/12.x/routing)
- [Controllers](https://laravel.com/docs/12.x/controllers)
- [Middleware](https://laravel.com/docs/12.x/middleware)
- [Validation and Form Requests](https://laravel.com/docs/12.x/validation)
- [Service container](https://laravel.com/docs/12.x/container)
- [Service providers](https://laravel.com/docs/12.x/providers)
- [Facades](https://laravel.com/docs/12.x/facades)
- [Error handling](https://laravel.com/docs/12.x/errors)
- [Logging](https://laravel.com/docs/12.x/logging)

## Laravel: data, security and runtime

- [Eloquent models](https://laravel.com/docs/12.x/eloquent)
- [Relationships and eager loading](https://laravel.com/docs/12.x/eloquent-relationships)
- [Casts](https://laravel.com/docs/12.x/eloquent-mutators)
- [Serialization](https://laravel.com/docs/12.x/eloquent-serialization)
- [API resources](https://laravel.com/docs/12.x/eloquent-resources)
- [Query builder](https://laravel.com/docs/12.x/queries)
- [Database connections and transactions](https://laravel.com/docs/12.x/database)
- [Migrations, indexes and constraints](https://laravel.com/docs/12.x/migrations)
- [Pagination](https://laravel.com/docs/12.x/pagination)
- [Authentication](https://laravel.com/docs/12.x/authentication)
- [Authorization](https://laravel.com/docs/12.x/authorization)
- [CSRF](https://laravel.com/docs/12.x/csrf)
- [HTTP client](https://laravel.com/docs/12.x/http-client)
- [Queues and failed jobs](https://laravel.com/docs/12.x/queues)
- [Events](https://laravel.com/docs/12.x/events)
- [Cache and atomic locks](https://laravel.com/docs/12.x/cache)
- [Configuration](https://laravel.com/docs/12.x/configuration)
- [Deployment](https://laravel.com/docs/12.x/deployment)

## Testing and PHP

- [Laravel testing](https://laravel.com/docs/12.x/testing)
- [HTTP tests](https://laravel.com/docs/12.x/http-tests)
- [Database testing](https://laravel.com/docs/12.x/database-testing)
- [Mocking](https://laravel.com/docs/12.x/mocking)
- [PHPUnit 11.5: writing tests and data providers](https://docs.phpunit.de/en/11.5/writing-tests-for-phpunit.html)
- [PHPUnit test doubles](https://docs.phpunit.de/en/11.5/test-doubles.html)
- [Pest documentation](https://pestphp.com/docs)
- [PHP type declarations](https://www.php.net/manual/en/language.types.declarations.php)
- [PHP exceptions](https://www.php.net/manual/en/language.exceptions.php)
- [PSR-12 style guidance](https://www.php-fig.org/psr/psr-12/)

Read the actual test runner version/configuration. Use project formatter rules when they differ from general style guidance.

## Databases and infrastructure

- [PostgreSQL query plans](https://www.postgresql.org/docs/current/using-explain.html)
- [PostgreSQL indexes](https://www.postgresql.org/docs/current/indexes.html)
- [PostgreSQL concurrency control](https://www.postgresql.org/docs/current/mvcc.html)
- [MySQL reference manual](https://dev.mysql.com/doc/refman/8.4/en/)
- [SQLite documentation](https://www.sqlite.org/docs.html)
- [Redis documentation](https://redis.io/docs/latest/)
- [Docker Compose reference](https://docs.docker.com/reference/compose-file/)
- [GNU Make manual](https://www.gnu.org/software/make/manual/make.html)

Links using current/latest are discovery entry points. Select the reviewed engine version when behavior differs.

## Security references

- [OWASP authorization guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)
- [OWASP multi-tenant security](https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html)
- [OWASP injection prevention](https://cheatsheetseries.owasp.org/cheatsheets/Injection_Prevention_Cheat_Sheet.html)
- [OWASP XSS prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [OWASP SSRF prevention](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP logging guidance](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)

Use these to understand a concrete reachable vulnerability, not to generate a generic security checklist as findings.

## Frontend and external integrations

Frontend discovery and review requirements are defined in `frontend_review_rules.md`. Use the documentation for the actual discovered framework and version, not only Vue/Inertia.

- [Blade templates](https://laravel.com/docs/12.x/blade)
- [Livewire documentation](https://livewire.laravel.com/docs)
- [Alpine documentation](https://alpinejs.dev/start-here)
- [React documentation](https://react.dev/learn)
- [Angular documentation](https://angular.dev/overview)
- [Svelte documentation](https://svelte.dev/docs)

- [Vue documentation](https://vuejs.org/guide/introduction.html)
- [Inertia documentation](https://inertiajs.com/)
- [TypeScript documentation](https://www.typescriptlang.org/docs/)
- [GitHub organization repository endpoint](https://docs.github.com/en/rest/repos/repos#list-organization-repositories)

Include the actual stack/provider only where present in the reviewed project. Consult installed dependency code for exact Inertia methods or Vue library prop types.

## Packaging the knowledge material

`plugin_instructions.md` is the main instruction text. The other documents are task-specific reference material. Read `audit_workflow.md` first and retrieve the relevant topic references as the audit proceeds.

This directory is a knowledge/instruction package for the plugin creator, not an installed plugin or a runnable benchmark project. It does not contain application code changes.

OpenAI's official documentation describes reusable plugin instructions and reference files: [Moving custom GPT workflows to plugins](https://learn.chatgpt.com/docs/migrate-custom-gpts).
