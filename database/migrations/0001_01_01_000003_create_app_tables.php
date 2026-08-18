<?php

use App\Models\Territory;
use App\Models\TerritoryZone;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function _up(): void
    {
        // Previous table: `zone`
        Schema::create('territory_zones', function (Blueprint $table) {
            $table->comment('Territory grouping data');

            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->tinyText('color')->nullable()->comment('Optional color code (hex, rgb, etc.)');

            $table->foreignIdFor(User::class, 'created_by')->comment('User who created this record')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        // Previous table: `territori`
        Schema::create('territories', function (Blueprint $table) {
            $table->comment('Territory data');

            $table->id();
            $table->string('name');                     // Old name: `id_territorio`
            $table->text('description')->nullable();
            $table->string('image_path')->nullable()->comment('Path to a static image file');
            $table->json('geojson')->nullable()->comment('GeoJSON data for rendering on an interactive map');
            $table->text('notes')->nullable();
            $table->boolean('is_commercial')->default(false)->comment('Is this a commercial territory?');

            $table->foreignIdFor(TerritoryZone::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class, 'created_by')->comment('User who created this record')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        // Previous table: `tipo_campagne`
        Schema::create('territory_assignment_types', function (Blueprint $table) {
            $table->comment('Territory assignment types');

            $table->id();
            $table->string('name');
            $table->tinyText('short_name')->nullable()->comment('Short name for display purposes');
            $table->text('description')->nullable();
            $table->tinyText('color')->nullable()->comment('Optional color code (hex, rgb, etc.)');

            $table->dateTime('valid_from')->nullable()->comment('Valid from date');
            $table->dateTime('valid_until')->nullable()->comment('Valid until date');

            $table->foreignIdFor(User::class, 'created_by')->comment('User who created this record')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        // Previous table: `storico_uscite`
        Schema::create('territory_assignments', function (Blueprint $table) {
            $table->comment('Territory assignment data');

            $table->id();
            $table->dateTime('date_start')->comment('Start of assignation')->useCurrent();
            $table->dateTime('date_end')->nullable();
            $table->text('notes')->nullable();

            // Delete the record if either the user or territory get deleted
            $table->foreignIdFor(User::class, 'assignee_id')->comment('User the territory has been assigned to')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Territory::class)->constrained()->cascadeOnDelete();
            $table->foreignId('territory_assignment_type_id')->comment('Territory assignment type')->nullable()->constrained('territory_assignment_types')->nullOnDelete();

            $table->foreignIdFor(User::class, 'created_by')->comment('User who created the record')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function up(): void
    {
        try {
            $this->_up();
        } catch (Exception $e) {
            $this->down();
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('territory_assignments');
        Schema::dropIfExists('territory_assignment_types');
        Schema::dropIfExists('territories');
        Schema::dropIfExists('territory_zones');
    }
};
