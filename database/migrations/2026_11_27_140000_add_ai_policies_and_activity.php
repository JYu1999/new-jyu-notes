<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow / ask / deny per kind of action, for every AI connection (JYU-555).
        Schema::create('ai_policies', function (Blueprint $table) {
            $table->string('action', 20)->primary();
            $table->string('mode', 10);
            $table->timestampsTz();
        });

        // A connection may only be stricter than the global policy.
        Schema::table('ai_connections', function (Blueprint $table) {
            $table->jsonb('policy')->nullable()->after('permissions');
        });

        // "需確認" actions wait here for the author; publish requests are one kind of them.
        Schema::rename('ai_publish_requests', 'ai_action_requests');
        Schema::table('ai_action_requests', function (Blueprint $table) {
            $table->string('tool', 40)->default('request_publish')->after('ai_connection_id');
            $table->jsonb('arguments')->nullable()->after('tool');
            $table->string('summary')->nullable()->after('arguments');
            $table->text('error')->nullable()->after('status');
            $table->string('action', 20)->change();
            $table->string('publishable_type')->nullable()->change();
            $table->unsignedBigInteger('publishable_id')->nullable()->change();
        });

        // What each AI did (JYU-555 "AI 最近做了什麼").
        Schema::create('ai_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_connection_id')->nullable()->constrained('ai_connections')->nullOnDelete();
            $table->string('tool', 40);
            $table->string('summary');
            $table->string('status', 10)->default('done'); // done | queued | failed
            $table->nullableMorphs('subject');
            $table->foreignId('content_revision_id')->nullable()->constrained('content_revisions')->nullOnDelete();
            $table->foreignId('ai_action_request_id')->nullable()->constrained('ai_action_requests')->nullOnDelete();
            $table->timestampsTz();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_activities');
        Schema::table('ai_action_requests', function (Blueprint $table) {
            $table->dropColumn(['tool', 'arguments', 'summary', 'error']);
        });
        Schema::rename('ai_action_requests', 'ai_publish_requests');
        Schema::table('ai_connections', function (Blueprint $table) {
            $table->dropColumn('policy');
        });
        Schema::dropIfExists('ai_policies');
    }
};
