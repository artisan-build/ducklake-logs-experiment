<?php

use App\Http\Controllers\LogAppController;
use App\Http\Controllers\SearchController;
use App\Support\DuckDb;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('apps', [LogAppController::class, 'index'])->name('apps.index');
    Route::post('apps', [LogAppController::class, 'store'])->name('apps.store');
    Route::get('search', SearchController::class)->name('search');
});

Route::get('/probe/runtime', function (DuckDb $duckDb): array {
    return [
        'php' => PHP_VERSION,
        'ffi_extension_loaded' => extension_loaded('ffi'),
        'ffi_enabled' => extension_loaded('ffi') && (string) ini_get('ffi.enable') !== '',
        'proc_open_available' => function_exists('proc_open'),
        'cloud_disk_config_present' => isset($_ENV['LARAVEL_CLOUD_DISK_CONFIG']) || getenv('LARAVEL_CLOUD_DISK_CONFIG') !== false,
        's3_config_resolved' => filled(config('filesystems.disks.s3.bucket')),
        'pgsql_config_resolved' => filled(config('database.connections.pgsql.host')),
        'duckdb' => $duckDb->runtimeProbe(),
    ];
})->name('probe.runtime');

require __DIR__.'/settings.php';
