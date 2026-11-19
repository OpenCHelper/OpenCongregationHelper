<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The publications offered on paper to a new installation, named in its language (they can be changed later).
     */
    private const DEFAULT_PUBLICATIONS = [
        'watchtower' => 'watchtower',
        'watchtower_large_print' => 'watchtower',
        'meeting_workbook' => 'meeting_workbooks',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 50)->default('other')->comment('See App\Enums\PublicationCategory');
            $table->string('short_name', 50)->nullable()->comment('Shown in the headers of the columns');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true)->comment('Offered to the publishers');
            $table->timestamps();
        });

        Schema::create('periodical_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('periodical_subscription_items', function (Blueprint $table) {
            $table->id();
            // A short column, so that the name of its foreign key (unique in the whole database) stays within the 64
            // characters of MySQL with the table prefix of the installation (e.g. `p9999_` on the previews)
            $table->foreignId('subscription_id')->constrained('periodical_subscriptions')->cascadeOnDelete();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            // Named, as the generated name would be too long (the names of indexes are unique only in their table)
            $table->unique(['subscription_id', 'publication_id'], 'periodical_subscription_items_unique');
        });

        $locale = config('app.locale');

        DB::table('publications')->insert(array_map(
            static fn (string $key, string $category, int $index): array => [
                'name' => __("filament-resources.publication.defaults.{$key}.name", [], $locale),
                'category' => $category,
                'short_name' => __("filament-resources.publication.defaults.{$key}.short_name", [], $locale),
                'sort' => $index + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            array_keys(self::DEFAULT_PUBLICATIONS),
            self::DEFAULT_PUBLICATIONS,
            range(0, count(self::DEFAULT_PUBLICATIONS) - 1),
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodical_subscription_items');
        Schema::dropIfExists('periodical_subscriptions');
        Schema::dropIfExists('publications');
    }
};
