<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 「記住我」now lasts 7 days (JYU-565), but cookies issued before that were
     * set to live for ~400 days. Clearing every remember token makes those old
     * cookies stop working; logged-in sessions are untouched, and the next login
     * with 「記住我」 issues a fresh 7-day cookie.
     */
    public function up(): void
    {
        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }

    public function down(): void
    {
        // Nothing to restore: the old tokens are gone on purpose.
    }
};
