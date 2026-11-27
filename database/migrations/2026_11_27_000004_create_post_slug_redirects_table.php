<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Old URLs of posts whose slug changed after they were published (JYU-550).
        // /{locale}/posts/{old_slug} answers with a 301 to the post's current URL.
        Schema::create('post_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('old_slug');
            $table->timestampsTz();
            $table->unique(['locale', 'old_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_slug_redirects');
    }
};
