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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type', 50)->default('personal'); // personal, emergency, business, etc.
            $table->string('name')->nullable()->comment('Contact person name (for emergency contacts)');
            $table->string('phone_number', 20)->nullable()->comment('Phone number');
            $table->text('address')->nullable()->comment('Address');
            $table->text('notes')->nullable()->comment('Additional notes about this contact');
            $table->boolean('is_primary')->default(false)->comment('Whether this is the primary contact');
            $table->timestamps();

            // Index for better performance
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
