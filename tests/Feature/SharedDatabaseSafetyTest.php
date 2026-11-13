<?php

use App\Console\Commands\PrefixAwareWipeCommand;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->originalPrefix = DB::connection()->getTablePrefix();
});

afterEach(function () {
    DB::connection()->setTablePrefix('');
    Schema::dropIfExists('other_migrations');

    if ($this->createdMigrationsTable ?? false) {
        Schema::dropIfExists('migrations');
    }

    DB::connection()->setTablePrefix($this->originalPrefix);
});

test('db:wipe is replaced by the prefix-aware command', function () {
    expect(Artisan::all()['db:wipe'])->toBeInstanceOf(PrefixAwareWipeCommand::class);
});

test('db:wipe drops only the tables of the table prefix of the installation', function () {
    DB::connection()->setTablePrefix('zz9_');

    Schema::create('wipe_parents', function (Blueprint $table) {
        $table->id();
    });
    Schema::create('wipe_children', function (Blueprint $table) {
        $table->id();
        $table->foreignId('wipe_parent_id')->constrained();
    });

    $this->artisan('db:wipe', ['--force' => true])->assertSuccessful();

    expect(Schema::hasTable('wipe_parents'))->toBeFalse()
        ->and(Schema::hasTable('wipe_children'))->toBeFalse();

    // The tables of the test suite (another installation, as far as `zz9_` is concerned) are still there
    DB::connection()->setTablePrefix($this->originalPrefix);

    expect(Schema::hasTable('users'))->toBeTrue()
        ->and(Schema::hasTable('migrations'))->toBeTrue();
});

test('db:wipe and migrate:fresh refuse to run on an unprefixed database shared with other installations', function (string $command) {
    DB::connection()->setTablePrefix('');

    Schema::create('other_migrations', function (Blueprint $table) {
        $table->id();
    });

    // An installation already migrated without prefix (like TEST before setting it): `migrate:fresh` only wipes the
    // database when the migrations table of the installation exists
    if (! Schema::hasTable('migrations')) {
        Schema::create('migrations', function (Blueprint $table) {
            $table->id();
        });
        $this->createdMigrationsTable = true;
    }

    expect(fn () => Artisan::call($command, ['--force' => true]))
        ->toThrow(RuntimeException::class, 'other_');

    expect(Schema::hasTable('other_migrations'))->toBeTrue();

    DB::connection()->setTablePrefix($this->originalPrefix);

    expect(Schema::hasTable('users'))->toBeTrue();
})->with(['db:wipe', 'migrate:fresh']);
