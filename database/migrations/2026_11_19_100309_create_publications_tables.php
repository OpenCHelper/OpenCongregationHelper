<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The periodicals offered on paper to a new installation, with their name in every language of the application
     * (written here, so that this migration always inserts the same rows), their code, category and how often they
     * renew (in months). They can be changed, turned off or deleted later like any other publication.
     *
     * @var list<array{name: array<string, string>, code: string, category: string, renewal_months: ?int}>
     */
    private const DEFAULT_PUBLICATIONS = [
        [
            'name' => ['en' => 'The Watchtower', 'it' => 'La Torre di Guardia', 'es' => 'La Atalaya'],
            'code' => 'w',
            'category' => 'watchtower',
            'renewal_months' => 1,
        ],
        [
            'name' => ['en' => 'The Watchtower (large print)', 'it' => 'La Torre di Guardia (caratteri grandi)', 'es' => 'La Atalaya (letra grande)'],
            // TODO: verify the code of the large-print edition
            'code' => 'wlp',
            'category' => 'watchtower',
            'renewal_months' => 1,
        ],
        [
            'name' => ['en' => 'The Watchtower (public edition)', 'it' => 'La Torre di Guardia (edizione per il pubblico)', 'es' => 'La Atalaya (edición para el público)'],
            'code' => 'wp',
            'category' => 'watchtower',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Awake!', 'it' => 'Svegliatevi!', 'es' => '¡Despertad!'],
            'code' => 'g',
            'category' => 'awake',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Meeting Workbook', 'it' => 'Guida per l\'adunanza', 'es' => 'Guía de actividades'],
            'code' => 'mwb',
            'category' => 'meeting_workbooks',
            'renewal_months' => 2,
        ],
        [
            'name' => ['en' => 'Meeting Workbook (large print)', 'it' => 'Guida per l\'adunanza (caratteri grandi)', 'es' => 'Guía de actividades (letra grande)'],
            // TODO: verify the code of the large-print edition
            'code' => 'mwblp',
            'category' => 'meeting_workbooks',
            'renewal_months' => 2,
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->json('name')->comment('Translated in every language (spatie/laravel-translatable)');
            $table->string('category', 50)->default('other')->comment('See App\Enums\PublicationCategory');
            $table->string('code', 50)->nullable()->comment('Code of the publication (e.g. w, mwb), the same in every language');
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

        DB::table('publications')->insert(array_map(
            static fn (array $publication, int $index): array => [
                'name' => json_encode($publication['name'], JSON_UNESCAPED_UNICODE),
                'code' => $publication['code'],
                'category' => $publication['category'],
                'renewal_months' => $publication['renewal_months'],
                'sort' => $index + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            self::DEFAULT_PUBLICATIONS,
            array_keys(self::DEFAULT_PUBLICATIONS),
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
