<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function cityId(): int
    {
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'TestDept', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return City::create(['name' => 'TestCity', 'department_id' => $deptId])->id;
    }

    private function adminUser(): User
    {
        return User::factory()->create(['type' => 1, 'state' => 1]);
    }

    // ── Endpoint público ──

    public function test_store_guarda_mensaje_de_contacto(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/contact', [
            'name'    => 'Juan García',
            'movil'   => '3001234567',
            'email'   => 'juan@example.com',
            'asunto'  => 'Información sobre planes',
            'city_id' => $cityId,
            'mensaje' => 'Hola, quiero información sobre los planes disponibles.',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.name', 'Juan García')
                 ->assertJsonPath('data.subject', 'Información sobre planes');

        $this->assertDatabaseHas('contacts', [
            'name'    => 'Juan García',
            'email'   => 'juan@example.com',
            'phone'   => '3001234567',
            'subject' => 'Información sobre planes',
            'comment' => 'Hola, quiero información sobre los planes disponibles.',
        ]);
    }

    public function test_store_falla_sin_campos_requeridos(): void
    {
        $response = $this->postJson('/api/public/contact', []);

        $response->assertStatus(422)
                 ->assertJsonStructure(['message', 'errors']);
    }

    public function test_store_falla_con_movil_invalido(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/contact', [
            'name'    => 'Juan García',
            'movil'   => '123',
            'email'   => 'juan@example.com',
            'asunto'  => 'Otro',
            'city_id' => $cityId,
            'mensaje' => 'Mensaje de prueba largo suficiente.',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('errors.movil.0', fn($v) => str_contains($v, '10'));
    }

    public function test_store_falla_con_mensaje_corto(): void
    {
        $cityId = $this->cityId();

        $response = $this->postJson('/api/public/contact', [
            'name'    => 'Juan García',
            'movil'   => '3001234567',
            'email'   => 'juan@example.com',
            'asunto'  => 'Otro',
            'city_id' => $cityId,
            'mensaje' => 'Corto',
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure(['errors' => ['mensaje']]);
    }

    // ── Endpoints admin ──

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson('/api/contacts')->assertStatus(401);
    }

    public function test_index_retorna_lista_paginada(): void
    {
        $cityId = $this->cityId();
        Contact::factory()->count(3)->create(['city_id' => $cityId]);

        $response = $this->actingAs($this->adminUser())
                         ->getJson('/api/contacts');

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
                 ->assertJsonCount(3, 'data');
    }

    public function test_index_busca_por_nombre(): void
    {
        $cityId = $this->cityId();
        Contact::factory()->create(['name' => 'Ana Martínez', 'city_id' => $cityId]);
        Contact::factory()->create(['name' => 'Pedro López',  'city_id' => $cityId]);

        $response = $this->actingAs($this->adminUser())
                         ->getJson('/api/contacts?search=Ana');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Ana Martínez');
    }

    public function test_show_retorna_contacto_con_ciudad(): void
    {
        $cityId  = $this->cityId();
        $contact = Contact::factory()->create(['city_id' => $cityId]);

        $response = $this->actingAs($this->adminUser())
                         ->getJson("/api/contacts/{$contact->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $contact->id)
                 ->assertJsonStructure(['data' => ['city']]);
    }

    public function test_show_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->adminUser())
             ->getJson('/api/contacts/99999')
             ->assertStatus(404);
    }

    public function test_destroy_elimina_fisicamente(): void
    {
        $cityId  = $this->cityId();
        $contact = Contact::factory()->create(['city_id' => $cityId]);

        $this->actingAs($this->adminUser())
             ->deleteJson("/api/contacts/{$contact->id}")
             ->assertStatus(200);

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_destroy_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->adminUser())
             ->deleteJson('/api/contacts/99999')
             ->assertStatus(404);
    }
}
