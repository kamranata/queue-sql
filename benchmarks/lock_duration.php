<?php

/**
 * queue-sql benchmark — longest single-statement (lock) duration.
 *
 * A plain mass delete runs ONE statement that holds a lock for its whole duration.
 * queue-sql splits the same work into many small statements, so the longest single
 * lock is a fraction of the baseline. This script measures exactly that.
 *
 * Usage:
 *   php benchmarks/lock_duration.php [rows] [chunk]
 *   php benchmarks/lock_duration.php 200000 5000
 *
 * Defaults to a temporary SQLite file. For results that reflect real lock contention,
 * point it at MySQL or Postgres via env vars:
 *   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=bench DB_USERNAME=root DB_PASSWORD=secret \
 *   php benchmarks/lock_duration.php 500000 10000
 *
 * Note: this runs the chunks serially to isolate per-statement lock time. In production
 * queue-sql dispatches them across parallel workers, so wall-clock is lower still.
 */

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use QueueSql\RangePlanner;

$rows  = (int) ($argv[1] ?? 100000);
$chunk = (int) ($argv[2] ?? 5000);

$capsule = new Capsule;
$capsule->addConnection([
    'driver'   => getenv('DB_CONNECTION') ?: 'sqlite',
    'database' => getenv('DB_DATABASE') ?: (sys_get_temp_dir() . '/queue_sql_bench.sqlite'),
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('DB_PORT') ?: null,
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset'  => 'utf8mb4',
    'prefix'   => '',
]);
$capsule->setAsGlobal();

$db     = $capsule->getConnection();
$driver = $db->getDriverName();

if ($driver === 'sqlite') {
    $file = getenv('DB_DATABASE') ?: (sys_get_temp_dir() . '/queue_sql_bench.sqlite');
    if (! file_exists($file)) {
        touch($file);
    }
}

function reseed(Capsule $capsule, int $rows): void
{
    $db     = $capsule->getConnection();
    $schema = $db->getSchemaBuilder();

    $schema->dropIfExists('benchmark_rows');
    $schema->create('benchmark_rows', function ($t) {
        $t->increments('id');
        $t->boolean('flag')->default(true);
        $t->string('name')->nullable();
    });

    $batch = [];
    for ($i = 1; $i <= $rows; $i++) {
        $batch[] = ['flag' => true, 'name' => 'row' . $i];
        if (count($batch) === 1000) {
            $db->table('benchmark_rows')->insert($batch);
            $batch = [];
        }
    }
    if ($batch) {
        $db->table('benchmark_rows')->insert($batch);
    }
}

echo "queue-sql benchmark — longest single-statement (lock) duration\n";
echo str_repeat('-', 62) . "\n";
echo sprintf("driver: %s   rows: %s   chunk: %s\n\n", $driver, number_format($rows), number_format($chunk));

// Baseline: one big DELETE (holds a lock for its entire duration).
reseed($capsule, $rows);
$t0      = microtime(true);
$db->table('benchmark_rows')->where('flag', true)->delete();
$syncMs  = (microtime(true) - $t0) * 1000;

// queue-sql style: fan out into chunk-sized primary-key ranges.
reseed($capsule, $rows);
$builder = $db->table('benchmark_rows')->where('flag', true);
$ranges  = (new RangePlanner($builder, 'id'))->plan($chunk);

$total = 0.0;
$max   = 0.0;
foreach ($ranges as [$start, $end]) {
    $t  = microtime(true);
    $db->table('benchmark_rows')
        ->where('flag', true)
        ->whereBetween('id', [$start, $end])
        ->delete();
    $ms     = (microtime(true) - $t) * 1000;
    $total += $ms;
    $max    = max($max, $ms);
}

$jobs   = count($ranges);
$factor = $max > 0 ? $syncMs / $max : 0;

echo sprintf("baseline  (1 statement)      longest lock: %8.1f ms\n", $syncMs);
echo sprintf("queue-sql (%d statements)    longest lock: %8.1f ms   (total work: %.1f ms)\n", $jobs, $max, $total);
echo "\n";
echo sprintf("longest single lock reduced ~ %.1fx  (%.1f ms -> %.1f ms)\n", $factor, $syncMs, $max);

$db->getSchemaBuilder()->dropIfExists('benchmark_rows');
