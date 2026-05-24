<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_view_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            $table->timestampTz('viewed_at');

            $table->index(['post_id', 'fingerprint', 'viewed_at'], 'post_view_logs_dedup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_view_logs');
    }
};
