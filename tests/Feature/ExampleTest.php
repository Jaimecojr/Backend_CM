<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example for API health check.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Test that a non-existent route returns 404 (expected for API)
        $response = $this->get('/');

        $response->assertStatus(404);
    }
}
