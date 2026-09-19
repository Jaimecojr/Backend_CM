<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Stores the attributes listed in the model's `$uppercase` property in UPPERCASE.
 *
 * The rule lives in the model setter so every write path is covered at once: panel controllers,
 * the public website forms, seeders and factories. It only runs when an attribute is assigned, so
 * rows already in the database are left alone until the next time they are saved.
 *
 * Only list free-text fields (names, addresses, subjects...). Emails, logins, passwords, codes,
 * URLs and configuration values must not be listed: they are case-sensitive or read by other systems.
 *
 * Note that query-builder writes (`Model::where(...)->update([...])`, `$relation->update([...])`)
 * do not go through the model and therefore skip this rule; update through a model instance instead.
 *
 * @property list<string> $uppercase
 */
trait UppercasesAttributes
{
    public function setAttribute($key, $value)
    {
        // Str::upper is multibyte-aware ("pérez" -> "PÉREZ"); plain strtoupper() would leave the accents.
        if (is_string($value) && in_array($key, $this->uppercase ?? [], true)) {
            $value = Str::upper($value);
        }

        return parent::setAttribute($key, $value);
    }
}
