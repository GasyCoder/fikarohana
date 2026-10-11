<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['config']['database.default'];
        $database = $app['config']["database.connections.{$connection}.database"];

        // Check before RefreshDatabase can migrate or truncate anything.
        if ($connection !== 'pgsql' || ! is_string($database) || ! str_ends_with($database, '_test')
            || $app['config']["database.connections.{$connection}.url"]) {
            throw new RuntimeException('Integration tests require a dedicated PostgreSQL *_test database without DB_URL.');
        }

        return $app;
    }
}
