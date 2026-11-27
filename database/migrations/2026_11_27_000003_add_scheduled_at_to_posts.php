<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Scheduled publishing (JYU-549): a draft with scheduled_at is published
        // automatically by `posts:publish-scheduled` once that time has passed.
        Schema::table('posts', function (Blueprint $table) {
            $table->timestampTz('scheduled_at')->nullable()->after('published_at');
            $table->index(['status', 'scheduled_at'], 'posts_status_scheduled_idx');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_status_scheduled_idx');
            $table->dropColumn('scheduled_at');
        });
    }
};
