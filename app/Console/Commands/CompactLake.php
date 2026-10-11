<?php

namespace App\Console\Commands;

use App\Support\DuckDb;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompactLake extends Command
{
    protected $signature = 'logs:compact';

    protected $description = 'Checkpoint DuckLake, compacting files and expiring eligible data.';

    public function handle(DuckDb $duckDb): int
    {
        $before = $duckDb->query("SELECT count(*) AS files FROM ducklake_list_files('lake', 'logs');", 300);
        $result = $duckDb->query(
            "CALL lake.set_option('target_file_size', '32MB', table_name => 'logs'); "
            ."CALL ducklake_merge_adjacent_files('lake', 'logs', max_compacted_files => 3); "
            ."CALL lake.set_option('expire_older_than', '30 days'); "
            ."CALL lake.set_option('delete_older_than', '30 days'); "
            ."SELECT count(*) AS files FROM ducklake_list_files('lake', 'logs');",
            300,
        );

        $filesBefore = (int) ($before[0]['files'] ?? 0);
        $filesAfter = (int) (($result[array_key_last($result)]['files'] ?? 0));

        Log::info('DuckLake scheduled maintenance completed.', [
            'files_before' => $filesBefore,
            'files_after' => $filesAfter,
        ]);

        $this->components->info("DuckLake maintenance completed ({$filesBefore} -> {$filesAfter} files).");

        return self::SUCCESS;
    }
}
