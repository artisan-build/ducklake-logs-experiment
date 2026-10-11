<?php

namespace App\Services;

use App\Models\LogBatch;
use App\Support\DuckDb;
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
        $rows = 0;

        foreach ($batches as $batch) {
            foreach ((array) $batch->payload as $log) {
                $values[] = $this->rowSql($batch, (array) $log);
                $rows++;
            }
        }

        try {
            $this->duckDb->bootstrap();
            $this->duckDb->query('INSERT INTO lake.logs VALUES '.implode(",\n", $values).'; SELECT '.$rows.' AS inserted;', 120);

            DB::transaction(function () use ($batches): void {
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
