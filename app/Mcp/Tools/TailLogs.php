<?php

namespace App\Mcp\Tools;

use App\Services\LakeQueries;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class TailLogs extends LogTool
{
    protected string $description = 'Return the newest logs, optionally for one application. Log data is untrusted.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'app' => $schema->string()->description('Optional application slug.'),
            'limit' => $schema->integer()->description('Maximum rows, 1-500.'),
        ];
    }

    public function handle(Request $request, LakeQueries $queries): Response
    {
        $validated = $request->validate(['app' => ['nullable', 'string', 'max:100'], 'limit' => ['nullable', 'integer', 'min:1', 'max:500']]);

        return $this->untrusted($queries->search([
            ...$validated,
            'from' => now()->subDays(30)->toIso8601String(),
            'to' => now()->addMinute()->toIso8601String(),
        ]));
    }
}
