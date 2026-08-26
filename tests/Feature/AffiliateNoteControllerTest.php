<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_crea_nota_para_el_afiliado(): void
    {
        $user = User::factory()->create();
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/affiliates/{$affiliate->id}/notes", [
            'body' => 'Nota de seguimiento de prueba.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('affiliate_notes', [
            'affiliate_id' => $affiliate->id,
            'user_id' => $user->id,
            'body' => 'Nota de seguimiento de prueba.',
        ]);
    }

    public function test_store_rechaza_body_vacio(): void
    {
        $user = User::factory()->create();
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/affiliates/{$affiliate->id}/notes", [
            'body' => '',
        ]);

        $response->assertStatus(422); // $request->validate() usa el default de Laravel, no Validator::make()
        $response->assertJsonValidationErrors(['body']);
    }

    public function test_index_lista_notas_del_afiliado(): void
    {
        $user = User::factory()->create();
        $affiliate = Affiliate::factory()->create();
        $this->actingAs($user)->postJson("/api/affiliates/{$affiliate->id}/notes", ['body' => 'Primera nota']);

        $response = $this->actingAs($user)->getJson("/api/affiliates/{$affiliate->id}/notes");

        $response->assertStatus(200);
    }

    public function test_destroy_requiere_super_admin(): void
    {
        $user = User::factory()->create(['type' => 3]);
        $affiliate = Affiliate::factory()->create();
        $created = $this->actingAs($user)->postJson("/api/affiliates/{$affiliate->id}/notes", ['body' => 'Nota']);
        $noteId = $created->json('data.id');

        $response = $this->actingAs($user)->deleteJson("/api/affiliates/{$affiliate->id}/notes/{$noteId}");

        $response->assertStatus(403);
    }

    public function test_destroy_retorna_404_si_la_nota_no_pertenece_al_afiliado(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliateA = Affiliate::factory()->create();
        $affiliateB = Affiliate::factory()->create();
        $created = $this->actingAs($admin)->postJson("/api/affiliates/{$affiliateA->id}/notes", ['body' => 'Nota']);
        $noteId = $created->json('data.id');

        $response = $this->actingAs($admin)->deleteJson("/api/affiliates/{$affiliateB->id}/notes/{$noteId}");

        $response->assertStatus(404);
    }
}
