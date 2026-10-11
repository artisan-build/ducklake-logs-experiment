<?php

namespace App\Console\Commands;

use App\Services\FlushLogBatches;
use Illuminate\Console\Command;

class FlushLogs extends Command
{
    protected $signature = 'logs:flush {--limit=}';

    protected $description = 'Flush staged log batches from Postgres into DuckLake.';

    public function handle(FlushLogBatches $flush): int
    {
        $result = $flush->run($this->option('limit') === null ? null : (int) $this->option('limit'));
        $this->components->info("Flushed {$result['rows']} rows from {$result['batches']} batches.");

        return self::SUCCESS;
    }
}
