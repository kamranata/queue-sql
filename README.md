<p align="center"><img width="420" src="art/logo.png" alt="queue-sql"></p>

<p align="center">
<a href="https://github.com/kamranata/queue-sql/actions"><img src="https://github.com/kamranata/queue-sql/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/atayevkamran/queue-sql"><img src="https://img.shields.io/packagist/dt/atayevkamran/queue-sql" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/atayevkamran/queue-sql"><img src="https://img.shields.io/packagist/v/atayevkamran/queue-sql" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/atayevkamran/queue-sql"><img src="https://img.shields.io/packagist/l/atayevkamran/queue-sql" alt="License"></a>
</p>

Queue any Laravel write query — `delete`, `update`, `insert` — and run it across
parallel batched jobs. Built for large-scale mutations without long locks.

## Install

```bash
composer require atayevkamran/queue-sql
php artisan queue:batches-table   # required: job_batches table
php artisan migrate
php artisan vendor:publish --tag=queue-sql-config   # optional
```

## Usage

```php
use App\Models\User;

User::where('is_blocked', true)
    ->queue(chunk: 5000, tries: 3, backoff: 30, onQueue: 'cleanup', throttle: 10)
    ->delete()
    ->then(fn () => Log::info('done'))
    ->catch(fn (Throwable $e) => Log::error($e))
    ->dispatch();

// Preview without dispatching:
User::where('is_blocked', true)->queue(chunk: 5000)->delete()->dryRun();
// => ['operation' => 'delete', 'jobs' => 40, 'ranges' => 40, 'estimatedRows' => 190234]

// Update:
User::where('last_login', '<', now()->subYear())
    ->queue(chunk: 2000)
    ->update(['status' => 'dormant'])
    ->dispatch();

// Insert (fans out over the row array):
DB::table('imports')->queue(chunk: 1000)->insert($millionRows)->dispatch();
```

## Parameters (`queue(...)`)

| Param | Meaning | Default |
|---|---|---|
| `chunk` | rows per job | `1000` |
| `tries` | per-job retries | `1` |
| `backoff` | seconds between retries | `0` |
| `onConnection` | queue connection | default |
| `onQueue` | queue name | default |
| `throttle` | max jobs/second | none |
| `delay` | seconds before jobs start | none |

## Limitations (v1)

- **Reads are not supported** — write operations only.
- **Fan-out needs an incrementing integer primary key.** Other keys fall back to a
  single job (still queued). All `where` types (including nested closures, `whereHas`,
  `whereExists`, sub-selects) are supported.
- **`insert` is not idempotent** — a retry can duplicate rows. Guard with unique
  indexes / `insertOrIgnore` at the DB level.
- **Throttle/delay staggering relies on the queue driver honoring per-job delay** —
  the database and redis drivers do; SQS caps delay at 15 minutes.
- **Fan-out plans the primary-key range at dispatch time** — rows inserted afterward
  with a key above the captured max are not processed by that run.

## Requirements

Laravel 10–13, PHP 8.1+.
