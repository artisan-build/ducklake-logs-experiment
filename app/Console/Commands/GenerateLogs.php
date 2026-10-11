<?php

namespace App\Console\Commands;

use App\Models\LogApp;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateLogs extends Command
{
    protected $signature = 'logs:generate {--apps=5} {--lines=1000} {--days=7} {--batch=250} {--url=}';

    protected $description = 'Simulate multiple Laravel applications shipping realistic log batches to the ingest API.';

    public function handle(Factory $http): int
    {
        $appCount = max(1, (int) $this->option('apps'));
        $lineCount = max(1, (int) $this->option('lines'));
        $days = max(1, (int) $this->option('days'));
        $batchSize = min(500, max(1, (int) $this->option('batch')));
        $url = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        $run = strtolower(Str::random(6));
        $sent = 0;

        for ($appNumber = 1; $appNumber <= $appCount; $appNumber++) {
            [$app, $token] = LogApp::issue("Load app {$run} {$appNumber}", "load-{$run}-{$appNumber}");

            for ($offset = 0; $offset < $lineCount; $offset += $batchSize) {
                $logs = [];

                for ($i = $offset; $i < min($lineCount, $offset + $batchSize); $i++) {
                    $level = match (true) {
                        $i % 101 === 0 => 'error',
                        $i % 17 === 0 => 'warning',
                        $i % 5 === 0 => 'debug',
                        default => 'info',
                    };
                    $logs[] = [
                        'id' => (string) Str::uuid(),
                        'timestamp' => now()->subSeconds(($i * 31 + $appNumber) % ($days * 86400))->toIso8601String(),
                        'level' => $level,
                        'channel' => 'stack',
                        'message' => $level === 'error' ? "Checkout failed for request {$run}-{$appNumber}-{$i}" : "Synthetic {$level} event {$i}",
                        'context' => [
                            'request_id' => "{$run}-{$appNumber}-{$i}",
                            'route' => $i % 3 === 0 ? '/checkout' : '/dashboard',
                            'duration_ms' => 10 + ($i % 1200),
                            'exception' => $level === 'error' ? ['class' => 'RuntimeException', 'trace' => "#0 app/Service.php(42): fail()\n#1 public/index.php(20): run()"] : null,
                        ],
                        'extra' => ['generator' => 'logs:generate'],
                    ];
                }

                $response = $http->withToken($token)->timeout(10)->post($url.'/api/v1/logs', [
                    'idempotency_key' => "{$run}-{$appNumber}-{$offset}",
                    'logs' => $logs,
                ]);

                if (! $response->successful()) {
                    throw new RuntimeException("Ingest returned HTTP {$response->status()} for {$app->slug}.");
                }

                $sent += count($logs);
            }
        }

        $this->components->info("Accepted {$sent} synthetic logs from {$appCount} apps.");

        return self::SUCCESS;
    }
}
