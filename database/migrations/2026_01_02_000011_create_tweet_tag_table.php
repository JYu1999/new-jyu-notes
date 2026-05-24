<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tweet_tag', function (Blueprint $table) {
            $table->foreignId('tweet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['tweet_id', 'tag_id']);
            $table->index(['tag_id', 'tweet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tweet_tag');
    }
};
