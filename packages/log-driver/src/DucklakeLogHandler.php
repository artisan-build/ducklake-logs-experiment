<?php

declare(strict_types=1);

namespace ArtisanBuild\DucklakeLogDriver;

use ArtisanBuild\DucklakeLogDriver\Contracts\PayloadFilter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

final class DucklakeLogHandler extends AbstractHandler
{
    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    private bool $registered = false;

    private bool $flushing = false;

    private int $dropped = 0;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly Application $app,
        private readonly Factory $http,
        private readonly PayloadFilter $filter,
        private readonly array $config,
    ) {
        parent::__construct();
    }

    public function handle(LogRecord $record): bool
    {
        try {
            if ($this->flushing || ! $this->enabled()) {
                return false;
            }

            $payload = $this->filter->filter(LogPayload::fromRecord($record));

            if ($payload === null) {
                $this->dropped++;

                return false;
            }

            if (count($this->buffer) >= (int) ($this->config['max_buffer'] ?? 1000)) {
                array_shift($this->buffer);
                $this->dropped++;
            }

            $this->buffer[] = $payload->toArray();
            $this->registerFlush();
        } catch (Throwable) {
            $this->dropped++;
        }

        return false;
    }

    public function flush(): void
    {
        if ($this->flushing || $this->buffer === [] || ! $this->enabled()) {
            return;
        }

        $this->flushing = true;
        $batchSize = max(1, (int) ($this->config['batch_size'] ?? 100));

        try {
            foreach (array_chunk($this->buffer, $batchSize) as $batch) {
                try {
                    $response = $this->http
                        ->withToken((string) $this->config['token'])
                        ->acceptJson()
                        ->connectTimeout((float) ($this->config['connect_timeout_seconds'] ?? 0.5))
                        ->timeout((float) ($this->config['timeout_seconds'] ?? 2.0))
                        ->post(rtrim((string) $this->config['endpoint'], '/').'/api/v1/logs', [
                            'idempotency_key' => LogPayload::fromRecord(new LogRecord(new \DateTimeImmutable, 'driver', Level::Info, 'batch'))->id,
                            'logs' => $batch,
                        ]);

                    if (! $response->successful()) {
                        $this->dropped += count($batch);
                    }
                } catch (Throwable) {
                    $this->dropped += count($batch);
                }
            }
        } finally {
            $this->buffer = [];
            $this->flushing = false;
        }
    }

    public function droppedCount(): int
    {
        return $this->dropped;
    }

    private function enabled(): bool
    {
        $endpoint = $this->config['endpoint'] ?? null;
        $token = $this->config['token'] ?? null;

        if (! is_string($endpoint) || $endpoint === '' || ! is_string($token) || $token === '') {
            return false;
        }

        $endpointHost = parse_url($endpoint, PHP_URL_HOST);
        $appHost = parse_url((string) $this->app['config']->get('app.url'), PHP_URL_HOST);

        return ! (is_string($endpointHost) && is_string($appHost) && $endpointHost === $appHost);
    }

    private function registerFlush(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;
        $this->app->terminating(fn () => $this->flush());
    }
}
