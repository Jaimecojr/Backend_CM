<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIsFranchiseTest extends TestCase
{
    use RefreshDatabase;

    public function test_es_franquicia_es_verdadero_solo_para_type_2(): void
    {
        $admin     = User::factory()->create(['type' => 1]);
        $franchise = User::factory()->create(['type' => 2]);
        $other     = User::factory()->create(['type' => 3]);

        $this->assertFalse($admin->isFranchise());
        $this->assertTrue($franchise->isFranchise());
        $this->assertFalse($other->isFranchise());
    }
}
