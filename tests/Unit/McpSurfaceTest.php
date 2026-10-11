<?php

use App\Mcp\Servers\LogServer;
use App\Mcp\Tools\AggregateLogs;
use App\Mcp\Tools\GetContext;
use App\Mcp\Tools\ListApps;
use App\Mcp\Tools\SearchLogs;
use App\Mcp\Tools\TailLogs;

it('exposes only the five fixed log tools and no raw sql tool', function (): void {
    $reflection = new ReflectionProperty(LogServer::class, 'tools');
    $tools = $reflection->getDefaultValue();

    expect($tools)->toBe([
        ListApps::class,
        SearchLogs::class,
        TailLogs::class,
        AggregateLogs::class,
        GetContext::class,
    ])->and(implode(' ', $tools))->not->toContain('Sql');
});
