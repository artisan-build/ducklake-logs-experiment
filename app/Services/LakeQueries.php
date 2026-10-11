<?php

namespace App\Services;

use App\Support\DuckDb;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LakeQueries
{
    public function __construct(private readonly DuckDb $duckDb) {}

    /** @param array<string, mixed> $filters
     * @return array{query_ms: float, rows: list<array<string, mixed>>}
     */
    public function search(array $filters): array
    {
        $from = $this->duckDb->literal((string) $filters['from']);
        $to = $this->duckDb->literal((string) $filters['to']);
        $where = [
            "event_date BETWEEN CAST({$from} AS DATE) AND CAST({$to} AS DATE)",
            "occurred_at >= CAST({$from} AS TIMESTAMPTZ)",
            "occurred_at <= CAST({$to} AS TIMESTAMPTZ)",
        ];

        if (! empty($filters['app'])) {
            $where[] = 'app_slug = '.$this->duckDb->literal((string) $filters['app']);
        }

        if (! empty($filters['level'])) {
            $where[] = 'level = '.$this->duckDb->literal(strtolower((string) $filters['level']));
        }

        if (! empty($filters['text'])) {
            $where[] = 'contains(lower(message), lower('.$this->duckDb->literal((string) $filters['text']).'))';
        }

        if (! empty($filters['regex'])) {
            $where[] = 'regexp_matches(message, '.$this->duckDb->literal((string) $filters['regex']).', \'i\')';
        }

        $limit = min(500, max(1, (int) ($filters['limit'] ?? 100)));

        return $this->timed(sprintf(
            'SELECT event_id, app_slug, occurred_at, level, channel, message, context, extra FROM lake.logs WHERE %s ORDER BY occurred_at DESC LIMIT %d;',
            implode(' AND ', $where),
            $limit,
        ));
    }

    /** @return array{query_ms: float, rows: list<array<string, mixed>>} */
    public function aggregate(string $from, string $to, string $bucket = 'hour', ?string $app = null): array
    {
        $unit = in_array($bucket, ['hour', 'day'], true) ? $bucket : 'hour';
        $started = hrtime(true);
        $rollup = DB::table('log_hourly_counts')
            ->where('bucket', '>=', date('Y-m-d H:00:00P', strtotime($from)))
            ->where('bucket', '<=', date('Y-m-d H:00:00P', strtotime($to)))
            ->when($app !== null && $app !== '', fn ($query) => $query->where('app_slug', $app));

        if (Schema::hasTable('log_hourly_counts') && $rollup->exists()) {
            $rows = $unit === 'hour'
                ? $rollup->orderByDesc('bucket')->orderBy('app_slug')->orderBy('level')->get(['bucket', 'app_slug', 'level', 'count'])
                : $rollup->selectRaw("date_trunc('day', bucket) AS bucket, app_slug, level, sum(count) AS count")->groupByRaw("date_trunc('day', bucket), app_slug, level")->orderByDesc('bucket')->orderBy('app_slug')->orderBy('level')->get();

            return [
                'query_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
                'rows' => $rows->map(fn (object $row): array => (array) $row)->all(),
            ];
        }

        $fromLiteral = $this->duckDb->literal($from);
        $toLiteral = $this->duckDb->literal($to);
        $where = "event_date BETWEEN CAST({$fromLiteral} AS DATE) AND CAST({$toLiteral} AS DATE) "
            ."AND occurred_at >= CAST({$fromLiteral} AS TIMESTAMPTZ) "
            ."AND occurred_at <= CAST({$toLiteral} AS TIMESTAMPTZ)";

        if ($app !== null && $app !== '') {
            $where .= ' AND app_slug = '.$this->duckDb->literal($app);
        }

        return $this->timed("SELECT date_trunc('{$unit}', occurred_at) bucket, app_slug, level, count(*) count FROM lake.logs WHERE {$where} GROUP BY ALL ORDER BY bucket DESC, app_slug, level;");
    }

    /** @return array{query_ms: float, rows: list<array<string, mixed>>} */
    public function context(string $eventId, int $lines): array
    {
        $limit = min(101, max(3, ($lines * 2) + 1));
        $event = $this->duckDb->literal($eventId);
        $target = Schema::hasTable('log_event_locators')
            ? DB::table('log_event_locators')->where('event_id', $eventId)->first()
            : null;

        if ($target !== null) {
            $app = $this->duckDb->literal((string) $target->app_slug);
            $timestamp = $this->duckDb->literal((string) $target->occurred_at);

            return $this->timed("SELECT event_id, app_slug, occurred_at, level, channel, message, context, extra FROM lake.logs WHERE event_date = CAST({$timestamp} AS TIMESTAMPTZ)::DATE AND app_slug = {$app} ORDER BY abs(epoch(occurred_at) - epoch(CAST({$timestamp} AS TIMESTAMPTZ))), occurred_at LIMIT {$limit};");
        }

        return $this->timed("WITH target AS (SELECT app_slug, occurred_at FROM lake.logs WHERE event_id = {$event} LIMIT 1) SELECT l.event_id, l.app_slug, l.occurred_at, l.level, l.channel, l.message, l.context, l.extra FROM lake.logs l, target t WHERE l.app_slug = t.app_slug ORDER BY abs(epoch(l.occurred_at) - epoch(t.occurred_at)), l.occurred_at LIMIT {$limit};");
    }

    /** @return array{query_ms: float, rows: list<array<string, mixed>>} */
    private function timed(string $sql): array
    {
        $started = hrtime(true);
        $rows = $this->duckDb->query($sql);

        return [
            'query_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
            'rows' => $rows,
        ];
    }
}
