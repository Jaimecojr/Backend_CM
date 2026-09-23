<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Affiliate;
use App\Models\Beneficiary;
use App\Models\User;
use App\Reports\Concerns\AppliesFranchiseScope;
use Illuminate\Database\Eloquent\Builder;

class AffiliatesSummaryReport
{
    use AppliesFranchiseScope;

    private function baseQuery(array $filters, User $authUser): Builder
    {
        $query = Affiliate::query()->where('stade', 1);

        $this->scopeFranchise($query, 'user_id', $authUser);

        if ($authUser->isSuperAdmin() && !empty($filters['franchise_id'])) {
            $query->where('user_id', $filters['franchise_id']);
        }
        if (!empty($filters['from']) && !empty($filters['to'])) {
            $query->whereBetween('validity', [$filters['from'], $filters['to']]);
        }
        if (!empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        } elseif (!empty($filters['department_id'])) {
            $query->whereHas('city', fn (Builder $q) => $q->where('department_id', $filters['department_id']));
        }

        return $query;
    }

    public function indicators(array $filters, User $authUser): array
    {
        $today = now()->toDateString();

        $titulares          = (clone $this->baseQuery($filters, $authUser))->count();
        $titularesActivos   = (clone $this->baseQuery($filters, $authUser))
            ->where('validity_end', '>=', $today)->count();
        $titularesInactivos = $titulares - $titularesActivos;

        $beneficiarios = Beneficiary::whereIn(
            'affiliate_id',
            (clone $this->baseQuery($filters, $authUser))->select('id')
        )->count();
        $beneficiariosActivos = Beneficiary::whereIn(
            'affiliate_id',
            (clone $this->baseQuery($filters, $authUser))->where('validity_end', '>=', $today)->select('id')
        )->count();
        $beneficiariosInactivos = $beneficiarios - $beneficiariosActivos;

        return [
            'titulares'               => $titulares,
            'titulares_activos'       => $titularesActivos,
            'titulares_inactivos'     => $titularesInactivos,
            'beneficiarios'           => $beneficiarios,
            'beneficiarios_activos'   => $beneficiariosActivos,
            'beneficiarios_inactivos' => $beneficiariosInactivos,
        ];
    }
}
