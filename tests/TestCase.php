<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Fortify;
use Laravel\Jetstream\Jetstream;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create the application, as a new process would.
     *
     * Fortify and Jetstream keep in static properties whether they register their routes, so a value set while
     * booting the application of a test would leak to the applications of the following tests. Restoring the
     * defaults makes every test boot like a request does.
     */
    public function createApplication(): Application
    {
        Fortify::$registersRoutes = true;
        Jetstream::$registersRoutes = true;

        $app = parent::createApplication();

        // Each process of the parallel tests has its own marker of the installation, which `och:install` writes: a shared
        // one would make the setup wizard of the other processes disappear (and the one of the checkout too)
        $app['config']->set('app.setup.marker', sys_get_temp_dir().'/och-installed-'.getmypid());

        return $app;
    }
}
