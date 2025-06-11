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
        Schema::create('shared_urls', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->morphs('shareable');
            $table->uuid('uid')->unique()->comment('Unique identifier for the shared URL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_urls');
    }
};
