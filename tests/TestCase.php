<?php

namespace Tests;

use App\Services\Vies;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Vies::$pause = 0; // no waiting between VIES retries in tests
    }
}
