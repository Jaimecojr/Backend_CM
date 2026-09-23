<?php

namespace Tests\Feature\Reports;

use App\Models\Affiliate;
use App\Models\Renovation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateLatestRenovationTest extends TestCase
{
    use RefreshDatabase;

    public function test_devuelve_null_cuando_no_hay_renovaciones(): void
    {
        $affiliate = Affiliate::factory()->create();

        $this->assertNull($affiliate->latestRenovation);
    }

    public function test_devuelve_la_renovacion_con_mayor_id(): void
    {
        $affiliate = Affiliate::factory()->create();

        $first  = Renovation::create([
            'affiliate_id' => $affiliate->id,
            'date_ini'     => '2025-01-01',
            'date_end'     => '2025-12-31',
            'date_payment' => '2025-01-01',
            'value'        => 100000,
        ]);
        $second = Renovation::create([
            'affiliate_id' => $affiliate->id,
            'date_ini'     => '2026-01-01',
            'date_end'     => '2026-12-31',
            'date_payment' => '2026-01-02',
            'value'        => 120000,
        ]);

        $this->assertTrue($second->id > $first->id);
        $this->assertSame($second->id, $affiliate->fresh()->latestRenovation->id);
    }
}
