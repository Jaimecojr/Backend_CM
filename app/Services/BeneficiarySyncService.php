<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliate;

final class BeneficiarySyncService
{
    /**
     * Sincroniza los beneficiarios de un afiliado con el array recibido del request:
     * elimina los que ya no estén en la lista enviada, actualiza los que traen 'id'
     * y crea los que no lo traen. Lógica movida tal cual desde
     * AffiliateController::store()/update().
     *
     * @param array<int, array<string, mixed>> $beneficiariosRequest
     */
    public function sync(Affiliate $affiliate, array $beneficiariosRequest): void
    {
        // Eliminar los beneficiarios que ya no estén en la lista enviada
        $idsToKeep = array_filter(array_column($beneficiariosRequest, 'id'));
        $affiliate->beneficiaries()->whereNotIn('id', $idsToKeep)->delete();

        foreach ($beneficiariosRequest as $ben) {
            if (!empty($ben['name'])) {
                if (!empty($ben['id'])) {
                    $affiliate->beneficiaries()->where('id', $ben['id'])->update([
                        'name' => $ben['name'],
                        'id_card' => $ben['id_card'] ?? '',
                        'bithdate' => current(array_filter([$ben['bithdate'] ?? null])) ?: null,
                    ]);
                } else {
                    $affiliate->beneficiaries()->create([
                        'name' => $ben['name'],
                        'id_card' => $ben['id_card'] ?? '',
                        'bithdate' => current(array_filter([$ben['bithdate'] ?? null])) ?: null,
                    ]);
                }
            }
        }
    }
}
