<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_post', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->integer('order_in_category')->nullable();
            $table->primary(['category_id', 'post_id']);
            $table->index(['post_id', 'category_id']);
            $table->index(['category_id', 'order_in_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_post');
    }
};
