<?php

namespace App\Console\Commands;

use App\Models\LogApp;
use Illuminate\Console\Command;

class IssueLogApp extends Command
{
    protected $signature = 'logs:issue-app {name} {--slug=}';

    protected $description = 'Create an ingest application and print its one-time token.';

    public function handle(): int
    {
        [$app, $token] = LogApp::issue((string) $this->argument('name'), $this->option('slug') ?: null);
        $this->line(json_encode(['slug' => $app->slug, 'token' => $token], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
