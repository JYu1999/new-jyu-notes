<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_references', function (Blueprint $table) {
            $table->id();
            $table->morphs('source'); // source_type, source_id
            $table->morphs('target'); // target_type, target_id
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'target_type', 'target_id'], 'content_references_unique');
            $table->index(['target_type', 'target_id'], 'content_references_target_idx');
        });

        // Migrate existing post→post references, if the old table exists.
        if (Schema::hasTable('post_references')) {
            DB::table('post_references')->orderBy('id')->each(function ($row) {
                DB::table('content_references')->insert([
                    'source_type' => 'post',
                    'source_id' => $row->source_post_id,
                    'target_type' => 'post',
                    'target_id' => $row->target_post_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            });

            Schema::dropIfExists('post_references');
        }
    }

    public function down(): void
    {
        Schema::create('post_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('target_post_id')->constrained('posts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['source_post_id', 'target_post_id']);
            $table->index('target_post_id');
        });

        if (Schema::hasTable('content_references')) {
            DB::table('content_references')
                ->where('source_type', 'post')
                ->where('target_type', 'post')
                ->orderBy('id')
                ->each(function ($row) {
                    DB::table('post_references')->insert([
                        'source_post_id' => $row->source_id,
                        'target_post_id' => $row->target_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                });
        }

        Schema::dropIfExists('content_references');
    }
};
