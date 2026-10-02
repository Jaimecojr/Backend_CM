<?php

namespace Tests\Feature\Security;

use App\Models\Affiliate;
use App\Models\City;
use App\Models\Contact;
use App\Models\Counselor;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SensitiveDataExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_not_expose_counselor_password_when_listing_counselors(): void
    {
        // Arrange
        $user = User::factory()->create(['type' => 2]);
        Counselor::factory()->create(['password' => md5('legacy')]);

        // Act
        $response = $this->actingAs($user)->getJson('/api/counselors');

        // Assert
        $response->assertStatus(200);
        $this->assertStringNotContainsString(md5('legacy'), $response->getContent());
    }

    public function test_should_not_expose_counselor_password_when_showing_an_affiliate(): void
    {
        // Arrange
        $user = User::factory()->create(['type' => 2]);
        $counselor = Counselor::factory()->create(['password' => md5('legacy')]);
        $affiliate = Affiliate::factory()->create(['counselor_id' => $counselor->id]);

        // Act
        $response = $this->actingAs($user)->getJson("/api/affiliates/{$affiliate->id}");

        // Assert
        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('data.counselor'));
    }

    public function test_should_return_only_a_flag_when_super_admin_reads_whatsapp_settings(): void
    {
        // Arrange
        $admin = User::factory()->create(['type' => 1]);
        $this->createSetting('EAAtoken-secreto');

        // Act
        $response = $this->actingAs($admin)->getJson('/api/settings');

        // Assert
        $response->assertStatus(200);
        $response->assertJsonPath('data.wa_bearer_token_set', true);
        $this->assertStringNotContainsString('EAAtoken-secreto', $response->getContent());
    }

    public function test_should_keep_the_stored_token_when_update_sends_it_empty(): void
    {
        // Arrange
        $admin = User::factory()->create(['type' => 1]);
        $setting = $this->createSetting('EAAtoken-original');

        // Act
        $response = $this->actingAs($admin)->patchJson("/api/settings/{$setting->id}", [
            'wa_api_version'     => 'v19.0',
            'wa_phone_number_id' => '123',
            'wa_bearer_token'    => '',
            'wa_template_name'   => 'carnet',
        ]);

        // Assert
        $response->assertStatus(200);
        $this->assertSame('EAAtoken-original', $setting->fresh()->wa_bearer_token);
    }

    public function test_should_store_whatsapp_token_encrypted_when_saved(): void
    {
        // Arrange & Act
        $setting = $this->createSetting('EAAtoken-plano');

        // Assert
        $raw = DB::table('settings')->where('id', $setting->id)->value('wa_bearer_token');
        $this->assertNotSame('EAAtoken-plano', $raw);
        $this->assertSame('EAAtoken-plano', $setting->fresh()->wa_bearer_token);
    }

    public function test_should_store_contact_message_encrypted_and_uppercased_when_saved(): void
    {
        // Arrange & Act
        $contact = Contact::factory()->create([
            'city_id' => City::factory(),
            'comment' => 'tengo diabetes y necesito cita',
        ]);

        // Assert
        $raw = DB::table('contacts')->where('id', $contact->id)->value('comment');
        $this->assertStringNotContainsString('DIABETES', $raw);
        $this->assertSame('TENGO DIABETES Y NECESITO CITA', $contact->fresh()->comment);
    }

    public function test_should_not_return_internal_ids_when_public_status_finds_an_affiliate(): void
    {
        // Arrange
        Affiliate::factory()->create(['id_card' => '1234567890']);

        // Act
        $response = $this->postJson('/api/public/affiliate-status', ['document_number' => '1234567890']);

        // Assert
        $response->assertStatus(200);
        $this->assertArrayNotHasKey('id', $response->json('data'));
    }

    private function createSetting(string $token): Setting
    {
        return Setting::create([
            'wa_api_version'     => 'v18.0',
            'wa_phone_number_id' => '123456789',
            'wa_bearer_token'    => $token,
            'wa_template_name'   => 'carnet',
        ]);
    }
}
