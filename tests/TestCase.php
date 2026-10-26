<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuses to go on unless the suite is on the in-memory SQLite database.
     *
     * RefreshDatabase drops every table of whatever connection is the default,
     * and inside the Docker app container that used to be the dev MySQL
     * database (see the note in phpunit.xml). Checked here, after the app has
     * booted and before RefreshDatabase runs, so a wrong setting stops the run
     * instead of wiping a real database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database   = $app['config']->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Tests must run on in-memory SQLite, not [{$connection}] database [{$database}]. Refusing to touch it."
            );
        }

        return $app;
    }
}
