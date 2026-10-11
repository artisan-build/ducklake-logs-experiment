<?php

namespace App\Console\Commands;

use App\Models\LogApp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProbeDriver extends Command
{
    protected $signature = 'logs:probe-driver {--url=http://127.0.0.1:8000}';

    protected $description = 'Send one line through the installed path-package log driver without displaying its token.';

    public function handle(): int
    {
        $slug = 'driver-probe-'.strtolower(Str::random(6));
        [, $token] = LogApp::issue('Driver probe', $slug);
        config(['app.url' => 'http://driver-consumer.invalid']);

        Log::build([
            'driver' => 'ducklake',
            'endpoint' => rtrim((string) $this->option('url'), '/'),
            'token' => $token,
        ])->error('Driver round trip', ['source' => 'path-package', 'probe_slug' => $slug]);

        app()->terminate();
        $this->components->info("Driver buffered and terminated for {$slug}.");

        return self::SUCCESS;
    }
}
