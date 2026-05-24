<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_group_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('slug', 255);
            $table->string('title', 255);
            $table->text('excerpt')->nullable();
            $table->text('body');
            $table->string('cover_image_path', 500)->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->integer('views_count')->default(0);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('last_modified_at');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['locale', 'status', 'published_at'], 'posts_locale_status_published_idx');
            $table->index(['locale', 'status', 'last_modified_at'], 'posts_locale_status_modified_idx');
            $table->index(['locale', 'status', 'views_count'], 'posts_locale_status_views_idx');
        });

        DB::statement('CREATE UNIQUE INDEX posts_group_locale_unique ON posts (post_group_id, locale) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX posts_locale_slug_unique ON posts (locale, slug) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX posts_status_featured_idx ON posts (status, is_featured) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
