<?php

namespace Tests\Feature;

use App\Models\Renovation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenovationRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_put_sobre_renovation_no_registrada_retorna_404(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->putJson('/api/renovations/1', ['value' => 1000]);

        $response->assertStatus(405);
    }

    public function test_delete_sobre_renovation_no_registrada_retorna_404(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->deleteJson('/api/renovations/1');

        $response->assertStatus(405);
    }
}
