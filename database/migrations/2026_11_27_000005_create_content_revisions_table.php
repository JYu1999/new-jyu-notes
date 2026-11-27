<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Version history of posts, tweets and pages (JYU-553). Each row is a full
        // snapshot of the editable fields after a change; rows are kept when the
        // content is moved to the trash.
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('revisionable');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor', 10)->default('user'); // user | ai | system
            $table->string('actor_name')->nullable();
            $table->string('kind', 10); // create | edit | status | restore
            $table->string('summary');
            $table->jsonb('snapshot');
            $table->unsignedInteger('chars_added')->default(0);
            $table->unsignedInteger('chars_removed')->default(0);
            $table->timestampsTz();
            $table->index(['revisionable_type', 'revisionable_id', 'id'], 'content_revisions_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
    }
};
