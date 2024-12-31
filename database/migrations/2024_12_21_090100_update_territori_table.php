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
        // Update the existing 'territori' table
        Schema::table('territori', function (Blueprint $table) {
            $table->integer('id')->unsigned()->autoIncrement();
            $table->mediumText('immagine');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('territori', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->mediumText('immagine')->charset('binary');
            $table->dropTimestamps();
        });
    }
};
