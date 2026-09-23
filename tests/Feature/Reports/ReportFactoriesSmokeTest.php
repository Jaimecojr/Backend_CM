<?php

namespace Tests\Feature\Reports;

use App\Models\Agreement;
use App\Models\Beneficiary;
use App\Models\City;
use App\Models\Counselor;
use App\Models\Renovation;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFactoriesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_nuevas_factories_crean_registros_validos(): void
    {
        $this->assertInstanceOf(City::class, City::factory()->create());
        $this->assertInstanceOf(Agreement::class, Agreement::factory()->create());
        $this->assertInstanceOf(Counselor::class, Counselor::factory()->create());
        $this->assertInstanceOf(Renovation::class, Renovation::factory()->create());
        $this->assertInstanceOf(WhatsappMessage::class, WhatsappMessage::factory()->create());
        $this->assertInstanceOf(Beneficiary::class, Beneficiary::factory()->create());
    }
}
