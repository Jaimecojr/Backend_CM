<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiringTodayFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiring_today_returns_movil_and_phone(): void
    {
        $user = User::factory()->create(['type' => 1]);
        $hoy  = Carbon::today()->toDateString();

        Affiliate::factory()->create([
            'stade'        => 1,
            'validity_end' => $hoy,
            'movil'        => '3001234567',
            'phone'        => '6041234567',
        ]);

        $response = $this->actingAs($user)->getJson('/api/affiliates/expiring-today');

        $response->assertStatus(200)
                 ->assertJsonPath('data.0.movil', '3001234567')
                 ->assertJsonPath('data.0.phone', '6041234567');
    }
}