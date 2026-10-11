<?php

use App\Services\LakeQueries;
use App\Support\DuckDb;

function recordingDuckDb(): DuckDb
{
    return new class extends DuckDb
    {
        public string $sql = '';

        public function query(string $sql, int $timeout = 20): array
        {
            $this->sql = $sql;

            return [];
        }

        public function literal(?string $value): string
        {
            return "'{$value}'";
        }
    };
}

it('includes the partition date in search range predicates', function (): void {
    $duckDb = recordingDuckDb();

    (new LakeQueries($duckDb))->search([
        'from' => '2026-10-10T00:00:00Z',
        'to' => '2026-10-11T00:00:00Z',
    ]);

    expect($duckDb->sql)
        ->toContain("event_date BETWEEN CAST('2026-10-10T00:00:00Z' AS DATE) AND CAST('2026-10-11T00:00:00Z' AS DATE)")
        ->toContain("occurred_at >= CAST('2026-10-10T00:00:00Z' AS TIMESTAMPTZ)");
});

it('includes the partition date in aggregate range predicates', function (): void {
    $duckDb = recordingDuckDb();

    (new LakeQueries($duckDb))->aggregate('2026-10-04T00:00:00Z', '2026-10-11T00:00:00Z');

    expect($duckDb->sql)
        ->toContain("event_date BETWEEN CAST('2026-10-04T00:00:00Z' AS DATE) AND CAST('2026-10-11T00:00:00Z' AS DATE)")
        ->toContain("occurred_at <= CAST('2026-10-11T00:00:00Z' AS TIMESTAMPTZ)");
});
