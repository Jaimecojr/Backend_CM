<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsPreflightTest extends TestCase
{
    public function test_preflight_cors_se_cachea_por_24_horas(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'X-XSRF-TOKEN',
        ])->options('/api/affiliates');

        $response->assertHeader('Access-Control-Max-Age', '86400');
    }
}
