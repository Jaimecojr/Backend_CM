<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliate;

final class BeneficiarySyncService
{
    /**
     * Synchronizes an affiliate's beneficiaries with the array received from the request:
     * deletes the ones no longer in the sent list, updates the ones that carry an 'id'
     * and creates the ones that don't. Logic moved as-is from
     * AffiliateController::store()/update().
     *
     * @param array<int, array<string, mixed>> $beneficiariesRequest
     */
    public function sync(Affiliate $affiliate, array $beneficiariesRequest): void
    {
        // Delete the beneficiaries that are no longer in the sent list
        $idsToKeep = array_filter(array_column($beneficiariesRequest, 'id'));
        $affiliate->beneficiaries()->whereNotIn('id', $idsToKeep)->delete();

        foreach ($beneficiariesRequest as $beneficiary) {
            if (!empty($beneficiary['name'])) {
                if (!empty($beneficiary['id'])) {
                    $affiliate->beneficiaries()->where('id', $beneficiary['id'])->update([
                        'name' => $beneficiary['name'],
                        'id_card' => $beneficiary['id_card'] ?? '',
                        'bithdate' => current(array_filter([$beneficiary['bithdate'] ?? null])) ?: null,
                    ]);
                } else {
                    $affiliate->beneficiaries()->create([
                        'name' => $beneficiary['name'],
                        'id_card' => $beneficiary['id_card'] ?? '',
                        'bithdate' => current(array_filter([$beneficiary['bithdate'] ?? null])) ?: null,
                    ]);
                }
            }
        }
    }
}
