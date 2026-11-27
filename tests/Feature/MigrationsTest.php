<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Load one of the application's migrations by its file name.
 */
function loadMigration(string $name): Migration
{
    return require database_path("migrations/{$name}.php");
}

it('rolls back every migration and runs them again', function () {
    expect(Artisan::call('migrate:reset', ['--force' => true]))->toBe(0)
        ->and(Schema::hasTable('users'))->toBeFalse()
        ->and(Schema::hasTable('mail_logs'))->toBeFalse()
        ->and(Artisan::call('migrate', ['--force' => true]))->toBe(0)
        ->and(Schema::hasTable('mail_logs'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'two_factor_confirmed_at'))->toBeTrue();
});

afterEach(function () {
    // On MySQL the schema changes of the migrations commit the test transaction, so the user would outlive the test
    DB::table('users')->where('email', 'maria@example.com')->delete();
});

it('keeps the names of the users when splitting and joining them', function () {
    $splitFullName = loadMigration('2025_04_13_075827_split_user_fullname');
    $userId = DB::table('users')->insertGetId([
        'first_name' => 'Maria',
        'last_name' => 'De Luca',
        'email' => 'maria@example.com',
    ]);

    $splitFullName->down();

    expect(DB::table('users')->where('id', $userId)->value('name'))->toBe('Maria De Luca');

    $splitFullName->up();

    expect(DB::table('users')->where('id', $userId)->first(['first_name', 'last_name']))
        ->first_name->toBe('Maria')
        ->last_name->toBe('De Luca')
        ->and(Schema::hasColumn('users', 'name'))->toBeFalse();
});

it('does not add the two factor confirmation column twice', function () {
    expect(Schema::hasColumn('users', 'two_factor_confirmed_at'))->toBeTrue();

    loadMigration('2026_08_14_100113_add_two_factor_columns_to_users_table')->up();

    expect(Schema::hasColumn('users', 'two_factor_confirmed_at'))->toBeTrue();
});

it('drops the unused team tables and rolls them back', function () {
    $dropTeamTables = loadMigration('2026_11_25_233937_drop_team_tables');

    expect(Schema::hasTable('teams'))->toBeFalse()
        ->and(Schema::hasTable('team_user'))->toBeFalse()
        ->and(Schema::hasTable('team_invitations'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'current_team_id'))->toBeFalse();

    $dropTeamTables->down();

    expect(Schema::hasTable('teams'))->toBeTrue()
        ->and(Schema::hasTable('team_user'))->toBeTrue()
        ->and(Schema::hasTable('team_invitations'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'current_team_id'))->toBeTrue();

    $dropTeamTables->up();
    // Running it again on a database without the tables does nothing
    $dropTeamTables->up();

    expect(Schema::hasTable('teams'))->toBeFalse()
        ->and(Schema::hasTable('team_invitations'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'current_team_id'))->toBeFalse();
});

it('rolls back the settings and adds them again', function (string $file) {
    $migration = require $file;
    $settings = fn () => DB::table('settings')->get(['group', 'name'])->map(fn (object $setting): string => "{$setting->group}.{$setting->name}");
    preg_match_all("/migrator->add\\('([^']+)'/", file_get_contents($file), $matches);

    $migration->down();

    expect($settings()->intersect($matches[1]))->toBeEmpty();

    // Adding them again would throw if the rollback had left any of them in place
    $migration->up();

    expect($settings()->intersect($matches[1])->sort()->values()->all())->toBe(collect($matches[1])->sort()->values()->all());
})->with(fn (): array => collect(glob(dirname(__DIR__, 2).'/database/settings/*.php'))
    ->mapWithKeys(fn (string $file): array => [basename($file, '.php') => [$file]])
    ->all());
