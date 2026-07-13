# Changelog

All notable changes to `queue-sql` are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.0.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-07-13

### Added
- Config-driven defaults for **all** `queue(...)` params. `config/queue-sql.php` now also
  supplies `tries`, `backoff`, `throttle`, and `delay` (in addition to `chunk`, `connection`,
  `queue`). Precedence per param: explicit `queue(...)` arg > config default > built-in fallback.
- Batch names now carry the target table: `queue-sql:{operation}:{table}` (e.g.
  `queue-sql:delete:users`) instead of the bare `queue-sql:delete`, so batches are
  distinguishable in `job_batches` / Horizon.
- `dryRun()` now reports the resolved `table` alongside `operation`.

### Changed
- CI now also runs the full suite against real Postgres and MySQL (in addition to SQLite), so
  engine-specific behavior (`MIN/MAX`, `whereBetween` boundaries, grammar) is covered.

## [1.0.0] - 2026-07-08

### Added
- `queue(...)` macro on both the Query Builder and the Eloquent Builder.
- Terminal operations: `delete()`, `update($values)`, `insert($rows)`, each returning a
  deferred `QueuedOperation` that dispatches only on `->dispatch()`.
- Primary-key range fan-out: one batched job per `chunk`-sized key range, run in parallel to
  avoid long table locks.
- Single-job fallback for non-orderable primary keys (UUID / composite).
- `QuerySnapshot` — captures WHERE constraints as a compiled SQL fragment + bindings, so every
  `where` type (flat, nested closures, `whereHas`, `whereExists`, sub-selects) is supported and
  crosses the queue boundary safely and injection-free.
- `queue(...)` parameters: `chunk`, `tries`, `backoff`, `onConnection`, `onQueue`, `throttle`,
  `delay`.
- Batch callbacks: `then()`, `catch()`, `finally()`, `allowFailures()`, plus `dryRun()` for a
  no-dispatch plan preview.
- Support for Laravel 10, 11, 12, and 13 on PHP 8.1+.

[Unreleased]: https://github.com/kamranata/queue-sql/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/kamranata/queue-sql/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/kamranata/queue-sql/releases/tag/v1.0.0
