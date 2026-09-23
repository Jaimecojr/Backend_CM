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
        $query = Affiliate::query()
            ->with(['counselor:id,name,lastname', 'user:id,name', 'latestRenovation'])
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

        return $query->orderByDesc('payment_date')->orderBy('name');
    }

    /** Aggregated over the WHOLE filtered set, never just the current page. */
    public function totals(array $filters, User $authUser): array
    {
        $affiliates = $this->query($filters, $authUser)->get();

        $new      = $affiliates->filter(fn (Affiliate $a) => $a->latestRenovation === null);
        $renewed  = $affiliates->filter(fn (Affiliate $a) => $a->latestRenovation !== null);

        return [
            'new_count'      => $new->count(),
            'new_value'      => (int) $new->sum('value'),
            'renewal_count'  => $renewed->count(),
            'renewal_value'  => (int) $renewed->sum(fn (Affiliate $a) => $a->latestRenovation->value),
        ];
    }
}
