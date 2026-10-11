<?php

namespace App\Console\Commands;

use App\Support\DuckDb;
use Illuminate\Console\Command;

class BootstrapLake extends Command
{
    protected $signature = 'logs:bootstrap-lake';

    protected $description = 'Create the DuckLake logs table and partitioning.';

    public function handle(DuckDb $duckDb): int
    {
        $duckDb->bootstrap();
        $this->components->info('DuckLake is ready.');

        return self::SUCCESS;
    }
}
