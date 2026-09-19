<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may ever reach PalmPesa or a real router. Anything that is not
        // explicitly faked with Http::fake() fails loudly instead of going out.
        Http::preventStrayRequests();
    }
}
