<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('todos');
    }

    public function down(): void
    {
        // Intentionally not restored — the todos table and its data
        // are fully superseded by CHANGELOG.md (see
        // docs/superpowers/specs/2026-07-22-jyu-132-file-based-changelog-design.md).
    }
};
