<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\ClosureCommand;

const DEPLOYMENT_STEPS = [
    'migrate',
    'och:install',
    'storage:link',
    'optimize:clear',
    'filament:clear-cached-components',
    'filament:optimize-clear',
    'config:cache',
    'event:cache',
    'route:cache',
    'view:cache',
    'filament:cache-components',
    'filament:optimize',
    'schedule:interrupt',
];

/**
 * Replace the commands run by `och:post-deploy` with fakes that record their calls, so that the caches of the application
 * running the tests are left alone. The fake named `$failing` fails.
 *
 * @return ArrayObject<int, array{0: string, 1: array<string, mixed>}>
 */
function fakeDeploymentSteps(?string $failing = null): ArrayObject
{
    $calls = new ArrayObject;
    $kernel = app(Kernel::class);

    foreach (['migrate:fresh', ...DEPLOYMENT_STEPS] as $name) {
        $kernel->registerCommand(new ClosureCommand("{$name} {--force}", function () use ($name, $failing, $calls) {
            /** @var ClosureCommand $this */
            $calls[] = [$name, $this->input->getOptions()];

            return $name === $failing ? 1 : 0;
        }));
    }

    return $calls;
}

test('the deployment runs every step in order', function () {
    $calls = fakeDeploymentSteps();

    $this->artisan('och:post-deploy', ['--no-interaction' => true])
        ->expectsOutputToContain('Deployment completed.')
        ->assertSuccessful();

    expect(array_column($calls->getArrayCopy(), 0))->toBe(DEPLOYMENT_STEPS)
        ->and($calls[0][1]['force'])->toBeTrue()
        ->and($calls[1][1]['no-interaction'])->toBeTrue();
});

test('the deployment stops at the first failing step', function () {
    $calls = fakeDeploymentSteps(failing: 'och:install');

    $this->artisan('och:post-deploy', ['--no-interaction' => true])
        ->expectsOutputToContain('`och:install` failed: the deployment was stopped.')
        ->assertFailed();

    expect(array_column($calls->getArrayCopy(), 0))->toBe(['migrate', 'och:install']);
});

test('--fresh drops the tables first', function () {
    $calls = fakeDeploymentSteps();

    $this->artisan('och:post-deploy', ['--fresh' => true, '--no-interaction' => true])->assertSuccessful();

    expect($calls[0][0])->toBe('migrate:fresh');
});

test('--fresh is refused in production', function () {
    $calls = fakeDeploymentSteps();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('och:post-deploy', ['--fresh' => true, '--no-interaction' => true])
        ->expectsOutputToContain('refused in production')
        ->assertFailed();

    expect($calls)->toHaveCount(0);
});
