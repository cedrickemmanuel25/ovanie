<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestingDatabaseIsolationTest extends TestCase
{
    public function test_phpunit_never_uses_the_local_mysql_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertStringEndsWith('config-testing.php', app()->getCachedConfigPath());
        $this->assertFileDoesNotExist(app()->getCachedConfigPath());
    }
}
