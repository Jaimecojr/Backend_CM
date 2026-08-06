<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Beneficiary;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliatePublicStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_encuentra_afiliado_activo_con_beneficiarios(): void
    {
        $affiliate = Affiliate::factory()->create([
            'name'         => 'Jaime',
            'lastname'     => 'Castaño',
            'id_card'      => '1094947820',
            'stade'        => 1,
            'validity_end' => Carbon::today()->addMonths(6)->toDateString(),
        ]);

        Beneficiary::create(['affiliate_id' => $affiliate->id, 'name' => 'Manuela Ocampo', 'id_card' => '1000000001']);
        Beneficiary::create(['affiliate_id' => $affiliate->id, 'name' => 'Juan Carlos Ocampo', 'id_card' => '1000000002']);

        $response = $this->postJson('/api/public/affiliate-status', [
            'document_number' => '1094947820',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.name', 'Jaime')
                 ->assertJsonPath('data.lastname', 'Castaño')
                 ->assertJsonPath('data.stade', 1)
                 ->assertJsonCount(2, 'data.beneficiaries');

        $this->assertArrayNotHasKey('movil', $response->json('data'));
        $this->assertArrayNotHasKey('phone', $response->json('data'));
    }

    public function test_encuentra_afiliado_inactivo_y_no_lo_bloquea(): void
    {
        Affiliate::factory()->create([
            'id_card'      => '2094947820',
            'stade'        => 2,
            'validity_end' => Carbon::today()->subMonths(2)->toDateString(),
        ]);

        $response = $this->postJson('/api/public/affiliate-status', [
            'document_number' => '2094947820',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('success', true)
                 ->assertJsonPath('data.stade', 2);
    }

    public function test_retorna_404_si_no_existe(): void
    {
        $response = $this->postJson('/api/public/affiliate-status', [
            'document_number' => '9999999999',
        ]);

        $response->assertStatus(404)
                 ->assertJsonPath('success', false);
    }

    public function test_retorna_422_sin_document_number(): void
    {
        $response = $this->postJson('/api/public/affiliate-status', []);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }

    public function test_retorna_422_con_document_number_no_numerico(): void
    {
        $response = $this->postJson('/api/public/affiliate-status', [
            'document_number' => '109.494-7820',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('success', false);
    }
}
