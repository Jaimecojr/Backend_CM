<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_reject_login_when_user_is_inactive(): void
    {
        // Arrange
        $user = User::factory()->create(['state' => 2, 'password' => 'Correcta123']);

        // Act
        $response = $this->postJson('/login', ['user' => $user->user, 'password' => 'Correcta123']);

        // Assert
        $response->assertStatus(422);
        $this->assertGuest();
    }

    public function test_should_return_429_when_login_attempts_exceed_the_limit(): void
    {
        // Arrange
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['user' => $user->user, 'password' => 'mala']);
        }

        // Act
        $response = $this->postJson('/login', ['user' => $user->user, 'password' => 'mala']);

        // Assert
        $response->assertStatus(429);
    }

    public function test_should_rehash_legacy_md5_password_to_bcrypt_when_login_succeeds(): void
    {
        // Arrange
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['password' => md5('claveVieja123')]);

        // Act
        $this->postJson('/login', ['user' => $user->user, 'password' => 'claveVieja123']);

        // Assert
        $stored = DB::table('users')->where('id', $user->id)->value('password');
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertTrue(Hash::check('claveVieja123', $stored));
    }

    public function test_should_return_401_when_user_was_deactivated_after_logging_in(): void
    {
        // Arrange
        $user = User::factory()->create(['state' => 1]);
        $this->actingAs($user);
        $user->forceFill(['state' => 2])->save();

        // Act
        $response = $this->getJson('/api/user');

        // Assert
        $response->assertStatus(401);
    }
}
