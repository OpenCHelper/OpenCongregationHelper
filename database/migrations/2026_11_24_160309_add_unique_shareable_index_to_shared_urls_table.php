<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations: a model has one link at most, so the extra links made by concurrent shares are deleted
     * (keeping the oldest one, which may have been sent already).
     */
    public function up(): void
    {
        $keptIds = DB::table('shared_urls')
            ->selectRaw('MIN(id) as id')
            ->groupBy('shareable_type', 'shareable_id')
            ->pluck('id');

        DB::table('shared_urls')->whereNotIn('id', $keptIds)->delete();

        Schema::table('shared_urls', function (Blueprint $table) {
            $table->unique(['shareable_type', 'shareable_id']);
            $table->dropIndex(['shareable_type', 'shareable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_urls', function (Blueprint $table) {
            $table->index(['shareable_type', 'shareable_id']);
            $table->dropUnique(['shareable_type', 'shareable_id']);
        });
    }
};
