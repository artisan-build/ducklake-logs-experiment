<?php

use App\Http\Controllers\LogAppController;
use App\Http\Controllers\SearchController;
use App\Support\DuckDb;
use ArtisanBuild\DucklakeLogDriver\DucklakeLogHandler;
use ArtisanBuild\DucklakeLogDriver\PassThroughPayloadFilter;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Route;
use Monolog\Level;
use Monolog\LogRecord;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('apps', [LogAppController::class, 'index'])->name('apps.index');
    Route::post('apps', [LogAppController::class, 'store'])->name('apps.store');
    Route::get('search', SearchController::class)->name('search');
});

Route::get('/probe/runtime', fn (DuckDb $duckDb): array => [
    'php' => PHP_VERSION,
    'ffi_extension_loaded' => extension_loaded('ffi'),
    'ffi_enabled' => extension_loaded('ffi') && (string) ini_get('ffi.enable') !== '',
    'proc_open_available' => function_exists('proc_open'),
    'cloud_disk_config_present' => Env::get('LARAVEL_CLOUD_DISK_CONFIG') !== null || getenv('LARAVEL_CLOUD_DISK_CONFIG') !== false,
    's3_config_resolved' => filled(config('filesystems.disks.s3.bucket')),
    'pgsql_config_resolved' => filled(config('database.connections.pgsql.host')),
    'duckdb' => $duckDb->runtimeProbe(),
])->name('probe.runtime');

Route::get('/probe/driver-failure', function (Factory $http): array {
    $handler = new DucklakeLogHandler(app(), $http, new PassThroughPayloadFilter, [
        'endpoint' => 'https://192.0.2.1',
        'token' => 'probe-token',
        'connect_timeout_seconds' => 0.5,
        'timeout_seconds' => 1.0,
    ]);
    $started = hrtime(true);
    $handler->handle(new LogRecord(now()->toDateTimeImmutable(), 'probe', Level::Warning, 'downstream unavailable'));

    return [
        'handle_ms' => round((hrtime(true) - $started) / 1_000_000, 3),
        'flush_phase' => 'application termination',
    ];
})->name('probe.driver-failure');

require __DIR__.'/settings.php';
