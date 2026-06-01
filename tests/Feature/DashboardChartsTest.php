<?php
namespace Tests\Feature;
use App\Models\Affiliate;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_12_month_arrays(): void
    {
        $user = User::factory()->create(['type' => 3]);
        $response = $this->actingAs($user)->getJson('/api/dashboard/charts?year=' . now()->year);
        $response->assertStatus(200)
                 ->assertJsonCount(12, 'data.appointments_by_month')
                 ->assertJsonCount(12, 'data.affiliates_by_month');
    }

    public function test_non_admin_does_not_receive_by_franchise(): void
    {
        $user = User::factory()->create(['type' => 2]);
        $response = $this->actingAs($user)->getJson('/api/dashboard/charts');
        $response->assertStatus(200);
        $this->assertArrayNotHasKey('by_franchise', $response->json('data'));
    }

    public function test_admin_receives_by_franchise(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        User::factory()->create(['type' => 2, 'state' => 1]);
        $response = $this->actingAs($admin)->getJson('/api/dashboard/charts');
        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => ['by_franchise' => ['users', 'appointments_by_franchise', 'affiliates_by_franchise']]]);
    }

    public function test_non_admin_only_sees_own_data(): void
    {
        $userA = User::factory()->create(['type' => 2]);
        $userB = User::factory()->create(['type' => 2]);
        $year  = now()->year;
        Appointment::factory()->create(['date' => now()->toDateString(), 'user_id' => $userA->id]);
        Appointment::factory()->create(['date' => now()->toDateString(), 'user_id' => $userB->id]);
        $response = $this->actingAs($userA)->getJson("/api/dashboard/charts?year={$year}");
        $mes = (int) now()->format('n') - 1;
        $response->assertStatus(200)
                 ->assertJsonPath("data.appointments_by_month.{$mes}", 1);
    }
}
