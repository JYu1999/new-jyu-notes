<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * The old API tokens are replaced by AI connections (JYU-557). Every token stops
     * working; before dropping the table, a record of which tokens existed (name,
     * abilities, dates — never the token hash) is written to
     * storage/app/private/exports/personal_access_tokens-<timestamp>.json.
     */
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        $rows = DB::table('personal_access_tokens')
            ->select(['id', 'tokenable_type', 'tokenable_id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at', 'updated_at'])
            ->orderBy('id')->get();

        if ($rows->isNotEmpty()) {
            Storage::disk('local')->put(
                'exports/personal_access_tokens-'.now()->format('Ymd-His').'.json',
                json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            );
        }

        Schema::drop('personal_access_tokens');
    }

    /** Recreates the (empty) table; the tokens themselves cannot be restored. */
    public function down(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }
};
