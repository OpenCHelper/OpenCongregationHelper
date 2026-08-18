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
        Schema::rename('territory_assignment_types', 'campaigns');

        Schema::table('territory_assignments', function (Blueprint $table) {
            $table->renameColumn('territory_assignment_type_id', 'campaign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('territory_assignments', function (Blueprint $table) {
            $table->renameColumn('campaign_id', 'territory_assignment_type_id');
        });

        Schema::rename('campaigns', 'territory_assignment_types');
    }
};
