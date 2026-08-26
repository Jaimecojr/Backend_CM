<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_es_super_admin_es_verdadero_solo_para_type_1(): void
    {
        $admin    = User::factory()->create(['type' => 1]);
        $counselor = User::factory()->create(['type' => 2]);
        $advisor  = User::factory()->create(['type' => 3]);

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertFalse($counselor->isSuperAdmin());
        $this->assertFalse($advisor->isSuperAdmin());
    }
}
