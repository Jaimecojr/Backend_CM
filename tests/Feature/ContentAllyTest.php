<?php

namespace Tests\Feature;

use App\Models\ContentAlly;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentAllyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['type' => 1, 'state' => 1]);
    }

    private function fakeImage(): UploadedFile
    {
        Storage::fake('public');
        return UploadedFile::fake()->image('banner.jpg');
    }

    // ── Endpoint público ──

    public function test_public_index_retorna_aliados_ordenados(): void
    {
        Storage::fake('public');
        ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://a.com', 'position' => 2]);
        ContentAlly::create(['image' => 'content_allies/b.jpg', 'image_filename' => 'b.jpg', 'url' => 'https://b.com', 'position' => 1]);

        $response = $this->getJson('/api/public/content-allies');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('data.0.url', 'https://b.com');
    }

    public function test_public_index_no_expone_image_filename(): void
    {
        Storage::fake('public');
        ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://a.com', 'position' => 1]);

        $response = $this->getJson('/api/public/content-allies');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('image_filename', $response->json('data.0'));
    }

    // ── Endpoints admin ──

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson('/api/content-allies')->assertStatus(401);
    }

    public function test_index_retorna_todos_los_aliados(): void
    {
        Storage::fake('public');
        ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://a.com', 'position' => 1]);
        ContentAlly::create(['image' => 'content_allies/b.jpg', 'image_filename' => 'b.jpg', 'url' => 'https://b.com', 'position' => 2]);

        $response = $this->actingAs($this->admin())->getJson('/api/content-allies');

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_store_crea_aliado_con_imagen(): void
    {
        $image = $this->fakeImage();

        $response = $this->actingAs($this->admin())->postJson('/api/content-allies', [
            'image'    => $image,
            'url'      => 'https://empresa.com',
            'position' => 1,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.url', 'https://empresa.com')
                 ->assertJsonPath('data.position', 1);

        $this->assertDatabaseHas('content_allies', ['url' => 'https://empresa.com']);
    }

    public function test_store_rechaza_cuando_hay_6_aliados(): void
    {
        Storage::fake('public');
        for ($i = 1; $i <= 6; $i++) {
            ContentAlly::create(['image' => "content_allies/{$i}.jpg", 'image_filename' => "{$i}.jpg", 'url' => "https://a{$i}.com", 'position' => $i]);
        }

        $response = $this->actingAs($this->admin())->postJson('/api/content-allies', [
            'image'    => $this->fakeImage(),
            'url'      => 'https://extra.com',
            'position' => 7,
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('message', fn ($v) => str_contains($v, '6'));
    }

    public function test_store_requiere_url_valida(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/api/content-allies', [
            'image'    => $this->fakeImage(),
            'url'      => 'no-es-url',
            'position' => 1,
        ]);

        $response->assertStatus(422)->assertJsonStructure(['errors' => ['url']]);
    }

    public function test_update_actualiza_url_y_posicion(): void
    {
        Storage::fake('public');
        $ally = ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://old.com', 'position' => 1]);

        $response = $this->actingAs($this->admin())->putJson("/api/content-allies/{$ally->id}", [
            'url'      => 'https://new.com',
            'position' => 2,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.url', 'https://new.com');
        $this->assertDatabaseHas('content_allies', ['id' => $ally->id, 'url' => 'https://new.com', 'position' => 2]);
    }

    public function test_update_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->admin())
             ->putJson('/api/content-allies/99999', ['url' => 'https://x.com', 'position' => 1])
             ->assertStatus(404);
    }

    public function test_destroy_elimina_aliado(): void
    {
        Storage::fake('public');
        $ally = ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://a.com', 'position' => 1]);

        $this->actingAs($this->admin())
             ->deleteJson("/api/content-allies/{$ally->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('content_allies', ['id' => $ally->id]);
    }

    public function test_destroy_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->admin())
             ->deleteJson('/api/content-allies/99999')
             ->assertStatus(404);
    }

    public function test_reorder_actualiza_posiciones(): void
    {
        Storage::fake('public');
        $a = ContentAlly::create(['image' => 'content_allies/a.jpg', 'image_filename' => 'a.jpg', 'url' => 'https://a.com', 'position' => 1]);
        $b = ContentAlly::create(['image' => 'content_allies/b.jpg', 'image_filename' => 'b.jpg', 'url' => 'https://b.com', 'position' => 2]);

        $response = $this->actingAs($this->admin())->putJson('/api/content-allies/reorder', [
            'items' => [
                ['id' => $a->id, 'position' => 2],
                ['id' => $b->id, 'position' => 1],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('content_allies', ['id' => $a->id, 'position' => 2]);
        $this->assertDatabaseHas('content_allies', ['id' => $b->id, 'position' => 1]);
    }
}
