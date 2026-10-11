<?php

namespace App\Services;

use App\Models\LogBatch;
use App\Support\DuckDb;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class FlushLogBatches
{
    public function __construct(private readonly DuckDb $duckDb) {}

    /** @return array{batches: int, rows: int} */
    public function run(?int $limit = null): array
    {
        LogBatch::query()
            ->where('status', 'processing')
            ->where('claimed_at', '<', now()->subMinutes(5))
            ->update(['status' => 'pending', 'claimed_at' => null]);

        $batches = DB::transaction(function () use ($limit): Collection {
            $claimed = LogBatch::query()
                ->with('app')
                ->where('status', 'pending')->oldest()
                ->limit($limit ?? (int) config('ducklake.flush_batch_limit', 20))
                ->lock('for update skip locked')
                ->get();

            foreach ($claimed as $batch) {
                $batch->update(['status' => 'processing', 'claimed_at' => now(), 'last_error' => null]);
            }

            return $claimed;
        });

        if ($batches->isEmpty()) {
            return ['batches' => 0, 'rows' => 0];
        }

        $values = [];
        $locators = [];
        $errors = [];
        $hourly = [];
        $rows = 0;

        foreach ($batches as $batch) {
            foreach ((array) $batch->payload as $log) {
                $values[] = $this->rowSql($batch, (array) $log);
                $timestamp = Carbon::parse((string) $log['timestamp'])->utc();
                $locators[] = [
                    'event_id' => (string) $log['id'],
                    'app_slug' => $batch->app->slug,
                    'occurred_at' => $timestamp,
                ];
                if (strtolower((string) $log['level']) === 'error') {
                    $errors[] = [
                        'event_id' => (string) $log['id'],
                        'app_slug' => $batch->app->slug,
                        'occurred_at' => $timestamp,
                        'level' => 'error',
                        'channel' => (string) ($log['channel'] ?? 'app'),
                        'message' => (string) $log['message'],
                        'context' => json_encode($log['context'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                        'extra' => json_encode($log['extra'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR),
                    ];
                }
                $key = $timestamp->copy()->startOfHour()->toIso8601String().'|'.$batch->app->slug.'|'.strtolower((string) $log['level']);
                $hourly[$key] = [
                    'bucket' => $timestamp->copy()->startOfHour(),
                    'app_slug' => $batch->app->slug,
                    'level' => strtolower((string) $log['level']),
                    'count' => ($hourly[$key]['count'] ?? 0) + 1,
                ];
                $rows++;
            }
        }

        try {
            $this->duckDb->bootstrap();
            $this->duckDb->query('INSERT INTO lake.logs VALUES '.implode(",\n", $values).'; SELECT '.$rows.' AS inserted;', 120);

            DB::transaction(function () use ($batches, $errors, $hourly, $locators): void {
                DB::table('log_event_locators')->insertOrIgnore($locators);
                if ($errors !== []) {
                    DB::table('log_error_events')->insertOrIgnore($errors);
                }

                foreach ($hourly as $count) {
                    DB::statement(
                        'INSERT INTO log_hourly_counts (bucket, app_slug, level, count, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?) '
                        .'ON CONFLICT (bucket, app_slug, level) DO UPDATE SET count = log_hourly_counts.count + excluded.count, updated_at = excluded.updated_at',
                        [$count['bucket'], $count['app_slug'], $count['level'], $count['count'], now(), now()],
                    );
                }

                foreach ($batches as $batch) {
                    $batch->app()->increment('flushed_lines', $batch->row_count);
                    $batch->update([
                        'payload' => null,
                        'status' => 'flushed',
                        'flushed_at' => now(),
                        'claimed_at' => null,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            LogBatch::query()->whereKey($batches->modelKeys())->update([
                'status' => 'pending',
                'claimed_at' => null,
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return ['batches' => $batches->count(), 'rows' => $rows];
    }

    /** @param array<string, mixed> $log */
    private function rowSql(LogBatch $batch, array $log): string
    {
        $context = json_encode($log['context'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $extra = json_encode($log['extra'] ?? [], JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $timestamp = (string) $log['timestamp'];

        return sprintf(
            '(%s, %d, %s, CAST(%s AS TIMESTAMPTZ)::DATE, CAST(%s AS TIMESTAMPTZ), %s, %s, %s, CAST(%s AS JSON), CAST(%s AS JSON), now())',
            $this->duckDb->literal((string) $log['id']),
            $batch->app->id,
            $this->duckDb->literal($batch->app->slug),
            $this->duckDb->literal($timestamp),
            $this->duckDb->literal($timestamp),
            $this->duckDb->literal((string) $log['level']),
            $this->duckDb->literal((string) ($log['channel'] ?? 'app')),
            $this->duckDb->literal((string) $log['message']),
            $this->duckDb->literal(is_string($context) ? $context : '{}'),
            $this->duckDb->literal(is_string($extra) ? $extra : '{}'),
        );
    }
}
