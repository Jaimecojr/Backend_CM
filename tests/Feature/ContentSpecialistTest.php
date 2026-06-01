<?php

namespace Tests\Feature;

use App\Models\ContentSpecialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContentSpecialistTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['type' => 1, 'state' => 1]);
    }

    private function fakePhoto(): UploadedFile
    {
        Storage::fake('public');
        return UploadedFile::fake()->image('doctor.jpg');
    }

    // ── Endpoint público ──

    public function test_public_index_retorna_especialistas_ordenados(): void
    {
        Storage::fake('public');
        ContentSpecialist::create(['name' => 'Dr. B', 'specialty' => 'Cardiología', 'photo' => 'content_specialists/b.jpg', 'photo_filename' => 'b.jpg', 'position' => 2]);
        ContentSpecialist::create(['name' => 'Dr. A', 'specialty' => 'Pediatría', 'photo' => 'content_specialists/a.jpg', 'photo_filename' => 'a.jpg', 'position' => 1]);

        $response = $this->getJson('/api/public/content-specialists');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data')
                 ->assertJsonPath('data.0.name', 'Dr. A');
    }

    public function test_public_index_no_expone_photo_filename(): void
    {
        Storage::fake('public');
        ContentSpecialist::create(['name' => 'Dr. X', 'specialty' => 'Neurología', 'photo' => 'content_specialists/x.jpg', 'photo_filename' => 'x.jpg', 'position' => 1]);

        $response = $this->getJson('/api/public/content-specialists');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('photo_filename', $response->json('data.0'));
    }

    // ── Endpoints admin ──

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson('/api/content-specialists')->assertStatus(401);
    }

    public function test_store_crea_especialista_con_foto(): void
    {
        $photo = $this->fakePhoto();

        $response = $this->actingAs($this->admin())->post('/api/content-specialists', [
            'name'      => 'Dr. Juan Pérez',
            'specialty' => 'Cardiología',
            'photo'     => $photo,
            'position'  => 1,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Dr. Juan Pérez')
                 ->assertJsonPath('data.specialty', 'Cardiología');

        $this->assertDatabaseHas('content_specialists', ['name' => 'Dr. Juan Pérez', 'specialty' => 'Cardiología']);
    }

    public function test_store_rechaza_cuando_hay_4_especialistas(): void
    {
        Storage::fake('public');
        for ($i = 1; $i <= 4; $i++) {
            ContentSpecialist::create(['name' => "Dr. {$i}", 'specialty' => 'General', 'photo' => "content_specialists/{$i}.jpg", 'photo_filename' => "{$i}.jpg", 'position' => $i]);
        }

        $response = $this->actingAs($this->admin())->postJson('/api/content-specialists', [
            'name'     => 'Dr. Extra',
            'photo'    => $this->fakePhoto(),
            'position' => 5,
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('message', fn ($v) => str_contains($v, '4'));
    }

    public function test_update_actualiza_nombre_y_posicion(): void
    {
        Storage::fake('public');
        $s = ContentSpecialist::create(['name' => 'Dr. Viejo', 'specialty' => 'Pediatría', 'photo' => 'content_specialists/v.jpg', 'photo_filename' => 'v.jpg', 'position' => 1]);

        $response = $this->actingAs($this->admin())->putJson("/api/content-specialists/{$s->id}", [
            'name'      => 'Dr. Nuevo',
            'specialty' => 'Cardiología',
            'position'  => 2,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.name', 'Dr. Nuevo')
                 ->assertJsonPath('data.specialty', 'Cardiología');
    }

    public function test_destroy_elimina_especialista(): void
    {
        Storage::fake('public');
        $s = ContentSpecialist::create(['name' => 'Dr. X', 'specialty' => 'General', 'photo' => 'content_specialists/x.jpg', 'photo_filename' => 'x.jpg', 'position' => 1]);

        $this->actingAs($this->admin())
             ->deleteJson("/api/content-specialists/{$s->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('content_specialists', ['id' => $s->id]);
    }

    public function test_reorder_actualiza_posiciones(): void
    {
        Storage::fake('public');
        $a = ContentSpecialist::create(['name' => 'Dr. A', 'specialty' => 'Pediatría', 'photo' => 'content_specialists/a.jpg', 'photo_filename' => 'a.jpg', 'position' => 1]);
        $b = ContentSpecialist::create(['name' => 'Dr. B', 'specialty' => 'Cardiología', 'photo' => 'content_specialists/b.jpg', 'photo_filename' => 'b.jpg', 'position' => 2]);

        $this->actingAs($this->admin())->putJson('/api/content-specialists/reorder', [
            'items' => [
                ['id' => $a->id, 'position' => 2],
                ['id' => $b->id, 'position' => 1],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('content_specialists', ['id' => $a->id, 'position' => 2]);
    }
}
