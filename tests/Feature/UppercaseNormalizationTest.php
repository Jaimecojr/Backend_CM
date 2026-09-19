<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateNote;
use App\Models\Agreement;
use App\Models\Appointment;
use App\Models\Beneficiary;
use App\Models\City;
use App\Models\Concerns\UppercasesAttributes;
use App\Models\Contact;
use App\Models\Counselor;
use App\Models\Doctor;
use App\Models\MembershipForm;
use App\Models\MembershipFormBeneficiary;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Free-text data (names, addresses, subjects...) is stored in UPPERCASE for the modules the panel
 * and the public site write to. Emails, logins, passwords, codes and configuration keep their casing.
 * Rows that already exist are not rewritten; they are only uppercased the next time they are saved.
 */
class UppercaseNormalizationTest extends TestCase
{
    use RefreshDatabase;

    // ── The trait itself ──

    public function test_el_trait_pasa_a_mayusculas_solo_los_atributos_listados_y_respeta_tildes_y_enie(): void
    {
        $model = new class extends Model {
            use UppercasesAttributes;

            protected array $uppercase = ['name'];
        };

        $model->name = 'muñoz pérez';
        $model->email = 'Ana@Example.com';

        $this->assertSame('MUÑOZ PÉREZ', $model->name);
        $this->assertSame('Ana@Example.com', $model->email);
    }

    public function test_el_trait_deja_intactos_los_valores_que_no_son_texto(): void
    {
        $model = new class extends Model {
            use UppercasesAttributes;

            protected array $uppercase = ['name'];
        };

        $model->name = null;

        $this->assertNull($model->name);
    }

    // ── Which fields each model uppercases ──

    /**
     * @return array<string, array{class-string<Model>, array<string, string>, list<string>}>
     */
    public static function modelosProvider(): array
    {
        return [
            'afiliados' => [
                Affiliate::class,
                ['name' => 'juan', 'lastname' => 'pérez', 'address' => 'cra 5 # 1-2', 'company' => 'acme sas',
                 'email' => 'Juan@Example.com', 'contract_code' => 'ab-1', 'photo' => 'Foto.jpg'],
                ['name', 'lastname', 'address', 'company'],
            ],
            'beneficiarios' => [
                Beneficiary::class,
                ['name' => 'ana ruiz', 'id_card' => '123'],
                ['name'],
            ],
            'asesores' => [
                Counselor::class,
                ['name' => 'luis', 'lastname' => 'mora', 'address' => 'calle 9',
                 'email' => 'Luis@Example.com', 'password' => 'Secreto1'],
                ['name', 'lastname', 'address'],
            ],
            'médicos' => [
                Doctor::class,
                ['name' => 'eva', 'lastname' => 'paz', 'address' => 'av 3', 'secretary_name' => 'maría',
                 'email' => 'Eva@Example.com'],
                ['name', 'lastname', 'address', 'secretary_name'],
            ],
            'convenios' => [
                Agreement::class,
                ['name' => 'convenio salud'],
                ['name'],
            ],
            'citas' => [
                Appointment::class,
                ['name' => 'pedro gil', 'address' => 'consultorio 4'],
                ['name', 'address'],
            ],
            'solicitudes de afiliación' => [
                MembershipForm::class,
                ['name' => 'sara', 'lastname' => 'león', 'address' => 'cll 7', 'seller' => 'carlos asesor',
                 'email' => 'Sara@Example.com'],
                ['name', 'lastname', 'address', 'seller'],
            ],
            'beneficiarios de solicitud' => [
                MembershipFormBeneficiary::class,
                ['name' => 'niño uno'],
                ['name'],
            ],
            'notas de afiliado' => [
                AffiliateNote::class,
                ['body' => 'llamar mañana para renovar', 'affiliate_id' => '1'],
                ['body'],
            ],
            'contactos' => [
                Contact::class,
                ['name' => 'juan garcía', 'subject' => 'información', 'comment' => 'quiero saber más',
                 'email' => 'Juan@Example.com'],
                ['name', 'subject', 'comment'],
            ],
            'franquicias (usuarios)' => [
                User::class,
                ['name' => 'franquicia norte', 'contact' => 'rosa díaz', 'address' => 'cra 1',
                 'email' => 'Norte@Example.com', 'user' => 'norte.admin', 'password' => 'Secreto1'],
                ['name', 'contact', 'address'],
            ],
        ];
    }

