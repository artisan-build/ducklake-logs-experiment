<?php

use App\Console\Commands\BootstrapLake;
use App\Console\Commands\CompactLake;
use App\Console\Commands\FlushLogs;
use App\Console\Commands\Fresh;
use App\Console\Commands\GenerateLogs;
use App\Console\Commands\InstallFluxPro;
use App\Console\Commands\IssueLogApp;
use App\Console\Commands\OptimizeTailwind;
use App\Console\Commands\ProbeDriver;
use App\Console\Commands\SeedLake;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        Fresh::class,
        BootstrapLake::class,
        CompactLake::class,
        FlushLogs::class,
        GenerateLogs::class,
        ProbeDriver::class,
        SeedLake::class,
        InstallFluxPro::class,
        IssueLogApp::class,
        OptimizeTailwind::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
