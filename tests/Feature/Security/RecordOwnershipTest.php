<?php

namespace Tests\Feature\Security;

use App\Models\Affiliate;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Appointments belong to the franchise that booked them; affiliates are shared across franchises
 * on purpose, but only the super admin may move one to another franchise.
 */
class RecordOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_return_403_when_franchise_reads_another_franchise_appointment(): void
    {
        // Arrange
        $owner = User::factory()->create(['type' => 2]);
        $intruder = User::factory()->create(['type' => 2]);
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);

        // Act
        $response = $this->actingAs($intruder)->getJson("/api/appointments/{$appointment->id}");

        // Assert
        $response->assertStatus(403);
    }

    public function test_should_return_403_when_franchise_deletes_another_franchise_appointment(): void
    {
        // Arrange
        $owner = User::factory()->create(['type' => 2]);
        $intruder = User::factory()->create(['type' => 2]);
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);

        // Act
        $response = $this->actingAs($intruder)->deleteJson("/api/appointments/{$appointment->id}");

        // Assert
        $response->assertStatus(403);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    public function test_should_allow_access_when_franchise_reads_its_own_appointment(): void
    {
        // Arrange
        $owner = User::factory()->create(['type' => 2]);
        $appointment = Appointment::factory()->create(['user_id' => $owner->id]);

        // Act
        $response = $this->actingAs($owner)->getJson("/api/appointments/{$appointment->id}");

        // Assert
        $response->assertStatus(200);
    }

    public function test_should_assign_the_authenticated_franchise_when_creating_an_appointment_for_another(): void
    {
        // Arrange
        $franchise = User::factory()->create(['type' => 2]);
        $other = User::factory()->create(['type' => 2]);
        $doctor = Doctor::factory()->create();

        // Act
        $response = $this->actingAs($franchise)->postJson('/api/appointments', [
            'afi_code'  => 123,
            'doctor_id' => $doctor->id,
            'date'      => now()->addDay()->toDateString(),
            'hour'      => '10:00',
            'address'   => 'Calle 1 # 2-3',
            'city_id'   => $doctor->city_id,
            'phone'     => '3001234567',
            'value'     => 100000,
            'type'      => 1,
            'name'      => 'Paciente',
            'user_id'   => $other->id,
        ]);

        // Assert
        $response->assertStatus(201);
        $this->assertSame($franchise->id, (int) $response->json('data.user_id'));
    }

    public function test_should_ignore_franchise_change_when_non_admin_updates_an_affiliate(): void
    {
        // Arrange
        $owner = User::factory()->create(['type' => 2]);
        $editor = User::factory()->create(['type' => 2]);
        $affiliate = Affiliate::factory()->create(['user_id' => $owner->id]);

        // Act
        $response = $this->actingAs($editor)->patchJson("/api/affiliates/{$affiliate->id}", ['user_id' => $editor->id]);

        // Assert
        $response->assertStatus(200);
        $this->assertSame($owner->id, (int) $affiliate->fresh()->user_id);
    }

    public function test_should_change_franchise_when_super_admin_updates_an_affiliate(): void
    {
        // Arrange
        $admin = User::factory()->create(['type' => 1]);
        $newOwner = User::factory()->create(['type' => 2]);
        $affiliate = Affiliate::factory()->create();

        // Act
        $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", ['user_id' => $newOwner->id]);

        // Assert
        $this->assertSame($newOwner->id, (int) $affiliate->fresh()->user_id);
    }
}
