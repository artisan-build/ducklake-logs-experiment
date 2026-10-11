<?php

namespace App\Services;

use App\Support\DuckDb;

class LakeQueries
{
    public function __construct(private readonly DuckDb $duckDb) {}

    /** @param array<string, mixed> $filters
     * @return array{query_ms: float, rows: list<array<string, mixed>>}
     */
    public function search(array $filters): array
    {
        $where = [
            'occurred_at >= CAST('.$this->duckDb->literal((string) $filters['from']).' AS TIMESTAMPTZ)',
            'occurred_at <= CAST('.$this->duckDb->literal((string) $filters['to']).' AS TIMESTAMPTZ)',
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
        $where = 'occurred_at >= CAST('.$this->duckDb->literal($from).' AS TIMESTAMPTZ) AND occurred_at <= CAST('.$this->duckDb->literal($to).' AS TIMESTAMPTZ)';

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
