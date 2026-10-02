<?php

namespace Tests\Feature\Security;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuperAdminRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_return_403_when_franchise_creates_website_content(): void
    {
        // Arrange
        Storage::fake('public');
        $franchise = User::factory()->create(['type' => 2]);

        // Act
        $response = $this->actingAs($franchise)->postJson('/api/content-allies', [
            'image'    => UploadedFile::fake()->image('ally.png'),
            'url'      => 'https://phishing.example.com',
            'position' => 1,
        ]);

        // Assert
        $response->assertStatus(403);
        $this->assertDatabaseCount('content_allies', 0);
    }

    public function test_should_return_403_when_franchise_deletes_an_agreement(): void
    {
        // Arrange
        $franchise = User::factory()->create(['type' => 2]);
        $agreement = Agreement::factory()->create();

        // Act
        $response = $this->actingAs($franchise)->deleteJson("/api/agreements/{$agreement->id}");

        // Assert
        $response->assertStatus(403);
        $this->assertDatabaseHas('agreements', ['id' => $agreement->id]);
    }

    public function test_should_store_image_with_content_based_extension_when_client_name_is_html(): void
    {
        // Arrange
        Storage::fake('public');
        $admin = User::factory()->create(['type' => 1]);
        $png = UploadedFile::fake()->image('payload.png');
        $disguised = new UploadedFile($png->getPathname(), 'payload.html', 'text/html', null, true);

        // Act
        $response = $this->actingAs($admin)->postJson('/api/content-allies', [
            'image'    => $disguised,
            'url'      => 'https://aliado.example.com',
            'position' => 1,
        ]);

        // Assert
        $response->assertStatus(201);
        $this->assertStringEndsWith('.png', $response->json('data.image'));
    }

    public function test_should_reject_ally_url_when_scheme_is_javascript(): void
    {
        // Arrange
        Storage::fake('public');
        $admin = User::factory()->create(['type' => 1]);

        // Act
        $response = $this->actingAs($admin)->postJson('/api/content-allies', [
            'image'    => UploadedFile::fake()->image('ally.png'),
            'url'      => 'javascript:alert(1)',
            'position' => 1,
        ]);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
    }
}
