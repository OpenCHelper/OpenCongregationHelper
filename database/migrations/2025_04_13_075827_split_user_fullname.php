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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
            $table
                ->string('first_name')
                ->after('email')
                ->comment('First name of the user');
            $table
                ->string('last_name')
                ->after('first_name')
                ->comment('Last name of the user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
            $table
                ->string('name')
                ->after('email')
                ->comment('Full name of the user');
        });
    }
};
