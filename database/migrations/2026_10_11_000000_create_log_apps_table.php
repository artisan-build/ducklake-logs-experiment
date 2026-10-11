<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_apps', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('last_ingested_at')->nullable();
            $table->unsignedBigInteger('accepted_lines')->default(0);
            $table->unsignedBigInteger('flushed_lines')->default(0);
            $table->timestampsTz();
        });

        Schema::create('log_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('log_app_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 100);
            $table->json('payload')->nullable();
            $table->unsignedInteger('row_count');
            $table->string('status', 20)->default('pending')->index();
            $table->timestampTz('claimed_at')->nullable()->index();
            $table->timestampTz('flushed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->unique(['log_app_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_batches');
        Schema::dropIfExists('log_apps');
    }
};
