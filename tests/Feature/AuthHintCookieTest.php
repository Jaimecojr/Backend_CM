<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthHintCookieTest extends TestCase
{
    private function makeUser(string $password = 'secret123'): User
    {
        return User::factory()->create([
            'password' => Hash::make($password),
        ]);
    }

    public function test_login_exitoso_pone_la_cookie_auth_hint(): void
    {
        $user = $this->makeUser('secret123');

        $response = $this->postJson('/login', [
            'user' => $user->user,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        $response->assertCookie('auth_hint', '1');
    }

    public function test_login_fallido_no_pone_la_cookie_auth_hint(): void
    {
        $user = $this->makeUser('secret123');

        $response = $this->postJson('/login', [
            'user' => $user->user,
            'password' => 'incorrecta',
        ]);

        $response->assertStatus(422);
        $response->assertCookieMissing('auth_hint');
    }

    public function test_logout_borra_la_cookie_auth_hint(): void
    {
        $user = $this->makeUser('secret123');

        $response = $this->actingAs($user)->postJson('/logout');

        $response->assertStatus(200);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === 'auth_hint');

        $this->assertNotNull($cookie, 'La respuesta de /logout debe incluir la cookie auth_hint (para borrarla)');
        $this->assertTrue($cookie->getExpiresTime() < time());
    }
}