    /**
     * @dataProvider modelosProvider
     *
     * @param class-string<Model>   $class
     * @param array<string, string> $input
     * @param list<string>          $upper
     */
    public function test_cada_modelo_pasa_a_mayusculas_sus_campos_de_texto_y_no_toca_el_resto(string $class, array $input, array $upper): void
    {
        $model = (new $class)->forceFill($input);

        foreach ($input as $field => $value) {
            if ($field === 'password') {
                continue; // hashed by the model cast, not comparable as plain text
            }
            $expected = in_array($field, $upper, true) ? mb_strtoupper($value) : $value;
            $this->assertSame($expected, $model->getAttribute($field), "{$class}::{$field}");
        }
    }

    // ── Public site forms ──

    private function cityId(): int
    {
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'TestDept', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return City::create(['name' => 'TestCity', 'department_id' => $deptId])->id;
    }

    public function test_la_solicitud_de_afiliacion_publica_se_guarda_en_mayusculas(): void
    {
        $response = $this->postJson('/api/public/affiliate-request', [
            'name'          => 'juan carlos',
            'lastname'      => 'pérez muñoz',
            'document'      => '1234567890',
            'movil'         => '3001234567',
            'email'         => 'Juan@Example.com',
            'birth_date'    => '1990-05-15',
            'address'       => 'calle 1 # 2-3',
            'city_id'       => $this->cityId(),
            'advisor_name'  => 'carlos asesor',
            'beneficiaries' => [['full_name' => 'ana pérez']],
        ]);

        $response->assertStatus(201)->assertJsonPath('data.name', 'JUAN CARLOS');

        $form = MembershipForm::firstOrFail();
        $this->assertSame('JUAN CARLOS', $form->name);
        $this->assertSame('PÉREZ MUÑOZ', $form->lastname);
        $this->assertSame('CALLE 1 # 2-3', $form->address);
        $this->assertSame('CARLOS ASESOR', $form->seller);
        $this->assertSame('Juan@Example.com', $form->email);
        $this->assertSame('ANA PÉREZ', MembershipFormBeneficiary::firstOrFail()->name);
    }

    public function test_el_mensaje_de_contacto_publico_se_guarda_en_mayusculas(): void
    {
        $response = $this->postJson('/api/public/contact', [
            'name'    => 'juan garcía',
            'movil'   => '3001234567',
            'email'   => 'Juan@Example.com',
            'asunto'  => 'información sobre planes',
            'city_id' => $this->cityId(),
            'mensaje' => 'hola, quiero información sobre los planes disponibles.',
        ]);

        $response->assertStatus(201);

        $contact = Contact::firstOrFail();
        $this->assertSame('JUAN GARCÍA', $contact->name);
        $this->assertSame('INFORMACIÓN SOBRE PLANES', $contact->subject);
        $this->assertSame('HOLA, QUIERO INFORMACIÓN SOBRE LOS PLANES DISPONIBLES.', $contact->comment);
        $this->assertSame('Juan@Example.com', $contact->email);
    }

    // ── Panel: beneficiaries edited in place must go through the model too ──

    public function test_editar_un_beneficiario_existente_tambien_lo_guarda_en_mayusculas(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();
        $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [['name' => 'nombre original', 'id_card' => '222']],
        ])->assertStatus(200);
        $beneficiary = Beneficiary::where('affiliate_id', $affiliate->id)->firstOrFail();
        $this->assertSame('NOMBRE ORIGINAL', $beneficiary->name);

        $this->actingAs($admin)->patchJson("/api/affiliates/{$affiliate->id}", [
            'beneficiaries' => [['id' => $beneficiary->id, 'name' => 'nombre editado', 'id_card' => '222']],
        ])->assertStatus(200);

        $this->assertSame('NOMBRE EDITADO', $beneficiary->fresh()->name);
    }

    // ── Panel: affiliate notes ──

    public function test_una_nota_de_afiliado_se_guarda_en_mayusculas(): void
    {
        $admin = User::factory()->create(['type' => 1]);
        $affiliate = Affiliate::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/affiliates/{$affiliate->id}/notes", [
            'body' => 'llamar mañana para renovar el plan',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.body', 'LLAMAR MAÑANA PARA RENOVAR EL PLAN');
        $this->assertSame('LLAMAR MAÑANA PARA RENOVAR EL PLAN', AffiliateNote::firstOrFail()->body);
    }
}
