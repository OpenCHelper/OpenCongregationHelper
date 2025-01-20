<?php

namespace App\Providers;

use Awcodes\FilamentVersions\Providers\Contracts\VersionProvider;

class AppVersionProvider implements VersionProvider
{
    public function getName(): string
    {
        return 'App';
    }
 
    public function getVersion(): string
    {
        return config('app.version');
    }
}
