<?php

namespace Tests\Feature;

use App\Models\MembershipForm;
use App\Models\MembershipFormBeneficiary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipFormAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['type' => 1]);
    }

    public function test_index_requiere_autenticacion(): void
    {
        $this->getJson('/api/membership-forms')->assertStatus(401);
    }

    public function test_index_retorna_solo_pendientes(): void
    {
        $admin = $this->admin();
        MembershipForm::factory()->count(3)->create(['state' => 0]);
        MembershipForm::factory()->count(2)->create(['state' => 1]);

        $response = $this->actingAs($admin)->getJson('/api/membership-forms');

        $response->assertStatus(200)
                 ->assertJsonPath('meta.total', 3)
                 ->assertJsonStructure(['message', 'data', 'meta']);
    }

    public function test_index_busca_por_nombre(): void
    {
        $admin = $this->admin();
        MembershipForm::factory()->create(['name' => 'Carlos', 'lastname' => 'Torres', 'state' => 0]);
        MembershipForm::factory()->create(['name' => 'Ana',    'lastname' => 'López',  'state' => 0]);

        $response = $this->actingAs($admin)->getJson('/api/membership-forms?search=Carlos');

        $response->assertStatus(200)->assertJsonPath('meta.total', 1);
    }

    public function test_index_busca_por_cedula(): void
    {
        $admin = $this->admin();
        MembershipForm::factory()->create(['id_card' => '1122334455', 'state' => 0]);
        MembershipForm::factory()->create(['id_card' => '9988776655', 'state' => 0]);

        $response = $this->actingAs($admin)->getJson('/api/membership-forms?search=1122334455');

        $response->assertStatus(200)->assertJsonPath('meta.total', 1);
    }

    public function test_show_retorna_form_con_beneficiarios(): void
    {
        $admin = $this->admin();
        $form  = MembershipForm::factory()->create(['state' => 0]);
        MembershipFormBeneficiary::create(['membership_form_id' => $form->id, 'name' => 'Hijo 1']);

        $response = $this->actingAs($admin)->getJson("/api/membership-forms/{$form->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $form->id)
                 ->assertJsonCount(1, 'data.membership_form_beneficiaries');
    }

    public function test_show_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->admin())->getJson('/api/membership-forms/9999')->assertStatus(404);
    }

    public function test_destroy_elimina_el_registro(): void
    {
        $admin = $this->admin();
        $form  = MembershipForm::factory()->create(['state' => 0]);

        $this->actingAs($admin)->deleteJson("/api/membership-forms/{$form->id}")->assertStatus(200);
        $this->assertDatabaseMissing('membership_forms', ['id' => $form->id]);
    }

    public function test_destroy_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->admin())->deleteJson('/api/membership-forms/9999')->assertStatus(404);
    }

    public function test_mark_converted_cambia_state_a_1(): void
    {
        $admin = $this->admin();
        $form  = MembershipForm::factory()->create(['state' => 0]);

        $this->actingAs($admin)->patchJson("/api/membership-forms/{$form->id}/convert")->assertStatus(200);
        $this->assertDatabaseHas('membership_forms', ['id' => $form->id, 'state' => 1]);
    }

    public function test_mark_converted_retorna_404_si_no_existe(): void
    {
        $this->actingAs($this->admin())->patchJson('/api/membership-forms/9999/convert')->assertStatus(404);
    }
}
