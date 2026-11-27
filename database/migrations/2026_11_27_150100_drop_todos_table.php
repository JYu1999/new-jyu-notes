<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * The Todo feature was replaced by CHANGELOG.md (JYU-132) and is removed (JYU-559).
     * Every todo row is first exported to storage/app/private/exports/todos-<timestamp>.json
     * so nothing is lost; the export is checked before the table is dropped.
     */
    public function up(): void
    {
        if (! Schema::hasTable('todos')) {
            return;
        }

        $rows = DB::table('todos')->orderBy('id')->get();

        if ($rows->isNotEmpty()) {
            $path = 'exports/todos-'.now()->format('Ymd-His').'.json';
            Storage::disk('local')->put($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Never drop the data unless the export really contains every row.
            $saved = json_decode((string) Storage::disk('local')->get($path), true);
            if (! is_array($saved) || count($saved) !== $rows->count()) {
                throw new RuntimeException("Todo export to {$path} is incomplete; the todos table was not dropped.");
            }
        }

        Schema::drop('todos');
    }

    /** Recreates the (empty) table; restore rows from the export if ever needed. */
    public function down(): void
    {
        Schema::create('todos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->string('status', 10)->default('open');
            $table->boolean('show_in_changelog')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'show_in_changelog', 'completed_at']);
        });
    }
};
