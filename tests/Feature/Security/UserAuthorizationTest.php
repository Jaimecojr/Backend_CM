<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Before these guards any franchise could promote itself to super admin or take over the admin
 * account through /api/users, because the panel only hid the screens.
 */
class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_ignore_type_when_franchise_updates_its_own_record(): void
    {
        // Arrange
        $franchise = User::factory()->create(['type' => 2]);

        // Act
        $response = $this->actingAs($franchise)->patchJson("/api/users/{$franchise->id}", ['type' => 1]);

        // Assert
        $response->assertStatus(200);
        $this->assertSame(2, $franchise->fresh()->type);
    }

    public function test_should_ignore_state_and_password_when_franchise_updates_its_own_record(): void
    {
        // Arrange
        $franchise = User::factory()->create(['type' => 2, 'state' => 1]);
        $originalHash = $franchise->password;

        // Act
        $this->actingAs($franchise)->patchJson("/api/users/{$franchise->id}", [
            'state'    => 2,
            'password' => 'NuevaClave123',
        ]);

        // Assert
        $fresh = $franchise->fresh();
        $this->assertSame(1, $fresh->state);
        $this->assertSame($originalHash, $fresh->password);
    }

    public function test_should_update_username_when_franchise_edits_its_own_account(): void
    {
        // Arrange
        $franchise = User::factory()->create(['type' => 2]);

        // Act
        $response = $this->actingAs($franchise)->patchJson("/api/users/{$franchise->id}", ['user' => 'nuevo_login']);

        // Assert
        $response->assertStatus(200);
        $this->assertSame('nuevo_login', $franchise->fresh()->user);
    }

    public function test_should_return_403_when_franchise_updates_another_user(): void
    {
        // Arrange
        $franchise = User::factory()->create(['type' => 2]);
        $admin = User::factory()->create(['type' => 1]);

        // Act
        $response = $this->actingAs($franchise)->patchJson("/api/users/{$admin->id}", ['password' => 'Tomada12345']);

        // Assert
        $response->assertStatus(403);
        $this->assertFalse(Hash::check('Tomada12345', $admin->fresh()->password));
    }

    public function test_should_return_403_when_franchise_creates_a_user(): void
    {
        // Arrange
        $franchise = User::factory()->create(['type' => 2]);

        // Act
        $response = $this->actingAs($franchise)->postJson('/api/users', [
            'nit'      => '9998887771',
            'name'     => 'Admin Falso',
            'email'    => 'falso@example.com',
            'user'     => 'adminfalso',
            'password' => 'Secreta123',
            'city_id'  => $franchise->city_id,
            'type'     => 1,
        ]);

        // Assert
        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['user' => 'adminfalso']);
    }

    public function test_should_return_403_when_franchise_deletes_a_user(): void
    {
        // Arrange
        $franchise = User::factory()->create(['type' => 2]);
        $other = User::factory()->create(['type' => 2]);

        // Act
        $response = $this->actingAs($franchise)->deleteJson("/api/users/{$other->id}");

        // Assert
        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_should_change_type_when_super_admin_updates_a_user(): void
    {
        // Arrange
        $admin = User::factory()->create(['type' => 1]);
        $franchise = User::factory()->create(['type' => 2]);

        // Act
        $this->actingAs($admin)->patchJson("/api/users/{$franchise->id}", ['type' => 3]);

        // Assert
        $this->assertSame(3, $franchise->fresh()->type);
    }

    public function test_should_reject_weak_password_when_super_admin_creates_a_user(): void
    {
        // Arrange
        $admin = User::factory()->create(['type' => 1]);

        // Act
        $response = $this->actingAs($admin)->postJson('/api/users', [
            'nit'      => '9998887772',
            'name'     => 'Franquicia',
            'email'    => 'franq@example.com',
            'user'     => 'franq',
            'password' => 'abcdef',
            'city_id'  => $admin->city_id,
        ]);

        // Assert
        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['password']);
    }
}
