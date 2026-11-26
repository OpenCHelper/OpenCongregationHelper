<?php

use App\Models\User;
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
        Schema::create('service_groups', function (Blueprint $table) {
            $table->comment('Field service groups of the publishers');

            $table->id();
            $table->string('name');

            $table->foreignIdFor(User::class, 'coordinator_id')->comment('Coordinator of the group')->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class, 'assistant_coordinator1_id')->comment('First assistant of the coordinator')->nullable()->constrained()->nullOnDelete();
            $table->foreignIdFor(User::class, 'assistant_coordinator2_id')->comment('Second assistant of the coordinator')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_groups');
    }
};
