<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Encrypts, in place, the values written before these columns got the `encrypted` cast.
 *
 * Without it the models would fail to decrypt the old plaintext rows. Values that already decrypt
 * are left untouched, so running it twice is harmless. Uses APP_KEY: if the key is rotated later,
 * keep the old one in APP_PREVIOUS_KEYS or these values become unreadable.
 */
return new class extends Migration
{
    /** @var array<string, string> table => column */
    private array $columns = [
        'settings'        => 'wa_bearer_token',
        'contacts'        => 'comment',
        'affiliate_notes' => 'body',
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $column) {
            $this->transform($table, $column, function (string $value): ?string {
                return $this->isEncrypted($value) ? null : Crypt::encryptString($value);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $column) {
            $this->transform($table, $column, function (string $value): ?string {
                return $this->isEncrypted($value) ? Crypt::decryptString($value) : null;
            });
        }
    }

    /**
     * @param  callable(string): ?string  $map  returns the new value, or null to leave the row as is
     */
    private function transform(string $table, string $column, callable $map): void
    {
        DB::table($table)->whereNotNull($column)->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column, $map) {
                foreach ($rows as $row) {
                    $new = $map((string) $row->{$column});

                    if ($new !== null) {
                        DB::table($table)->where('id', $row->id)->update([$column => $new]);
                    }
                }
            });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
