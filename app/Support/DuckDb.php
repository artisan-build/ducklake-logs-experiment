<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

class DuckDb
{
    /** @return list<array<string, mixed>> */
    public function query(string $sql, int $timeout = 20): array
    {
        $process = new Process([(string) config('ducklake.binary'), '-unsigned', '-jsonlines']);
        $process->setTimeout($timeout);
        $process->setInput($this->preamble()."\nSELECT '__DUCKLAKE_RESULT_START__' AS marker;\n".$sql."\nSELECT '__DUCKLAKE_RESULT_END__' AS marker;\n");
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('DuckDB command failed with exit code '.$process->getExitCode().': '.$this->sanitizedError($process->getErrorOutput()));
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return [];
        }

        return $this->decodeFramedOutput($output);
    }

    /** @return array<string, mixed> */
    public function runtimeProbe(): array
    {
        $process = new Process([(string) config('ducklake.binary'), '-unsigned', '-json']);
        $process->setTimeout(10);
        $extensions = $this->literal((string) config('ducklake.extension_directory'));
        $process->setInput("SET extension_directory={$extensions}; LOAD ducklake; LOAD postgres; LOAD httpfs; SELECT version() AS duckdb_version, current_setting('extension_directory') AS extension_directory;");
        $process->run();

        return [
            'binary_exists' => is_executable((string) config('ducklake.binary')),
            'exit_code' => $process->getExitCode(),
            'extensions_loaded' => $process->isSuccessful(),
            'result' => $process->isSuccessful() ? json_decode(trim($process->getOutput()), true) : null,
        ];
    }

    /**
     * Measure the request-time query path without ever returning connection credentials.
     *
     * @return array<string, mixed>
     */
    public function profileQuery(string $sql, int $runs = 3, int $timeout = 120): array
    {
        $runs = min(7, max(1, $runs));
        $stages = [
            'process_start' => 'SELECT 1;',
            'extensions_and_settings' => $this->settingsSql().' '.$this->extensionsSql().' SELECT 1;',
            'catalog_attach' => $this->preamble().' SELECT 1;',
        ];
        $samples = [];

        foreach ($stages as $name => $input) {
            $samples[$name] = [];

            for ($run = 0; $run < $runs; $run++) {
                $samples[$name][] = $this->runRaw($input, $timeout)['wall_ms'];
            }
        }

        $profileSql = "CALL enable_logging(level := 'DEBUG', storage := 'memory');\n"
            .$this->preamble()."\n"
            .'EXPLAIN ANALYZE '.rtrim(trim($sql), ';').";\n"
            ."SELECT request.type AS method, response.status AS status, count(*) AS requests, coalesce(sum(request.duration_ms), 0) AS request_ms FROM duckdb_logs_parsed('HTTP') GROUP BY ALL ORDER BY method, status;\n"
            .'SELECT type AS log_type, count(*) AS log_entries FROM duckdb_logs GROUP BY ALL ORDER BY log_type;';
        $profile = $this->runRaw($profileSql, $timeout);
        $decoded = $this->decodeJsonLines($profile['stdout']);
        $rawPlan = $profile['stdout']."\n".$profile['stderr'];
        $plan = preg_replace('/\e\[[0-9;]*m/', '', $rawPlan) ?? $rawPlan;
        $http = [];
        $logTypes = [];

        foreach ($decoded as $row) {
            if (isset($row['explain_value'])) {
                $plan .= (string) $row['explain_value'];
            }

            if (isset($row['requests'])) {
                $http[] = $row;
            }

            if (isset($row['log_entries'])) {
                $logTypes[(string) $row['log_type']] = (int) $row['log_entries'];
            }
        }

        preg_match('/Total Time:\s*([0-9.]+)s/', $plan, $totalMatch);
        preg_match('/HTTP Requests:\s*([0-9]+)/', $plan, $requestMatch);
        preg_match('/#GET:\s*([0-9]+)/', $plan, $getMatch);
        preg_match('/#HEAD:\s*([0-9]+)/', $plan, $headMatch);
        preg_match('/in:\s*([^\n│]+)/', $plan, $bytesInMatch);
        preg_match('/out:\s*([^\n│]+)/', $plan, $bytesOutMatch);

        return [
            'runs' => $runs,
            'settings' => [
                'threads' => (int) config('ducklake.threads'),
                'memory_limit' => (string) config('ducklake.memory_limit'),
            ],
            'cumulative_wall_ms' => array_map(fn (array $values): array => [
                'samples' => array_map(fn (float $value): float => round($value, 2), $values),
                'median' => round($this->median($values), 2),
            ], $samples),
            'profile_wall_ms' => round($profile['wall_ms'], 2),
            'query_total_ms' => isset($totalMatch[1]) ? round(((float) $totalMatch[1]) * 1000, 2) : null,
            'explain_http_requests' => isset($requestMatch[1]) ? (int) $requestMatch[1] : null,
            'explain_get_requests' => isset($getMatch[1]) ? (int) $getMatch[1] : null,
            'explain_head_requests' => isset($headMatch[1]) ? (int) $headMatch[1] : null,
            'explain_bytes_in' => isset($bytesInMatch[1]) ? trim($bytesInMatch[1]) : null,
            'explain_bytes_out' => isset($bytesOutMatch[1]) ? trim($bytesOutMatch[1]) : null,
            'http' => $http,
            'log_types' => $logTypes,
        ];
    }

    public function bootstrap(): void
    {
        $this->query(<<<'SQL'
            CREATE TABLE IF NOT EXISTS lake.logs (
                event_id VARCHAR,
                app_id BIGINT,
                app_slug VARCHAR,
                event_date DATE,
                occurred_at TIMESTAMPTZ,
                level VARCHAR,
                channel VARCHAR,
                message VARCHAR,
                context JSON,
                extra JSON,
                ingested_at TIMESTAMPTZ
            );
            ALTER TABLE lake.logs SET PARTITIONED BY (event_date);
            ALTER TABLE lake.logs SET SORTED BY (app_slug ASC, occurred_at ASC);
            CALL lake.set_option('target_file_size', '32MB', table_name => 'logs');
            SQL, 60);
    }

    public function literal(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'".str_replace("'", "''", $value)."'";
    }

    /** @return list<array<string, mixed>> */
    private function decodeFramedOutput(string $output): array
    {
        $collecting = false;
        $rows = [];

        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            $decoded = json_decode($line, true);

            if (! is_array($decoded)) {
                continue;
            }

            if (($decoded['marker'] ?? null) === '__DUCKLAKE_RESULT_START__') {
                $collecting = true;

                continue;
            }

            if (($decoded['marker'] ?? null) === '__DUCKLAKE_RESULT_END__') {
                return $rows;
            }

            if ($collecting && array_keys($decoded) !== ['Success']) {
                $rows[] = $decoded;
            }
        }

        throw new RuntimeException('DuckDB returned an unreadable response.');
    }

    private function preamble(): string
    {
        $database = config('database.connections.pgsql');
        $disk = config('filesystems.disks.s3');

        if (! config('ducklake.cloud') || ! is_array($database) || empty($database['host']) || ! is_array($disk) || empty($disk['bucket'])) {
            $dataPath = $this->literal(storage_path('app/ducklake/data/'));

            return $this->settingsSql().' '.$this->extensionsSql().' ATTACH '.$this->literal('ducklake:'.storage_path('app/ducklake/catalog.ducklake'))." AS lake (DATA_PATH {$dataPath}, DATA_INLINING_ROW_LIMIT 0);";
        }

        $endpoint = (string) ($disk['endpoint'] ?? '');
        $endpointHost = parse_url($endpoint, PHP_URL_HOST) ?: preg_replace('#^https?://#', '', $endpoint);
        $useSsl = str_starts_with($endpoint, 'https://') ? 'true' : 'false';
        $pathStyle = ! empty($disk['use_path_style_endpoint']) ? 'path' : 'vhost';
        $s3Secret = sprintf(
            'CREATE OR REPLACE TEMPORARY SECRET cloud_s3 (TYPE s3, KEY_ID %s, SECRET %s, REGION %s, ENDPOINT %s, URL_STYLE %s, USE_SSL %s);',
            $this->literal((string) ($disk['key'] ?? '')),
            $this->literal((string) ($disk['secret'] ?? '')),
            $this->literal((string) ($disk['region'] ?? 'auto')),
            $this->literal((string) $endpointHost),
            $this->literal($pathStyle),
            $useSsl,
        );
        $dsn = sprintf(
            'host=%s port=%s dbname=%s user=%s password=%s sslmode=require',
            $database['host'],
            $database['port'] ?? 5432,
            $database['database'],
            $database['username'],
            $database['password'],
        );
        $dataPath = 's3://'.$disk['bucket'].'/'.ltrim((string) config('ducklake.data_prefix'), '/');

        return sprintf(
            '%s %s %s ATTACH %s AS lake (DATA_PATH %s, DATA_INLINING_ROW_LIMIT 0);',
            $this->settingsSql(),
            $this->extensionsSql(),
            $s3Secret,
            $this->literal('ducklake:postgres:'.$dsn),
            $this->literal($dataPath),
        );
    }

    private function settingsSql(): string
    {
        $memory = $this->literal((string) config('ducklake.memory_limit', '384MB'));
        $threads = max(1, (int) config('ducklake.threads', 1));

        return "SET threads={$threads}; SET memory_limit={$memory}; SET preserve_insertion_order=false;";
    }

    private function extensionsSql(): string
    {
        $extensions = $this->literal((string) config('ducklake.extension_directory'));

        return "SET extension_directory={$extensions}; LOAD ducklake; LOAD postgres; LOAD httpfs;";
    }

    /** @return array{stdout: string, stderr: string, wall_ms: float} */
    private function runRaw(#[\SensitiveParameter] string $input, int $timeout): array
    {
        $process = new Process([(string) config('ducklake.binary'), '-unsigned', '-jsonlines']);
        $process->setTimeout($timeout);
        $process->setInput($input);
        $started = hrtime(true);
        $process->run();
        $wall = (hrtime(true) - $started) / 1_000_000;

        if (! $process->isSuccessful()) {
            throw new RuntimeException('DuckDB profiling failed with exit code '.$process->getExitCode().': '.$this->sanitizedError($process->getErrorOutput()));
        }

        return [
            'stdout' => $process->getOutput(),
            'stderr' => $this->sanitizedError($process->getErrorOutput()),
            'wall_ms' => $wall,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function decodeJsonLines(string $output): array
    {
        $rows = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            $decoded = json_decode($line, true);

            if (is_array($decoded) && array_keys($decoded) !== ['Success']) {
                $rows[] = $decoded;
            }
        }

        return $rows;
    }

    /** @param list<float> $values */
    private function median(array $values): float
    {
        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
    }

    private function sanitizedError(string $error): string
    {
        $secrets = [
            config('database.connections.pgsql.password'),
            config('filesystems.disks.s3.key'),
            config('filesystems.disks.s3.secret'),
            config('filesystems.disks.s3.access_key_id'),
            config('filesystems.disks.s3.access_key_secret'),
        ];

        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $error = str_replace($secret, '[REDACTED]', $error);
            }
        }

        $error = preg_replace('/password=[^\s\'";]+/i', 'password=[REDACTED]', $error) ?? $error;
        $error = preg_replace('/\b(KEY_ID|SECRET)\s+\'[^\']*\'/i', '$1 \'[REDACTED]\'', $error) ?? $error;
        $error = preg_replace('/\b(KEY_ID|SECRET)\s+[^,\)\n]+/i', '$1 [REDACTED]', $error) ?? $error;

        return mb_substr(trim($error), 0, 1200);
    }
}
