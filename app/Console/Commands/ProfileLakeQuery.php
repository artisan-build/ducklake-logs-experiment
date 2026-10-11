<?php

namespace App\Console\Commands;

use App\Support\DuckDb;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ProfileLakeQuery extends Command
{
    protected $signature = 'logs:profile-query {shape=literal : literal, regex, aggregate, or errors} {--runs=3}';

    protected $description = 'Safely profile request startup, catalog attach, and object-store work.';

    public function handle(DuckDb $duckDb): int
    {
        $shape = (string) $this->argument('shape');
        $result = $duckDb->profileQuery($this->sql($shape), (int) $this->option('runs'), 180);
        $result['shape'] = $shape;

        $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function sql(string $shape): string
    {
        $range = "event_date BETWEEN CAST(now() - INTERVAL '24 hours' AS DATE) AND CAST(now() AS DATE) AND occurred_at >= now() - INTERVAL '24 hours' AND occurred_at <= now()";

        return match ($shape) {
            'literal' => "SELECT event_id, app_slug, occurred_at, level, channel, message, context, extra FROM lake.logs WHERE {$range} AND contains(lower(message), 'checkout') ORDER BY occurred_at DESC LIMIT 100",
            'regex' => "SELECT event_id, app_slug, occurred_at, level, channel, message, context, extra FROM lake.logs WHERE {$range} AND regexp_matches(message, 'checkout.*(failed|completed)', 'i') ORDER BY occurred_at DESC LIMIT 100",
            'aggregate' => "SELECT date_trunc('hour', occurred_at) bucket, app_slug, level, count(*) count FROM lake.logs WHERE event_date BETWEEN CAST(now() - INTERVAL '7 days' AS DATE) AND CAST(now() AS DATE) AND occurred_at >= now() - INTERVAL '7 days' AND occurred_at <= now() GROUP BY ALL ORDER BY bucket DESC, app_slug, level",
            'errors' => "SELECT event_id, app_slug, occurred_at, level, channel, message, context, extra FROM lake.logs WHERE {$range} AND app_slug = 'benchmark-app-01' AND level = 'error' ORDER BY occurred_at DESC LIMIT 50",
            default => throw new InvalidArgumentException("Unknown profile shape: {$shape}"),
        };
    }
}
