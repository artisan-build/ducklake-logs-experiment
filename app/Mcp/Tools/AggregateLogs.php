<?php

namespace App\Mcp\Tools;

use App\Services\LakeQueries;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class AggregateLogs extends LogTool
{
    protected string $description = 'Count logs by time bucket, application, and level.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('ISO-8601 range start.')->required(),
            'to' => $schema->string()->description('ISO-8601 range end.')->required(),
            'bucket' => $schema->string()->description('hour or day.'),
            'app' => $schema->string()->description('Optional application slug.'),
        ];
    }

    public function handle(Request $request, LakeQueries $queries): Response
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'bucket' => ['nullable', 'in:hour,day'],
            'app' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->untrusted($queries->aggregate((string) $validated['from'], (string) $validated['to'], (string) ($validated['bucket'] ?? 'hour'), $validated['app'] ?? null));
    }
}
