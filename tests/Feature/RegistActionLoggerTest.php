<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RegistActionLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistActionLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_created_guarda_action_type_i_con_el_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        (new RegistActionLogger())->created('doctors', 42);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'doctors',
            'table_id'     => 42,
            'user_id'      => $user->id,
        ]);
    }

    public function test_updated_guarda_action_type_u(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        (new RegistActionLogger())->updated('specialties', 7);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'U',
            'target_table' => 'specialties',
            'table_id'     => 7,
            'user_id'      => $user->id,
        ]);
    }

    public function test_status_changed_guarda_action_type_e(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        (new RegistActionLogger())->statusChanged('affiliates', 3);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'E',
            'target_table' => 'affiliates',
            'table_id'     => 3,
            'user_id'      => $user->id,
        ]);
    }

    public function test_sin_usuario_autenticado_guarda_user_id_null(): void
    {
        (new RegistActionLogger())->created('doctors', 1);

        $this->assertDatabaseHas('regist_actions', [
            'action_type'  => 'I',
            'target_table' => 'doctors',
            'table_id'     => 1,
            'user_id'      => null,
        ]);
    }
}
