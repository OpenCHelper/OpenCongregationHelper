<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
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
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withCommands([
        \BladeUI\Icons\Console\CacheCommand::class,
        \BladeUI\Icons\Console\ClearCommand::class,
    ])->create();
