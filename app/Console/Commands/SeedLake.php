<?php

namespace App\Console\Commands;

use App\Models\LogApp;
use App\Support\DuckDb;
use Illuminate\Console\Command;

class SeedLake extends Command
{
    protected $signature = 'logs:seed-lake {--apps=40} {--rows=1000000} {--days=30}';

    protected $description = 'Generate a large deterministic synthetic corpus directly in DuckLake for query benchmarks.';

    public function handle(DuckDb $duckDb): int
    {
        $apps = max(1, (int) $this->option('apps'));
        $rows = max(1, (int) $this->option('rows'));
        $days = max(1, (int) $this->option('days'));

        for ($i = 1; $i <= $apps; $i++) {
            LogApp::query()->firstOrCreate(
                ['slug' => sprintf('benchmark-app-%02d', $i)],
                ['name' => "Benchmark app {$i}", 'token_hash' => hash('sha256', "benchmark-disabled-{$i}")],
            );
        }

        $duckDb->bootstrap();
        $duckDb->query(<<<SQL
            INSERT INTO lake.logs
            SELECT
                uuid()::VARCHAR,
                (i % {$apps}) + 1,
                printf('benchmark-app-%02d', (i % {$apps}) + 1),
                (now() - ((i % ({$days} * 86400)) * INTERVAL '1 second'))::DATE,
                now() - ((i % ({$days} * 86400)) * INTERVAL '1 second'),
                CASE WHEN i % 101 = 0 THEN 'error' WHEN i % 17 = 0 THEN 'warning' WHEN i % 5 = 0 THEN 'debug' ELSE 'info' END,
                'stack',
                CASE WHEN i % 101 = 0 THEN 'Checkout failed while charging card request-' || i::VARCHAR ELSE 'Synthetic request completed request-' || i::VARCHAR END,
                json_object('request_id', 'request-' || i::VARCHAR, 'duration_ms', 10 + (i % 1200), 'route', CASE WHEN i % 3 = 0 THEN '/checkout' ELSE '/dashboard' END),
                json_object('generator', 'logs:seed-lake'),
                now()
            FROM range({$rows}) rows(i);
            SELECT count(*) AS total_rows FROM lake.logs;
            SQL, 900);

        $this->components->info("Inserted {$rows} rows across {$apps} applications and {$days} days.");

        return self::SUCCESS;
    }
}
