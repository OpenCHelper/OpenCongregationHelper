<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Addresses (or CIDR ranges, comma-separated) of the reverse proxies in
    | front of the application, whose `X-Forwarded-For` and
    | `X-Forwarded-Proto` headers are trusted (see `bootstrap/app.php`).
    |
    | `*` trusts whichever peer sends the request: needed when the address of
    | the proxy is not known (e.g. Altervista), but then any client can choose
    | the IP address the application sees. Leave it empty when the
    | application is reached directly, without a proxy.
    |
    */

    'proxies' => filled($proxies = env('TRUSTED_PROXIES', '*')) ? $proxies : null,

];
