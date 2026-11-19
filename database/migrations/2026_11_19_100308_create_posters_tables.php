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
        Schema::create('storage_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('posters', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique()->comment('Symbol of the poster, e.g. "mi-25"');
            $table->string('title');
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poster_stocks');
        Schema::dropIfExists('posters');
        Schema::dropIfExists('storage_locations');
    }
};
