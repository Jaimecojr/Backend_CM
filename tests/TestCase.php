<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Gives the test run a throwaway APP_KEY generated in memory, unless the environment (e.g. CI)
     * already provides one. No key is ever committed: even a test-only key in phpunit.xml gets
     * flagged by secret scanners, and a real one would let anyone forge sessions. The encrypter is
     * resolved lazily, so setting the config right after boot is early enough.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        if (blank($app['config']->get('app.key'))) {
            $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        }

        return $app;
    }
}
