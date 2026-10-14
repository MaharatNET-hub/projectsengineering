<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // the suite also runs on a real server database (DB_CONNECTION=mysql DB_DATABASE=… php artisan test):
        // each test then starts from an empty one, as it does with the in-memory SQLite default
        if (config('database.default') !== 'sqlite') {
            $this->artisan('db:wipe', ['--force' => true]);
        }
    }
}
