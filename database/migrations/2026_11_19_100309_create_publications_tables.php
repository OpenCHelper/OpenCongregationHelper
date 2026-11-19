<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The publications offered on paper to a new installation, named in its language (they can be changed later),
     * with their category and how often they renew (in months).
     *
     * @var array<string, array{category: string, renewal_months: int}>
     */
    private const DEFAULT_PUBLICATIONS = [
        'watchtower' => ['category' => 'watchtower', 'renewal_months' => 1],
        'watchtower_large_print' => ['category' => 'watchtower', 'renewal_months' => 1],
        'meeting_workbook' => ['category' => 'meeting_workbooks', 'renewal_months' => 2],
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
            $table->unsignedTinyInteger('renewal_months')->nullable()->comment('Requested again every N months; empty if not renewable');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true)->comment('Offered to the publishers');
            $table->timestamps();
        });

        Schema::create('publication_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // The publications received regularly: each one is requested again every period, until it is stopped
        Schema::create('publication_subscriptions', function (Blueprint $table) {
            $table->id();
            // A short column, so that the name of its foreign key (unique in the whole database) stays within the 64
            // characters of MySQL with the table prefix of the installation (e.g. `p9999_` on the previews)
            $table->foreignId('subscriber_id')->constrained('publication_subscribers')->cascadeOnDelete();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->date('started_at');
            $table->date('ended_at')->nullable()->comment('Stopped: no longer requested again');
            $table->timestamps();

            $table->index(['subscriber_id', 'ended_at']);
        });

        Schema::create('publication_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publication_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->comment('Requested again by this subscription')
                ->constrained('publication_subscriptions')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->date('requested_at');
            $table->date('ordered_at')->nullable()->comment('Optional: ordered from the branch');
            $table->date('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['requested_at']);
            $table->index(['delivered_at']);
        });

        $locale = config('app.locale');

        DB::table('publications')->insert(array_map(
            static fn (string $key, array $publication, int $index): array => [
                'name' => __("filament-resources.publication.defaults.{$key}.name", [], $locale),
                'category' => $publication['category'],
                'short_name' => __("filament-resources.publication.defaults.{$key}.short_name", [], $locale),
                'renewal_months' => $publication['renewal_months'],
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
        Schema::dropIfExists('publication_requests');
        Schema::dropIfExists('publication_subscriptions');
        Schema::dropIfExists('publication_subscribers');
        Schema::dropIfExists('publications');
    }
};
