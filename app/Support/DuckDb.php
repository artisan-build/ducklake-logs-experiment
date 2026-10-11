<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

class DuckDb
{
    /** @return list<array<string, mixed>> */
    public function query(string $sql, int $timeout = 20): array
    {
        $process = new Process([(string) config('ducklake.binary'), '-unsigned', '-json']);
        $process->setTimeout($timeout);
        $process->setInput($this->preamble()."\n".$sql."\n");
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('DuckDB command failed with exit code '.$process->getExitCode().': '.$this->sanitizedError($process->getErrorOutput()));
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return [];
        }

        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('DuckDB returned an unreadable response.');
        }

        return array_is_list($decoded) ? $decoded : [$decoded];
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
            ALTER TABLE lake.logs SET PARTITIONED BY (app_slug, event_date);
            SELECT count(*) AS rows FROM lake.logs;
            SQL, 60);
    }

    public function literal(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'".str_replace("'", "''", $value)."'";
    }

    private function preamble(): string
    {
        $extensions = $this->literal((string) config('ducklake.extension_directory'));
        $database = config('database.connections.pgsql');
        $disk = config('filesystems.disks.s3');

        if (! config('ducklake.cloud') || ! is_array($database) || empty($database['host']) || ! is_array($disk) || empty($disk['bucket'])) {
            $dataPath = $this->literal(storage_path('app/ducklake/data/'));

            return "SET extension_directory={$extensions}; LOAD ducklake; LOAD postgres; LOAD httpfs; ATTACH ".$this->literal('ducklake:'.storage_path('app/ducklake/catalog.ducklake'))." AS lake (DATA_PATH {$dataPath}, DATA_INLINING_ROW_LIMIT 0);";
        }

        $endpoint = (string) ($disk['endpoint'] ?? '');
        $endpointHost = parse_url($endpoint, PHP_URL_HOST) ?: preg_replace('#^https?://#', '', $endpoint);
        $useSsl = str_starts_with($endpoint, 'https://') ? 'true' : 'false';
        $pathStyle = ! empty($disk['use_path_style_endpoint']) ? 'path' : 'vhost';
        $s3Secret = sprintf(
            'CREATE OR REPLACE TEMP SECRET cloud_s3 (TYPE s3, KEY_ID %s, SECRET %s, REGION %s, ENDPOINT %s, URL_STYLE %s, USE_SSL %s);',
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
            'SET extension_directory=%s; LOAD ducklake; LOAD postgres; LOAD httpfs; %s ATTACH %s AS lake (DATA_PATH %s, DATA_INLINING_ROW_LIMIT 0);',
            $extensions,
            $s3Secret,
            $this->literal('ducklake:postgres:'.$dsn),
            $this->literal($dataPath),
        );
    }

    private function sanitizedError(string $error): string
    {
        $secrets = [
            config('database.connections.pgsql.password'),
            config('filesystems.disks.s3.key'),
            config('filesystems.disks.s3.secret'),
        ];

        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $error = str_replace($secret, '[REDACTED]', $error);
            }
        }

        $error = preg_replace('/password=[^\s\'";]+/i', 'password=[REDACTED]', $error) ?? $error;
        $error = preg_replace('/\b(KEY_ID|SECRET)\s+\'[^\']*\'/i', '$1 \'[REDACTED]\'', $error) ?? $error;

        return mb_substr(trim($error), 0, 1200);
    }
}
