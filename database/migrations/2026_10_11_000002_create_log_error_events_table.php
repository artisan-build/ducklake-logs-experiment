<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_error_events', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->string('app_slug', 100);
            $table->timestampTz('occurred_at');
            $table->string('level', 20);
            $table->string('channel', 100);
            $table->text('message');
            $table->json('context');
            $table->json('extra');
            $table->index(['app_slug', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_error_events');
    }
};
