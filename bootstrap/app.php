<?php

use App\Jobs\DetectStaleAssignments;
use BladeUI\Icons\Console\CacheCommand;
use BladeUI\Icons\Console\ClearCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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

            The address of the proxy is not known, so every peer is trusted
            and any client can send these headers: only the ones the
            application needs are honoured.
            - `X-Forwarded-Proto` keeps the HTTPS scheme (GH36); the host
              and port still come from the `Host` header, so no `:80` is
              added to the URLs.
            - `X-Forwarded-For` makes `$request->ip()` the visitor's address
              instead of the proxy's one, which would otherwise put every
              user in the same login throttling bucket. The right-most
              address is used, so an address prepended by the client is
              ignored as long as the proxy appends the one it received the
              request from.
            - `X-Forwarded-Host`, `-Port` and `-Prefix` are NOT trusted:
              they would let a client choose the host of every generated
              URL, including the password reset links sent by email.

            See [GH36](https://github.com/LukeSavefrogs/registro-territori/issues/36)
            for more information.
        */
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );
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
