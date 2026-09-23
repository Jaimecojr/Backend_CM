<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Affiliate;
use App\Models\User;
use App\Reports\Concerns\AppliesFranchiseScope;
use Illuminate\Database\Eloquent\Builder;

class SalesReport
{
    use AppliesFranchiseScope;

    public function query(array $filters, User $authUser): Builder
    {
        $query = $this->filteredQuery($filters, $authUser)
            ->with(['counselor:id,name,lastname', 'user:id,name', 'latestRenovation']);

        return $query->orderByDesc('payment_date')->orderBy('name');
    }

    /**
     * The filter/scope logic shared by query() (listing + export, which
     * needs the eager-loaded relations and ordering) and totals() (which
     * aggregates in the DB and must NOT carry query()'s ->with(), since
     * selectRaw()'ing an aggregate row breaks Eloquent's relation matching).
     */
    private function filteredQuery(array $filters, User $authUser): Builder
    {
        $query = Affiliate::query()
            ->where('stade', 1)
            ->whereNotNull('payment_date');

        $this->scopeFranchise($query, 'user_id', $authUser);

        if ($authUser->isSuperAdmin() && !empty($filters['franchise_id'])) {
            $query->where('user_id', $filters['franchise_id']);
        }
        if (!empty($filters['from'])) {
            $query->where('payment_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('payment_date', '<=', $filters['to']);
        }
        if (!empty($filters['counselor_id'])) {
            $query->where('counselor_id', $filters['counselor_id']);
        }

        return $query;
    }

    /**
     * Classifies a single affiliate row as "Nuevo" or "Renovación" and
     * resolves the fecha_desde/valor_venta pair accordingly — the single
     * source of truth shared by ReportController::mapSaleRow() and
     * SalesReportExport::map() so the classification logic lives in
     * exactly one place (mirrors AppointmentsReport::patientName()).
     */
    public static function classify(Affiliate $affiliate): array
    {
        $renovation = $affiliate->latestRenovation;
        $isRenewal  = $renovation !== null;

        return [
            'tipo_venta'  => $isRenewal ? 'Renovación' : 'Nuevo',
            'fecha_desde' => $isRenewal ? $renovation->date_ini : $affiliate->validity,
            'valor_venta' => $isRenewal ? $renovation->value : $affiliate->value,
        ];
    }

    /**
     * Aggregated over the WHOLE filtered set, never just the current page.
     *
     * Computed with two DB-side aggregate queries instead of pulling every
     * matching affiliate (plus eager-loaded relations) into PHP: one for
     * affiliates with no renovation at all ("Nuevo"), and one joined to
     * Affiliate::latestRenovation() (a hasOne ...->ofMany('id','max')) for
     * affiliates whose latest renovation is the "Renovación" value.
     */
    public function totals(array $filters, User $authUser): array
    {
        $new = $this->filteredQuery($filters, $authUser)
            ->whereDoesntHave('renovations')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(affiliates.value), 0) as total')
            ->first();

        $renewed = $this->filteredQuery($filters, $authUser)
            ->joinSub(
                fn ($q) => $q->from('renovations')
                    ->selectRaw('affiliate_id, MAX(id) as latest_id')
                    ->groupBy('affiliate_id'),
                'latest_renovation_ids',
                'latest_renovation_ids.affiliate_id',
                '=',
                'affiliates.id'
            )
            // Inner join: only affiliates with at least one renovation survive,
            // and each contributes exactly one row (its latest renovation).
            ->join('renovations', 'renovations.id', '=', 'latest_renovation_ids.latest_id')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(renovations.value), 0) as total')
            ->first();

        return [
            'new_count'      => (int) $new->cnt,
            'new_value'      => (int) $new->total,
            'renewal_count'  => (int) $renewed->cnt,
            'renewal_value'  => (int) $renewed->total,
        ];
    }
}
