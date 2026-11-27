<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An AI (OAuth client) the author connected, with the permissions and
        // expiry chosen on the consent screen (JYU-554).
        Schema::create('ai_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('oauth_client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->string('name');
            $table->jsonb('permissions');
            $table->timestampTz('expires_at');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->unique(['user_id', 'oauth_client_id']);
        });

        // "Publish / hide this" requests from an AI, waiting for the author (JYU-554 / JYU-556).
        Schema::create('ai_publish_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_connection_id')->nullable()->constrained('ai_connections')->nullOnDelete();
            $table->morphs('publishable');
            $table->string('action', 10); // publish | hide
            $table->text('note')->nullable();
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_publish_requests');
        Schema::dropIfExists('ai_connections');
    }
};
