<?php

namespace App\Console\Commands;

use App\Support\DuckDb;
use Illuminate\Console\Command;

class CompactLake extends Command
{
    protected $signature = 'logs:compact';

    protected $description = 'Checkpoint DuckLake, compacting files and expiring eligible data.';

    public function handle(DuckDb $duckDb): int
    {
        $duckDb->query("CALL lake.set_option('expire_older_than', '7 days'); CALL lake.set_option('delete_older_than', '7 days'); CHECKPOINT; SELECT count(*) snapshots FROM lake.ducklake_snapshots();", 300);
        $this->components->info('DuckLake checkpoint completed.');

        return self::SUCCESS;
    }
}
