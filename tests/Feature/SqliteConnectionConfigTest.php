<?php

/**
 * The SQLite connection can wait for the other writers, e.g. the end-to-end tests (see `tests/e2e/support/serve.sh`).
 */
test('the busy timeout and the journal mode of SQLite come from the environment', function () {
    putenv('DB_BUSY_TIMEOUT=15000');
    putenv('DB_JOURNAL_MODE=wal');

    try {
        $config = require config_path('database.php');
    } finally {
        putenv('DB_BUSY_TIMEOUT');
        putenv('DB_JOURNAL_MODE');
    }

    expect($config['connections']['sqlite']['busy_timeout'])->toBe('15000')
        ->and($config['connections']['sqlite']['journal_mode'])->toBe('wal');
});

test('SQLite keeps its defaults when the environment sets nothing', function () {
    $config = require config_path('database.php');

    expect($config['connections']['sqlite']['busy_timeout'])->toBeNull()
        ->and($config['connections']['sqlite']['journal_mode'])->toBeNull();
});
