<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Home-page order of featured articles (JYU-547). Shared by every language
        // version of an article; null when the article isn't featured.
        Schema::table('posts', function (Blueprint $table) {
            $table->unsignedInteger('featured_order')->nullable()->after('is_featured');
        });

        // Keep today's order (newest first) for articles that are already featured.
        $groups = DB::table('posts')
            ->where('is_featured', true)
            ->whereNull('deleted_at')
            ->groupBy('post_group_id')
            ->selectRaw('post_group_id, max(published_at) as latest')
            ->orderByRaw('max(published_at) desc nulls last')
            ->pluck('post_group_id');

        foreach ($groups as $i => $groupId) {
            DB::table('posts')->where('post_group_id', $groupId)->update(['featured_order' => $i + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('featured_order');
        });
    }
};
