<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The departments of the congregation (see `App\Enums\DepartmentKey`).
     */
    private const DEPARTMENTS = ['territories'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique()->comment('See App\Enums\DepartmentKey');
            $table->timestamps();
        });

        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 50)->default('assistant')->comment('See App\Enums\DepartmentRole');
            $table->timestamps();

            $table->unique(['department_id', 'user_id']);
        });

        DB::table('departments')->insert(array_map(
            static fn (string $key): array => ['key' => $key, 'created_at' => now(), 'updated_at' => now()],
            self::DEPARTMENTS,
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_user');
        Schema::dropIfExists('departments');
    }
};
