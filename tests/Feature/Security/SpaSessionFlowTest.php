<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end SPA flow (real login, no actingAs): the session created by POST /login must still be
 * valid on the next /api request. A framework/Sanctum version mismatch in how the session password
 * hash is stored once made Sanctum's AuthenticateSession log every user out on their first API call.
 */
class SpaSessionFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SPA_HEADERS = [
        'Origin'  => 'http://localhost:3000',
        'Referer' => 'http://localhost:3000/',
    ];

    public function test_should_keep_the_session_when_the_spa_calls_the_api_after_logging_in(): void
    {
        // Arrange
        config()->set('sanctum.stateful', ['localhost:3000']);
        $user = User::factory()->create(['type' => 1, 'password' => 'Correcta123']);

        $this->withHeaders(self::SPA_HEADERS)
            ->postJson('/login', ['user' => $user->user, 'password' => 'Correcta123'])
            ->assertStatus(200);

        // Act
        $response = $this->withHeaders(self::SPA_HEADERS)->getJson('/api/affiliates');

        // Assert
        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }
}
