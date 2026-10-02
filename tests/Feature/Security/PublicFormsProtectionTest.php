<?php

namespace Tests\Feature\Security;

use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicFormsProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_return_422_when_recaptcha_verification_fails(): void
    {
        // Arrange
        config()->set('services.recaptcha.secret', 'test-secret');
        Http::fake(['www.google.com/*' => Http::response(['success' => false], 200)]);

        // Act
        $response = $this->postJson('/api/public/contact', $this->contactPayload());

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recaptcha_token']);
        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_should_reject_low_score_when_recaptcha_v3_flags_a_bot(): void
    {
        // Arrange
        config()->set('services.recaptcha.secret', 'test-secret');
        Http::fake(['www.google.com/*' => Http::response(['success' => true, 'score' => 0.1], 200)]);

        // Act
        $response = $this->postJson('/api/public/contact', $this->contactPayload());

        // Assert
        $response->assertStatus(422);
    }

    public function test_should_save_contact_when_recaptcha_verification_succeeds(): void
    {
        // Arrange
        config()->set('services.recaptcha.secret', 'test-secret');
        Http::fake(['www.google.com/*' => Http::response(['success' => true, 'score' => 0.9], 200)]);

        // Act
        $response = $this->postJson('/api/public/contact', $this->contactPayload());

        // Assert
        $response->assertStatus(201);
        Http::assertSent(fn ($request) => $request['secret'] === 'test-secret' && $request['response'] === 'token-ok');
    }

    public function test_should_return_429_when_contact_form_is_flooded(): void
    {
        // Arrange
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/public/contact', []);
        }

        // Act
        $response = $this->postJson('/api/public/contact', []);

        // Assert
        $response->assertStatus(429);
    }

    public function test_should_send_security_headers_when_api_responds(): void
    {
        // Act
        $response = $this->getJson('/api/public/specialties');

        // Assert
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    /**
     * @return array<string, mixed>
     */
    private function contactPayload(): array
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Dept', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [
            'name'            => 'Visitante',
            'movil'           => '3001234567',
            'email'           => 'visitante@example.com',
            'asunto'          => 'Consulta',
            'city_id'         => City::create(['name' => 'Ciudad', 'department_id' => $departmentId])->id,
            'mensaje'         => 'Quisiera información sobre los planes.',
            'recaptcha_token' => 'token-ok',
        ];
    }
}
