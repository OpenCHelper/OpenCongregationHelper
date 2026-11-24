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
