<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations: the database refuses to delete a user who has assignments, instead of permanently deleting
     * their whole assignment history with them (`UserObserver::deleting()` explains it before getting here).
     */
    public function up(): void
    {
        Schema::table('territory_assignments', function (Blueprint $table) {
            $table->dropForeign(['assignee_id']);
            $table->foreign('assignee_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('territory_assignments', function (Blueprint $table) {
            $table->dropForeign(['assignee_id']);
            $table->foreign('assignee_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
