<?php

namespace Tests\Feature\Support;

use App\Models\Affiliate;
use App\Support\IdCardLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdCardLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_quita_todo_lo_que_no_sea_digito(): void
    {
        $this->assertSame('123456789', IdCardLookup::normalize('1.234-56.789'));
        $this->assertSame('', IdCardLookup::normalize('   '));
    }

    public function test_exists_retorna_true_si_hay_coincidencia(): void
    {
        Affiliate::factory()->create(['id_card' => '555666777']);

        $this->assertTrue(IdCardLookup::exists(Affiliate::class, '555666777'));
    }

    public function test_exists_retorna_false_si_no_hay_coincidencia(): void
    {
        $this->assertFalse(IdCardLookup::exists(Affiliate::class, '999999999'));
    }

    public function test_exists_excluye_el_ignore_id(): void
    {
        $affiliate = Affiliate::factory()->create(['id_card' => '444555666']);

        $this->assertFalse(IdCardLookup::exists(Affiliate::class, '444555666', $affiliate->id));
    }
}
