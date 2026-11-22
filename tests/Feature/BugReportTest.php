<?php

use App\Support\BugReport;
use Filament\Facades\Filament;
use Filament\Livewire\Topbar;
use Illuminate\Http\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Fields of the bug report issue form, by id.
 *
 * @return array<string, array<string, mixed>>
 */
function bugReportFormFields(): array
{
    $form = Yaml::parseFile(base_path('.github/ISSUE_TEMPLATE/'.BugReport::TEMPLATE));

    return collect($form['body'])->filter(fn (array $field): bool => isset($field['id']))->keyBy('id')->all();
}

/**
 * Request to the installation at the given address, from the given browser.
 */
function bugReportRequest(string $url = 'https://congregation.example.org/app', string $userAgent = 'Browser/1.0'): Request
{
    return Request::create($url, server: ['HTTP_USER_AGENT' => $userAgent]);
}

test('the bug report link opens the issue form prefilled with the details of the installation', function () {
    app()->detectEnvironment(fn (): string => 'staging');
    config(['app.version' => 'v9.8.7', 'app.version_info.branch_file' => 'non-existent-file']);

    $url = BugReport::url(bugReportRequest('https://congregation.example.org:8443/app/territories', 'Mozilla/5.0 (Test)'));
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith(config('app.repository_url').'/issues/new?')
        ->and($query)->toBe([
            'template' => BugReport::TEMPLATE,
            'instance' => 'https://congregation.example.org:8443',
            'app_environment' => 'staging',
            'version' => '9.8.7',
            'browser' => 'Mozilla/5.0 (Test)',
        ]);
});

test('the prefilled fields exist in the issue form', function () {
    parse_str((string) parse_url(BugReport::url(bugReportRequest()), PHP_URL_QUERY), $query);
    unset($query['template']);

    expect(bugReportFormFields())->toHaveKeys(array_keys($query));
});

test('the browser is left empty when unknown', function () {
    parse_str((string) parse_url(BugReport::url(bugReportRequest(userAgent: '')), PHP_URL_QUERY), $query);

    expect($query)->not->toHaveKey('browser');
});

test('the version includes the build outside production', function () {
    file_put_contents(base_path('test-git-head'), 'ref: refs/heads/main');
    file_put_contents(base_path('test-git-commit'), 'abcdef1234567890abcdef1234567890abcdef12');
    config([
        'app.version' => 'v1.0.0',
        'app.version_info.branch_file' => 'test-git-head',
        'app.version_info.commit_file' => 'test-git-commit',
    ]);

    try {
        $version = BugReport::version();
        app()->detectEnvironment(fn (): string => 'production');
        $productionVersion = BugReport::version();
    } finally {
        unlink(base_path('test-git-head'));
        unlink(base_path('test-git-commit'));
    }

    expect($version)->toBe('1.0.0 (main#abcdef1)')
        ->and($productionVersion)->toBe('1.0.0');
});

test('the bug report is opened in a new tab from the About modal', function () {
    $this->app->instance('request', bugReportRequest());

    $versionInfo = Filament::getPanel('app')->getUserMenuItems()['versionInfo']->livewire(app(Topbar::class));
    $actions = $versionInfo->getExtraModalFooterActions();

    expect($actions)->toHaveKey('reportBug')
        ->and($actions['reportBug']->getLabel())->toBe(__('filament-resources.bug_report.button_label'))
        ->and($actions['reportBug']->getUrl())->toBe(BugReport::url(bugReportRequest()))
        ->and($actions['reportBug']->shouldOpenUrlInNewTab())->toBeTrue();
});
