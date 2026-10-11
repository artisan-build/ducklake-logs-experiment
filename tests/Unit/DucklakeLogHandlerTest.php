<?php

use ArtisanBuild\DucklakeLogDriver\Contracts\PayloadFilter;
use ArtisanBuild\DucklakeLogDriver\DucklakeLogHandler;
use ArtisanBuild\DucklakeLogDriver\LogPayload;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Monolog\Level;
use Monolog\LogRecord;

function driverHandler(?PayloadFilter $filter = null, array $overrides = []): DucklakeLogHandler
{
    config(['app.url' => 'https://source.test']);

    return new DucklakeLogHandler(
        app(),
        app(Factory::class),
        $filter ?? new class implements PayloadFilter
        {
            public function filter(LogPayload $payload): ?LogPayload
            {
                return $payload;
            }
        },
        array_replace([
            'endpoint' => 'https://sink.test',
            'token' => 'ingest-token',
            'batch_size' => 100,
            'max_buffer' => 1000,
            'timeout_seconds' => 1.0,
            'connect_timeout_seconds' => 0.1,
        ], $overrides),
    );
}

function logRecord(array $context = []): LogRecord
{
    return new LogRecord(now()->toDateTimeImmutable(), 'stack', Level::Error, 'Checkout failed', $context);
}

it('buffers during the request and performs slow network work only when flushed', function (): void {
    Http::fake(function () {
        usleep(250_000);

        return Http::response(['accepted' => 1], 202);
    });
    $handler = driverHandler();

    $started = hrtime(true);
    $handler->handle(logRecord());
    $handleMs = (hrtime(true) - $started) / 1_000_000;

    $started = hrtime(true);
    $handler->flush();
    $flushMs = (hrtime(true) - $started) / 1_000_000;

    expect($handleMs)->toBeLessThan(25.0)
        ->and($flushMs)->toBeGreaterThan(200.0)
        ->and($handler->droppedCount())->toBe(0);
});

it('never throws and counts drops when the endpoint fails', function (): void {
    Http::fake(fn () => throw new RuntimeException('endpoint down'));
    $handler = driverHandler();

    $handler->handle(logRecord());
    $handler->flush();

    expect($handler->droppedCount())->toBe(1);
});

it('ships the rebound filter output and never sends the original secret', function (): void {
    Http::fake(['sink.test/*' => Http::response(['accepted' => 1], 202)]);
    $filter = new class implements PayloadFilter
    {
        public function filter(LogPayload $payload): ?LogPayload
        {
            return new LogPayload(
                $payload->id,
                $payload->timestamp,
                $payload->level,
                $payload->channel,
                $payload->message,
                ['authorization' => '[REDACTED]'],
                $payload->extra,
            );
        }
    };
    $handler = driverHandler($filter);

    $handler->handle(logRecord(['authorization' => 'Bearer super-secret-value']));
    $handler->flush();

    Http::assertSent(function (Request $request): bool {
        $body = $request->body();

        return str_contains($body, '[REDACTED]') && ! str_contains($body, 'super-secret-value');
    });
});

it('does not send sink logs back to the same host', function (): void {
    Http::fake();
    config(['app.url' => 'https://sink.test']);
    $handler = driverHandler(overrides: ['endpoint' => 'https://source.test']);
    config(['app.url' => 'https://source.test']);

    $handler->handle(logRecord());
    $handler->flush();

    Http::assertNothingSent();
});
