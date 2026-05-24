<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tweets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tweet_group_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->text('body');
            $table->jsonb('media')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['locale', 'status', 'published_at'], 'tweets_locale_status_published_idx');
        });

        DB::statement('CREATE UNIQUE INDEX tweets_group_locale_unique ON tweets (tweet_group_id, locale) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX tweets_status_published_idx ON tweets (status, published_at DESC) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tweets');
    }
};
