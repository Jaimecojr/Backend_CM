<?php

namespace Tests\Feature\Security;

use App\Models\City;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Production already holds plaintext rows; without this migration the new `encrypted` casts
 * would throw on read and break the WhatsApp sender, the contacts list and the notes.
 */
class EncryptSensitiveColumnsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_should_encrypt_legacy_plaintext_rows_when_migration_runs(): void
    {
        // Arrange
        $contact = Contact::factory()->create(['city_id' => City::factory()]);
        DB::table('contacts')->where('id', $contact->id)->update(['comment' => 'TEXTO LEGADO']);
        $migration = require database_path('migrations/2026_10_01_000000_encrypt_sensitive_text_columns.php');

        // Act
        $migration->up();

        // Assert
        $raw = DB::table('contacts')->where('id', $contact->id)->value('comment');
        $this->assertSame('TEXTO LEGADO', Crypt::decryptString($raw));
        $this->assertSame('TEXTO LEGADO', $contact->fresh()->comment);
    }

    public function test_should_leave_already_encrypted_rows_untouched_when_migration_runs_twice(): void
    {
        // Arrange
        $contact = Contact::factory()->create(['city_id' => City::factory(), 'comment' => 'mensaje cifrado']);
        $before = DB::table('contacts')->where('id', $contact->id)->value('comment');
        $migration = require database_path('migrations/2026_10_01_000000_encrypt_sensitive_text_columns.php');

        // Act
        $migration->up();

        // Assert
        $this->assertSame($before, DB::table('contacts')->where('id', $contact->id)->value('comment'));
    }
}
