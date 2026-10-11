<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLogBatch
 */
class LogBatch extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $fillable = ['log_app_id', 'idempotency_key', 'payload', 'row_count', 'status', 'claimed_at', 'flushed_at', 'last_error'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'row_count' => 'integer',
            'claimed_at' => 'immutable_datetime',
            'flushed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<LogApp, $this> */
    public function app(): BelongsTo
    {
        return $this->belongsTo(LogApp::class, 'log_app_id');
    }
}
