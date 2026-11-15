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

        return parent::createApplication();
    }
}
