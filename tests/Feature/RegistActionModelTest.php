<?php

namespace Tests\Feature;

use App\Models\RegistAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistActionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_puede_crear_con_las_4_columnas_reales(): void
    {
        $user = User::factory()->create();

        $action = RegistAction::create([
            'action_type'  => 'I',
            'target_table' => 'doctors',
            'table_id'     => 1,
            'user_id'      => $user->id,
        ]);

        $this->assertDatabaseHas('regist_actions', [
            'id'           => $action->id,
            'action_type'  => 'I',
            'target_table' => 'doctors',
            'table_id'     => 1,
            'user_id'      => $user->id,
        ]);
    }

    public function test_borrar_el_usuario_deja_user_id_en_null_sin_borrar_el_registro(): void
    {
        $user = User::factory()->create();
        $action = RegistAction::create([
            'action_type'  => 'U',
            'target_table' => 'specialties',
            'table_id'     => 5,
            'user_id'      => $user->id,
        ]);

        $user->delete();

        $this->assertDatabaseHas('regist_actions', [
            'id'      => $action->id,
            'user_id' => null,
        ]);
    }
}
