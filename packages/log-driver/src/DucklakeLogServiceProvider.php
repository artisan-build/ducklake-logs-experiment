<?php

declare(strict_types=1);

namespace ArtisanBuild\DucklakeLogDriver;

use ArtisanBuild\DucklakeLogDriver\Contracts\PayloadFilter;
use Illuminate\Http\Client\Factory;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;

final class DucklakeLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ducklake-log.php', 'ducklake-log');
        $this->app->bind(PayloadFilter::class, PassThroughPayloadFilter::class);
    }

    public function boot(LogManager $logs): void
    {
        $logs->extend('ducklake', function ($app, array $config): Logger {
            $handler = new DucklakeLogHandler(
                $app,
                $app->make(Factory::class),
                $app->make(PayloadFilter::class),
                array_replace((array) $app['config']->get('ducklake-log', []), $config),
            );

            return new Logger('ducklake', [$handler]);
        });
    }
}
