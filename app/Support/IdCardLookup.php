<?php

declare(strict_types=1);

namespace App\Support;

class IdCardLookup
{
    /**
     * Strips every non-digit character. Shared by every checkIdCard()
     * endpoint so the normalization rule lives in exactly one place.
     */
    public static function normalize(string $idCard): string
    {
        return preg_replace('/\D/', '', $idCard) ?? '';
    }

    /**
     * Checks whether a record with this (already normalized) id card exists
     * for the given model, optionally excluding one id — used when editing,
     * so the record doesn't collide with itself.
     *
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     */
    public static function exists(string $modelClass, string $idCard, ?int $ignoreId = null): bool
    {
        $query = $modelClass::query()->where('id_card', $idCard);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
