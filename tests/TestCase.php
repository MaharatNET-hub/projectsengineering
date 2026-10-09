<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // the file-upload intake is off on the live site but its engine stays covered by the tests
        config(['v2.file_submit' => true]);
    }
}
