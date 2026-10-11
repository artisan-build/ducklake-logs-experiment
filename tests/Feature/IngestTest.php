<?php

use App\Models\LogApp;
use App\Models\LogBatch;

use function Pest\Laravel\postJson;

it('authenticates and idempotently stages a batch for the token app', function (): void {
    [$app, $token] = LogApp::issue('Orders');
    $payload = [
        'idempotency_key' => 'batch-1',
        'logs' => [[
            'id' => '123e4567-e89b-42d3-a456-426614174000',
            'timestamp' => '2026-10-11T09:00:00Z',
            'level' => 'error',
            'channel' => 'stack',
            'message' => 'Checkout failed',
            'context' => ['request_id' => 'req-1'],
            'extra' => [],
        ]],
    ];

    postJson('/api/v1/logs', $payload, ['Authorization' => "Bearer {$token}"])
        ->assertAccepted()
        ->assertJson(['accepted' => 1, 'duplicate' => false]);

    postJson('/api/v1/logs', $payload, ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJson(['accepted' => 1, 'duplicate' => true]);

    expect(LogBatch::query()->count())->toBe(1)
        ->and($app->fresh()->accepted_lines)->toBe(1);
});

it('rejects missing and invalid ingest tokens', function (): void {
    postJson('/api/v1/logs', [])->assertUnauthorized();
    postJson('/api/v1/logs', [], ['Authorization' => 'Bearer invalid'])->assertUnauthorized();
});
