<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiarySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_agrega_un_beneficiario_nuevo(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [['name' => 'Hijo Nuevo', 'id_card' => '111']],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('beneficiaries', ['affiliate_id' => $affiliate->id, 'name' => 'Hijo Nuevo']);
    }

    public function test_update_edita_un_beneficiario_existente(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $created = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [['name' => 'Nombre Original', 'id_card' => '222']],
        ]);
        $beneficiaryId = \App\Models\Beneficiary::where('affiliate_id', $affiliate->id)->first()->id;

        $response = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [['id' => $beneficiaryId, 'name' => 'Nombre Editado', 'id_card' => '222']],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('beneficiaries', ['id' => $beneficiaryId, 'name' => 'Nombre Editado']);
    }

    public function test_update_elimina_beneficiarios_no_incluidos_en_el_payload(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [['name' => 'A Eliminar', 'id_card' => '333']],
        ]);
        $beneficiaryId = \App\Models\Beneficiary::where('affiliate_id', $affiliate->id)->first()->id;

        $response = $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('beneficiaries', ['id' => $beneficiaryId]);
    }
}
