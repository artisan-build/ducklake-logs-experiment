<?php

namespace App\Console\Commands;

use App\Support\DuckDb;
use Illuminate\Console\Command;
use RuntimeException;

class ReshapeLake extends Command
{
    protected $signature = 'logs:reshape-lake';

    protected $description = 'Rewrite logs into day partitions with app/time sorted files.';

    public function handle(DuckDb $duckDb): int
    {
        $archive = $duckDb->query("SELECT count(*) AS tables FROM information_schema.tables WHERE table_schema = 'main' AND table_name = 'logs_phase1_archive';", 60);

        if ((int) ($archive[0]['tables'] ?? 0) !== 0) {
            throw new RuntimeException('Phase-one archive already exists; refusing to reshape twice.');
        }

        $duckDb->query(<<<'SQL'
            DROP TABLE IF EXISTS lake.logs_phase2;
            CREATE TABLE lake.logs_phase2 (
                event_id VARCHAR,
                app_id BIGINT,
                app_slug VARCHAR,
                event_date DATE,
                occurred_at TIMESTAMPTZ,
                level VARCHAR,
                channel VARCHAR,
                message VARCHAR,
                context JSON,
                extra JSON,
                ingested_at TIMESTAMPTZ
            );
            ALTER TABLE lake.logs_phase2 SET PARTITIONED BY (event_date);
            ALTER TABLE lake.logs_phase2 SET SORTED BY (app_slug ASC, occurred_at ASC);
            CALL lake.set_option('target_file_size', '32MB', table_name => 'logs_phase2');
            SQL, 120);

        $dates = $duckDb->query('SELECT DISTINCT event_date::VARCHAR AS event_date FROM lake.logs ORDER BY event_date;', 300);

        foreach ($dates as $date) {
            $literal = $duckDb->literal((string) $date['event_date']);
            $duckDb->query("INSERT INTO lake.logs_phase2 SELECT * FROM lake.logs WHERE event_date = CAST({$literal} AS DATE);", 300);
            $this->components->info('Rewrote '.$date['event_date'].'.');
        }

        $counts = $duckDb->query('SELECT (SELECT count(*) FROM lake.logs) AS source_rows, (SELECT count(*) FROM lake.logs_phase2) AS rewritten_rows;', 300);
        $stats = $counts[array_key_last($counts)] ?? [];

        if (($stats['source_rows'] ?? null) !== ($stats['rewritten_rows'] ?? null)) {
            throw new RuntimeException('Rewritten row count does not match the phase-one archive.');
        }

        $files = $duckDb->query(<<<'SQL'
            BEGIN;
            ALTER TABLE lake.logs RENAME TO logs_phase1_archive;
            ALTER TABLE lake.logs_phase2 RENAME TO logs;
            COMMIT;
            SELECT
                (SELECT count(*) FROM ducklake_list_files('lake', 'logs_phase1_archive')) AS source_files,
                (SELECT count(*) FROM ducklake_list_files('lake', 'logs')) AS rewritten_files;
            SQL, 120);
        $stats = array_merge($stats, $files[array_key_last($files)] ?? []);

        $this->line((string) json_encode($stats, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
