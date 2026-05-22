<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_todays_appointments(): void
    {
        $user   = User::factory()->create(['type' => 1]);
        $doctor = Doctor::factory()->create(['name' => 'Ana', 'lastname' => 'García']);

        Appointment::factory()->create([
            'date'      => Carbon::today()->toDateString(),
            'name'      => 'Paciente Hoy',
            'hour'      => '09:00',
            'doctor_id' => $doctor->id,
            'user_id'   => $user->id,
        ]);
        Appointment::factory()->create([
            'date'    => Carbon::tomorrow()->toDateString(),
            'name'    => 'Paciente Mañana',
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/appointments/today');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'Paciente Hoy');
    }

    public function test_non_admin_only_sees_own_appointments(): void
    {
        $userA  = User::factory()->create(['type' => 2]);
        $userB  = User::factory()->create(['type' => 2]);
        $doctor = Doctor::factory()->create();
        $hoy    = Carbon::today()->toDateString();

        Appointment::factory()->create(['date' => $hoy, 'user_id' => $userA->id, 'doctor_id' => $doctor->id]);
        Appointment::factory()->create(['date' => $hoy, 'user_id' => $userB->id, 'doctor_id' => $doctor->id]);

        $response = $this->actingAs($userA)->getJson('/api/appointments/today');

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }
}
