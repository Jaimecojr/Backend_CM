<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_receives_403(): void
    {
        $user = User::factory()->create(['type' => 2]);
        $this->actingAs($user)->getJson('/api/dashboard/stats')
             ->assertStatus(403);
    }

    public function test_admin_receives_correct_counts(): void
    {
        $user = User::factory()->create(['type' => 1]);
        $hoy  = Carbon::today()->toDateString();

        Affiliate::factory()->count(3)->create(['stade' => 1]);
        Affiliate::factory()->count(2)->create(['stade' => 2, 'validity_end' => Carbon::yesterday()->toDateString()]);
        Affiliate::factory()->create(['stade' => 2, 'validity_end' => Carbon::tomorrow()->toDateString()]);

        Appointment::factory()->count(4)->create(['date' => Carbon::now()->startOfMonth()->addDays(2)->toDateString()]);
        Appointment::factory()->create(['date' => Carbon::now()->subMonth()->toDateString()]); // mes anterior

        $response = $this->actingAs($user)->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
                 ->assertJsonPath('data.affiliates.active', 3)
                 ->assertJsonPath('data.affiliates.inactive', 3)
                 ->assertJsonPath('data.affiliates.inactive_by_expiry', 2)
                 ->assertJsonPath('data.appointments.this_month', 4);
    }
}
