<?php

use App\Jobs\DetectStaleAssignments;
use BladeUI\Icons\Console\CacheCommand;
use BladeUI\Icons\Console\ClearCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Stringable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
            On Altervista, HTTPS is proxied and served over port 80, which
            is recognised by Laravel as insecure. This middleware tells
            Laravel to trust the proxy and allow URLs to be generated with
            the HTTPS scheme.

            See [GH36](https://github.com/LukeSavefrogs/registro-territori/issues/36)
            for more information.
        */
        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Schedule tasks here
        $schedule->job(new DetectStaleAssignments)
            ->weekly()
            ->days([Schedule::MONDAY])
            ->withoutOverlapping()
            ->onOneServer();
        // ->onFailure(function (Stringable $output) {
        //     // Handle failure
        // })
        // ->onSuccess(function (Stringable $output) {
        //     // Handle success
        // });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withCommands([
        CacheCommand::class,
        ClearCommand::class,
    ])->create();
