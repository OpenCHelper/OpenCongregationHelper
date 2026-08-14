<?php

use App\Providers\AppVersionProvider;
use Illuminate\Support\Facades\Config;
use Awcodes\Versions\Providers\Contracts\VersionProvider;

beforeEach(function () {
    // Reset configuration for each test
    Config::set('app.version', '1.0.0');
    Config::set('app.version_info.branch_file', null);
    Config::set('app.version_info.commit_file', null);
});

afterEach(function () {
    // Clean up test files
    $testFiles = [
        base_path('test-git-head'),
        base_path('test-git-branch'),
        base_path('test-git-commit'),
        base_path('test-invalid-branch'),
        base_path('test-invalid-commit'),
    ];

    foreach ($testFiles as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
});

describe('AppVersionProvider', function () {

    it('implements VersionProvider interface', function () {
        $provider = new AppVersionProvider;

        expect($provider)->toBeInstanceOf(VersionProvider::class);
    });

    it('returns correct name', function () {
        $provider = new AppVersionProvider;

        expect($provider->getName())->toBe('App');
    });

    describe('getVersion()', function () {

        it('returns version with git info when git files exist', function () {
            // Create test git HEAD file
            file_put_contents(base_path('test-git-head'), 'ref: refs/heads/main');

            // Create test git branch file
            file_put_contents(base_path('test-git-branch'), 'abcdef1234567890abcdef1234567890abcdef12');

            Config::set('app.version_info.branch_file', 'test-git-head');
            Config::set('app.version_info.commit_file', 'test-git-branch');

            $provider = new AppVersionProvider;
            $version = $provider->getVersion();

            expect($version)->toMatch('/^1\.0\.0 \(main#abcdef1 @ \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\)$/');
        });

        it('handles custom app version from config', function () {
            Config::set('app.version', '2.1.0-beta');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBeString()->toStartWith('2.1.0-beta');
        });

    });

    describe('git branch detection', function () {

        it('returns null when branch file does not exist', function () {
            Config::set('app.version_info.branch_file', 'non-existent-file');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (#unknown)');
        });

        it('parses git HEAD file correctly for branch reference', function () {
            file_put_contents(base_path('test-git-head'), 'ref: refs/heads/feature-branch');
            file_put_contents(base_path('test-git-commit'), 'commit123456789');

            Config::set('app.version_info.branch_file', 'test-git-head');
            Config::set('app.version_info.commit_file', 'test-git-commit');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (feature-branch#commit1)');
        });

        it('parses git HEAD file correctly for tag reference', function () {
            file_put_contents(base_path('test-git-head'), 'ref: refs/tags/v1.0.0');
            file_put_contents(base_path('test-git-commit'), 'tagcommit123456789');

            Config::set('app.version_info.branch_file', 'test-git-head');
            Config::set('app.version_info.commit_file', 'test-git-commit');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (v1.0.0#tagcomm)');
        });

        it('handles direct commit hash in HEAD file', function () {
            file_put_contents(base_path('test-git-head'), 'abcdef1234567890abcdef1234567890abcdef12');

            Config::set('app.version_info.branch_file', 'test-git-head');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (abcdef1234567890abcdef1234567890abcdef12#unknown)');
        });

        it('handles empty branch file', function () {
            file_put_contents(base_path('test-invalid-branch'), '');

            Config::set('app.version_info.branch_file', 'test-invalid-branch');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (#unknown)');
        });

        it('handles whitespace in branch file', function () {
            file_put_contents(base_path('test-git-head'), "  ref: refs/heads/main  \n");
            file_put_contents(base_path('test-git-commit'), "  commit123  \n");

            Config::set('app.version_info.branch_file', 'test-git-head');
            Config::set('app.version_info.commit_file', 'test-git-commit');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (main#commit1)');
        });

    });

    describe('git commit detection', function () {

        it('returns null when commit file does not exist', function () {
            file_put_contents(base_path('test-git-head'), 'ref: refs/heads/main');

            Config::set('app.version_info.branch_file', 'test-git-head');
            Config::set('app.version_info.commit_file', 'non-existent-commit-file');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (main#unknown)');
        });

        it('auto-detects commit file from branch when no commit file is configured', function () {
            // Generate a random string to simulate a branch
            $randomBranch = 'autodetected-branch-'.bin2hex(random_bytes(5));
            file_put_contents(base_path('test-git-head'), 'ref: refs/heads/'.$randomBranch);

            // Create the expected auto-detected path
            $autoPath = base_path('.git/refs/heads/'.$randomBranch);
            if (! is_dir(dirname($autoPath))) {
                mkdir(dirname($autoPath), 0755, true);
            }
            file_put_contents($autoPath, 'autodetectedcommit123456');

            Config::set('app.version_info.branch_file', 'test-git-head');
            // Don't set commit_file, should auto-detect

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 ('.$randomBranch.'#autodet)');

            unlink($autoPath);
        });

        it('handles empty commit file', function () {
            file_put_contents(base_path('test-git-head'), 'ref: refs/heads/main');
            file_put_contents(base_path('test-invalid-commit'), '');

            Config::set('app.version_info.branch_file', 'test-git-head');
            Config::set('app.version_info.commit_file', 'test-invalid-commit');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (main#unknown)');
        });

        it('truncates commit hash to 7 characters', function () {
            file_put_contents(base_path('test-git-head'), 'ref: refs/heads/main');
            file_put_contents(base_path('test-git-commit'), 'verylongcommithash1234567890abcdef');

            Config::set('app.version_info.branch_file', 'test-git-head');
            Config::set('app.version_info.commit_file', 'test-git-commit');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (main#verylon)');
        });

        it('handles short commit hash', function () {
            file_put_contents(base_path('test-git-head'), 'ref: refs/heads/main');
            file_put_contents(base_path('test-git-commit'), 'abc123');

            Config::set('app.version_info.branch_file', 'test-git-head');
            Config::set('app.version_info.commit_file', 'test-git-commit');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (main#abc123)');
        });

    });

    describe('edge cases and invalid input', function () {

        it('handles null config values', function () {
            Config::set('app.version_info.branch_file', null);
            Config::set('app.version_info.commit_file', null);

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->not->toBeEmpty();
        });

        it('handles empty string config values', function () {
            Config::set('app.version_info.branch_file', '');
            Config::set('app.version_info.commit_file', '');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->not->toBeEmpty();
        });

        it('handles malformed git reference', function () {
            file_put_contents(base_path('test-git-head'), 'ref: invalid/reference/format');

            Config::set('app.version_info.branch_file', 'test-git-head');

            $provider = new AppVersionProvider;

            expect($provider->getVersion())->toBe('1.0.0 (invalid/reference/format#unknown)');
        });

    });

});
