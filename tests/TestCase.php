<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety net: RefreshDatabase wipes the database before each test. If the
     * config is cached (php artisan optimize), phpunit.xml's in-memory
     * database is ignored and the real database would be wiped instead.
     * Runs before any test trait (including RefreshDatabase) is set up.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests against '{$database}': tests must use the in-memory database. "
                . 'Run `php artisan config:clear` and try again.'
            );
        }

        return parent::setUpTraits();
    }
}
