<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE posts ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(excerpt, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(body, '')), 'C')
            ) STORED
        ");
        DB::statement("CREATE INDEX posts_search_vector_idx ON posts USING GIN (search_vector)");

        DB::statement("
            ALTER TABLE tweets ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (to_tsvector('simple', coalesce(body, ''))) STORED
        ");
        DB::statement("CREATE INDEX tweets_search_vector_idx ON tweets USING GIN (search_vector)");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS posts_search_vector_idx");
        DB::statement("ALTER TABLE posts DROP COLUMN IF EXISTS search_vector");
        DB::statement("DROP INDEX IF EXISTS tweets_search_vector_idx");
        DB::statement("ALTER TABLE tweets DROP COLUMN IF EXISTS search_vector");
    }
};
