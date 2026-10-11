<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LogApp extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'token_hash', 'last_ingested_at', 'accepted_lines', 'flushed_lines'];

    protected function casts(): array
    {
        return [
            'last_ingested_at' => 'immutable_datetime',
            'accepted_lines' => 'integer',
            'flushed_lines' => 'integer',
        ];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(LogBatch::class);
    }

    /** @return array{0: self, 1: string} */
    public static function issue(string $name, ?string $slug = null): array
    {
        $token = 'dlk_'.Str::random(48);
        $app = self::query()->create([
            'name' => $name,
            'slug' => $slug ?: Str::slug($name),
            'token_hash' => hash('sha256', $token),
        ]);

        return [$app, $token];
    }
}
