<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The publications offered on paper to a new installation (the Bible, the periodicals and the publications
     * in print since 2015), with their name in every language of the application (written here, so that this
     * migration always inserts the same rows), their code, category and how often they renew (in months). They
     * can be changed, turned off or deleted later like any other publication.
     *
     * The codes are the ones of the index of the publications ("Abbreviations of Publication Titles").
     *
     * @see https://wol.jw.org/en/wol/d/r1/lp-e/1200270068
     *
     * @var list<array{name: array<string, string>, code: string, category: string, renewal_months: ?int}>
     */
    private const DEFAULT_PUBLICATIONS = [
        [
            'name' => ['en' => 'New World Translation of the Holy Scriptures', 'it' => 'Traduzione del Nuovo Mondo delle Sacre Scritture', 'es' => 'La Biblia. Traducción del Nuevo Mundo'],
            'code' => 'nwt',
            'category' => 'bibles',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'New World Translation of the Holy Scriptures (large print)', 'it' => 'Traduzione del Nuovo Mondo delle Sacre Scritture (caratteri grandi)', 'es' => 'La Biblia. Traducción del Nuevo Mundo (letra grande)'],
            // The codes of the editions not in the index of the publications are the ones used by the congregations
            'code' => 'nwtls',
            'category' => 'bibles',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'New World Translation of the Holy Scriptures (pocket edition)', 'it' => 'Traduzione del Nuovo Mondo delle Sacre Scritture (formato tascabile)', 'es' => 'La Biblia. Traducción del Nuevo Mundo (edición de bolsillo)'],
            'code' => 'nwtpkt',
            'category' => 'bibles',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'The Watchtower', 'it' => 'La Torre di Guardia', 'es' => 'La Atalaya'],
            'code' => 'w',
            'category' => 'watchtower',
            'renewal_months' => 1,
        ],
        [
            'name' => ['en' => 'The Watchtower (large print)', 'it' => 'La Torre di Guardia (caratteri grandi)', 'es' => 'La Atalaya (letra grande)'],
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
        // The other publications in print since 2015, as in the index of the publications (requested once)
        [
            'name' => ['en' => 'New World Translation of the Holy Scriptures (Study Edition)', 'it' => 'Traduzione del Nuovo Mondo delle Sacre Scritture (edizione per lo studio)', 'es' => 'La Biblia. Traducción del Nuevo Mundo (edición de estudio)'],
            'code' => 'nwtsty',
            'category' => 'bibles',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'What Can the Bible Teach Us?', 'it' => 'Cosa ci insegna la Bibbia?', 'es' => '¿Qué nos enseña la Biblia?'],
            'code' => 'bhs',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => '“Bearing Thorough Witness” About God’s Kingdom', 'it' => 'Rendiamo “completa testimonianza in merito al Regno di Dio”', 'es' => 'Demos “un testimonio completo sobre el Reino de Dios”'],
            'code' => 'bt',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => '“Come Be My Follower”', 'it' => '“Vieni, sii mio discepolo”', 'es' => '“Ven y sé mi seguidor”'],
            'code' => 'cf',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Draw Close to Jehovah', 'it' => 'Avviciniamoci a Geova', 'es' => 'Acerquémonos a Jehová'],
            'code' => 'cl',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Insight on the Scriptures, Volume 1', 'it' => 'Perspicacia nello studio delle Scritture, volume 1', 'es' => 'Perspicacia para comprender las Escrituras, volumen 1'],
            'code' => 'it-1',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Insight on the Scriptures, Volume 2', 'it' => 'Perspicacia nello studio delle Scritture, volume 2', 'es' => 'Perspicacia para comprender las Escrituras, volumen 2'],
            'code' => 'it-2',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Jesus—The Way, the Truth, the Life', 'it' => 'Gesù: la via, la verità, la vita', 'es' => 'Jesús: el camino, la verdad y la vida'],
            'code' => 'jy',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Lessons You Can Learn From the Bible', 'it' => 'Impariamo dai racconti della Bibbia', 'es' => 'Lecciones que aprendo de la Biblia'],
            'code' => 'lfb',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Enjoy Life Forever!—An Interactive Bible Course', 'it' => 'Puoi vivere felice per sempre. Corso biblico interattivo', 'es' => '¡Disfrute de la vida para siempre! Curso interactivo de la Biblia'],
            'code' => 'lff',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'How to Remain in God’s Love', 'it' => 'Come rimanere nell’amore di Dio', 'es' => 'Continúe en el amor de Dios'],
            'code' => 'lvs',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Organized to Do Jehovah’s Will', 'it' => 'Organizzati per fare la volontà di Geova', 'es' => 'Organizados para hacer la voluntad de Jehová'],
            'code' => 'od',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Pure Worship of Jehovah—Restored At Last!', 'it' => 'La pura adorazione di Geova: finalmente ristabilita!', 'es' => 'La adoración pura de Jehová: ¡por fin restaurada!'],
            'code' => 'rr',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Scriptures for Christian Living', 'it' => 'Versetti utili nella vita di un cristiano', 'es' => 'Textos bíblicos para la vida cristiana'],
            'code' => 'scl',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Walk Courageously With God', 'it' => 'Cammina coraggiosamente con Dio', 'es' => 'Seamos valientes al andar con Dios'],
            'code' => 'wcg',
            'category' => 'books',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Jehovah’s Witnesses and Education', 'it' => 'I Testimoni di Geova e l’istruzione', 'es' => 'Los testigos de Jehová y la educación'],
            'code' => 'ed',
            'category' => 'brochures_and_booklets',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Enjoy Life Forever!—Introductory Bible Lessons', 'it' => 'Puoi vivere felice per sempre. Lezioni bibliche introduttive', 'es' => '¡Disfrute de la vida para siempre! Introducción a las enseñanzas de la Biblia'],
            'code' => 'lffi',
            'category' => 'brochures_and_booklets',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Love People—Make Disciples', 'it' => 'Ama le persone. Fai discepoli', 'es' => 'Hacer discípulos: una obra de amor'],
            'code' => 'lmd',
            'category' => 'brochures_and_booklets',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Return to Jehovah', 'it' => 'Ritorna a Geova', 'es' => 'Jehová desea que vuelva'],
            'code' => 'rj',
            'category' => 'brochures_and_booklets',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Apply Yourself to Reading and Teaching', 'it' => 'Applicati alla lettura e all’insegnamento', 'es' => 'Seamos mejores lectores y maestros'],
            'code' => 'th',
            'category' => 'brochures_and_booklets',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Wisdom From the Gospels', 'it' => 'Perle di saggezza dal Vangelo', 'es' => 'La sabiduría de los Evangelios'],
            'code' => 'wfg',
            'category' => 'brochures_and_booklets',
            'renewal_months' => null,
        ],
        [
            'name' => ['en' => 'Answers to 10 Questions Young People Ask', 'it' => '10 domande dei ragazzi', 'es' => 'Respuestas a 10 preguntas de los jóvenes'],
            'code' => 'ypq',
            'category' => 'brochures_and_booklets',
            'renewal_months' => null,
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
            $table->string('code', 50)->unique()->comment('Code of the publication (e.g. w, mwb), the same in every language');
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
            $table->date('last_period_on')->nullable()->comment('Day of the last period requested, from which the next one is counted');
            $table->timestamps();

            $table->index(['subscriber_id', 'ended_at']);
        });

        Schema::create('publication_requests', function (Blueprint $table) {
            $table->id();
            // Kept when the publisher is deleted: the requests are the history of the publications
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
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
            // A subscription requests each period once (the one-off requests have no subscription)
            $table->unique(['subscription_id', 'requested_at']);
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
