<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppointmentWhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_envia_notificacion_con_codigo_es_co(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.cita']]], 200),
        ]);

        Setting::create([
            'wa_api_version'                => 'v18.0',
            'wa_phone_number_id'            => '1234567890',
            'wa_bearer_token'               => 'token-de-prueba',
            // wa_template_name es NOT NULL en la tabla settings (se usa para carnets);
            // no es relevante para esta prueba pero debe tener un valor para satisfacer la constraint.
            'wa_template_name'              => 'carnet_afiliado',
            'wa_appointment_template_name'  => 'notificacion_cita',
        ]);

        $user   = User::factory()->create();
        $doctor = Doctor::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/appointments', [
            'afi_code'  => 123,
            'doctor_id' => $doctor->id,
            'date'      => now()->addDay()->toDateString(),
            'hour'      => '10:00',
            'address'   => 'Calle 1 # 2-3',
            'city_id'   => $doctor->city_id,
            'phone'     => '3001234567',
            'value'     => 100000,
            'type'      => 1,
            'name'      => 'Paciente Test',
            'user_id'   => $user->id,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('whatsapp.enviado', true);

        Http::assertSent(fn ($request) => $request['template']['language']['code'] === 'es_CO');
    }
}
