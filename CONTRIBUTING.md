# Contributing

Thanks for considering a contribution to `queue-sql`. Bug reports, feature ideas, and pull
requests are all welcome.

## Reporting bugs

Open an issue with:

- the Laravel and PHP versions you are running,
- a minimal reproduction (the query and the `queue(...)` call),
- what you expected and what actually happened.

## Development setup

```bash
git clone https://github.com/kamranata/queue-sql
cd queue-sql
composer install
vendor/bin/phpunit
```

Tests run on an in-memory SQLite database via Orchestra Testbench — no external services
required. To reproduce the CI cross-database checks locally, point the suite at a real engine
with env vars (driver behavior such as `MIN/MAX` and `whereBetween` boundaries is
engine-specific):

```bash
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 \
DB_DATABASE=queue_sql_test DB_USERNAME=postgres DB_PASSWORD=postgres vendor/bin/phpunit

DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_DATABASE=queue_sql_test DB_USERNAME=root DB_PASSWORD=root vendor/bin/phpunit
```

## Pull requests

- **Write a test first.** Every behavior change comes with a test that fails before your
  change and passes after. Tests assert real behavior (row counts after a run), not mocks.
- **Keep the full suite green.** Run `vendor/bin/phpunit` before pushing; output must be clean.
- **Match the existing style and scope.** This package is write-operations only and avoids
  adding configuration or abstractions before a real use case needs them.
- **Update the docs.** If you change behavior, update `README.md` and add a `CHANGELOG.md`
  entry under `Unreleased`.
- Keep pull requests focused — one concern per PR is easier to review and merge.

## Supported versions

The CI matrix runs the suite against Laravel 10, 11, 12, and 13 across PHP 8.1–8.4. A separate
`cross-database` job also runs the suite against real Postgres and MySQL. A change must pass on
every supported combination.

## Coding conventions

- Namespace `QueueSql\`, PSR-4 autoloaded from `src/`.
- One clear responsibility per class; small, focused files.
- Jobs configure retries via the public `$tries` / `$backoff` properties (Laravel has no
  fluent `tries()` setter).
