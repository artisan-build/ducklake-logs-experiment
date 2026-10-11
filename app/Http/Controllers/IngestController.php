<?php

namespace App\Http\Controllers;

use App\Models\LogApp;
use App\Models\LogBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:100'],
            'logs' => ['required', 'array', 'min:1', 'max:500'],
            'logs.*.id' => ['required', 'uuid'],
            'logs.*.timestamp' => ['required', 'date'],
            'logs.*.level' => ['required', 'string', 'max:20'],
            'logs.*.channel' => ['nullable', 'string', 'max:100'],
            'logs.*.message' => ['required', 'string', 'max:262144'],
            'logs.*.context' => ['nullable', 'array'],
            'logs.*.extra' => ['nullable', 'array'],
        ]);

        /** @var LogApp $app */
        $app = $request->attributes->get('log_app');
        $created = false;

        $batch = DB::transaction(function () use ($app, $validated, &$created): LogBatch {
            $existing = LogBatch::query()
                ->where('log_app_id', $app->id)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $batch = LogBatch::query()->create([
                'log_app_id' => $app->id,
                'idempotency_key' => $validated['idempotency_key'],
                'payload' => $validated['logs'],
                'row_count' => count($validated['logs']),
            ]);

            $app->increment('accepted_lines', count($validated['logs']), ['last_ingested_at' => now()]);
            $created = true;

            return $batch;
        });

        return response()->json([
            'batch_id' => $batch->id,
            'accepted' => $batch->row_count,
            'duplicate' => ! $created,
        ], $created ? 202 : 200);
    }
}
