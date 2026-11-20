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
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('posters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique()->comment('Symbol of the poster, e.g. "mi-25"');
            $table->json('title')->comment('Translated in every language (spatie/laravel-translatable)');
            $table->string('image_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('poster_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storage_location_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['poster_id', 'storage_location_id']);
        });

        // The department managing the posters (see `App\Enums\DepartmentKey`)
        DB::table('departments')->insert(['key' => 'public_witnessing', 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('departments')->where('key', 'public_witnessing')->delete();
        Schema::dropIfExists('poster_stocks');
        Schema::dropIfExists('posters');
        Schema::dropIfExists('storage_locations');
    }
};
