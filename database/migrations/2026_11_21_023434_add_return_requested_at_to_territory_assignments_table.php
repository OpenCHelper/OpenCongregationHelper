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
            $table->dateTime('return_requested_at')->nullable()->after('ended_at')
                ->comment('When the return of the territory was asked to the territory department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('territory_assignments', function (Blueprint $table) {
            $table->dropColumn('return_requested_at');
        });
    }
};
