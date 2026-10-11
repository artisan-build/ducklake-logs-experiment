<?php

namespace App\Console\Commands;

use App\Support\DuckDb;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildLogQueryPlane extends Command
{
    protected $signature = 'logs:rebuild-query-plane';

    protected $description = 'Rebuild event locators and hourly counts from the durable lake.';

    public function handle(DuckDb $duckDb): int
    {
        DB::table('log_event_locators')->truncate();
        DB::table('log_hourly_counts')->truncate();

        $dates = $duckDb->query('SELECT DISTINCT event_date::VARCHAR AS event_date FROM lake.logs ORDER BY event_date;', 300);

        foreach ($dates as $date) {
            $literal = $duckDb->literal((string) $date['event_date']);
            $duckDb->queryWithApplicationDatabase(<<<SQL
                INSERT INTO appdb.public.log_event_locators
                SELECT event_id::UUID, app_slug, occurred_at
                FROM lake.logs
                WHERE event_date = CAST({$literal} AS DATE);
                SELECT count(*) AS inserted FROM lake.logs WHERE event_date = CAST({$literal} AS DATE);
                SQL, 600);
            $this->components->info('Indexed '.$date['event_date'].'.');
        }

        $duckDb->queryWithApplicationDatabase(<<<'SQL'
            INSERT INTO appdb.public.log_hourly_counts (bucket, app_slug, level, count, created_at, updated_at)
            SELECT date_trunc('hour', occurred_at), app_slug, level, count(*), now(), now()
            FROM lake.logs
            GROUP BY ALL;
            SELECT count(*) AS groups FROM appdb.public.log_hourly_counts;
            SQL, 600);

        $this->components->info('Query-plane tables rebuilt.');

        return self::SUCCESS;
    }
}
