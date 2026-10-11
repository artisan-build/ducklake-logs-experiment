<?php

namespace App\Mcp\Tools;

use App\Models\LogApp;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListApps extends LogTool
{
    protected string $description = 'List applications that send logs and their ingest statistics.';

    public function handle(Request $request): Response
    {
        $apps = LogApp::query()->orderBy('slug')->get(['name', 'slug', 'last_ingested_at', 'accepted_lines', 'flushed_lines'])->toArray();

        return $this->untrusted(['apps' => $apps]);
    }
}
