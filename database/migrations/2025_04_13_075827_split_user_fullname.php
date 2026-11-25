<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table
                ->string('first_name')
                ->nullable()
                ->after('email')
                ->comment('First name of the user');
            $table
                ->string('last_name')
                ->nullable()
                ->after('first_name')
                ->comment('Last name of the user');
        });

        DB::table('users')->select(['id', 'name'])->orderBy('id')->each(function (object $user): void {
            $nameParts = preg_split('/\s+/', trim((string) $user->name), 2);

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $nameParts[0] ?? '',
                'last_name' => $nameParts[1] ?? '',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->comment('First name of the user')->change();
            $table->string('last_name')->nullable(false)->comment('Last name of the user')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table
                ->string('name')
                ->nullable()
                ->after('email')
                ->comment('Full name of the user');
        });

        DB::table('users')->select(['id', 'first_name', 'last_name'])->orderBy('id')->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'name' => trim("{$user->first_name} {$user->last_name}"),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable(false)->comment('Full name of the user')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
