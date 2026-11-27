<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cached title of external pages linked from tweets (JYU-540).
        Schema::create('link_previews', function (Blueprint $table) {
            $table->id();
            $table->char('url_hash', 40)->unique(); // sha1(url): URLs can be longer than an index allows
            $table->text('url');
            $table->string('domain');
            $table->string('title', 300)->nullable();
            $table->string('status', 16); // ok | failed
            $table->timestamp('fetched_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_previews');
    }
};
