<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_event_locators', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->string('app_slug', 100);
            $table->timestampTz('occurred_at');
        });

        Schema::create('log_hourly_counts', function (Blueprint $table): void {
            $table->timestampTz('bucket');
            $table->string('app_slug', 100);
            $table->string('level', 20);
            $table->unsignedBigInteger('count');
            $table->timestampsTz();
            $table->primary(['bucket', 'app_slug', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_hourly_counts');
        Schema::dropIfExists('log_event_locators');
    }
};
