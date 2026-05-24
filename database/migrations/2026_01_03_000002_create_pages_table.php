<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_group_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('slug', 120);
            $table->string('title', 255);
            $table->text('body');
            $table->string('cover_image_path', 500)->nullable();
            $table->string('status', 20)->default('published');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['locale', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX pages_group_locale_unique ON pages (page_group_id, locale) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX pages_locale_slug_unique ON pages (locale, slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
