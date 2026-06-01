<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    private function makeUser(string $password = 'secret123'): User
    {
        return User::factory()->create([
            'password' => Hash::make($password),
        ]);
    }

    public function test_cambia_password_con_credenciales_correctas(): void
    {
        $user = $this->makeUser('secret123');

        $response = $this->actingAs($user)->postJson('/api/user/change-password', [
            'current_password' => 'secret123',
            'new_password'     => 'nueva456',
        ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Contraseña actualizada correctamente']);
        $this->assertTrue(Hash::check('nueva456', $user->fresh()->password));
    }

    public function test_retorna_422_con_password_actual_incorrecta(): void
    {
        $user = $this->makeUser('secret123');

        $response = $this->actingAs($user)->postJson('/api/user/change-password', [
            'current_password' => 'equivocada',
            'new_password'     => 'nueva456',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_retorna_422_con_new_password_menor_a_6_caracteres(): void
    {
        $user = $this->makeUser('secret123');

        $response = $this->actingAs($user)->postJson('/api/user/change-password', [
            'current_password' => 'secret123',
            'new_password'     => '123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['new_password']);
    }

    public function test_retorna_401_sin_autenticacion(): void
    {
        $response = $this->postJson('/api/user/change-password', [
            'current_password' => 'secret123',
            'new_password'     => 'nueva456',
        ]);

        $response->assertStatus(401);
    }
}
