<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('territory_assignments', function (Blueprint $table) {
            $table->renameColumn('date_start', 'started_at');
            $table->renameColumn('date_end', 'ended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('territory_assignments', function (Blueprint $table) {
            $table->renameColumn('started_at', 'date_start');
            $table->renameColumn('ended_at', 'date_end');
        });
    }
};
