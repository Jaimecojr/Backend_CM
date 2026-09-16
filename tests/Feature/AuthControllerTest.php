<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cubre AuthController::login()/logout() — el único punto de entrada de
 * autenticación del panel, con el provider dual `md5-eloquent`
 * (Md5UserProvider) que acepta tanto passwords MD5 heredadas del sistema
 * anterior como bcrypt ya migradas. La cookie auth_hint tiene su propia
 * suite (AuthHintCookieTest); este archivo no repite esos casos.
 */
class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_exitoso_con_password_legado_md5(): void
    {
        $user = User::factory()->create();
        // Se escribe con el query builder para evitar que el cast 'hashed'
        // del modelo re-hashee este valor con bcrypt al guardarlo.
        DB::table('users')->where('id', $user->id)->update([
            'password' => md5('claveVieja123'),
        ]);

        $response = $this->postJson('/login', [
            'user' => $user->user,
            'password' => 'claveVieja123',
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_exitoso_con_password_ya_migrada_a_bcrypt(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('claveNueva123'),
        ]);

        $response = $this->postJson('/login', [
            'user' => $user->user,
            'password' => 'claveNueva123',
        ]);

        $response->assertStatus(200);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_falla_con_password_incorrecta_en_formato_md5(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update([
            'password' => md5('claveCorrecta'),
        ]);

        $response = $this->postJson('/login', [
            'user' => $user->user,
            'password' => 'claveIncorrecta',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Credenciales inválidas']);
        $this->assertGuest();
    }

    public function test_login_falla_con_usuario_inexistente(): void
    {
        $response = $this->postJson('/login', [
            'user' => 'no-existe',
            'password' => 'lo-que-sea',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Credenciales inválidas']);
        $this->assertGuest();
    }

    public function test_login_requiere_user_y_password(): void
    {
        $response = $this->postJson('/login', []);

        // AuthController::login() usa $request->validate() (default de
        // Laravel), no Validator::make() manual — por eso responde 422 y no
        // el 400 que usan Affiliate/User/Doctor/Counselor/Beneficiary.
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['user', 'password']);
    }

    public function test_logout_invalida_la_sesion(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/logout');

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'Logout OK']);
        $this->assertGuest();
    }
}
