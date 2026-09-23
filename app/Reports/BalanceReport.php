<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Affiliate;
use App\Models\User;
use App\Reports\Concerns\AppliesFranchiseScope;
use Illuminate\Database\Eloquent\Builder;

class BalanceReport
{
    use AppliesFranchiseScope;

    /**
     * Mirrors the legacy system's report, which was an inner join requiring
     * cou_stade = 1 — intentional, not a bug. An affiliate's outstanding
     * balance stops appearing in this report the moment their counselor is
     * deactivated, even though the affiliate itself is still active and
     * still owes the balance.
     */
    public function query(array $filters, User $authUser): Builder
    {
        $query = Affiliate::query()
            ->with(['counselor:id,name,lastname'])
            ->where('stade', 1)
            ->where('balance', '>', 0)
            ->whereHas('counselor', fn (Builder $q) => $q->where('state', 1));

        $this->scopeFranchise($query, 'user_id', $authUser);

        if ($authUser->isSuperAdmin() && !empty($filters['franchise_id'])) {
            $query->where('user_id', $filters['franchise_id']);
        }
        if (!empty($filters['counselor_id'])) {
            $query->where('counselor_id', $filters['counselor_id']);
        }

        return $query->orderBy('name');
    }

    /** Aggregated over the WHOLE filtered set, never just the current page. */
    public function totalBalance(array $filters, User $authUser): int
    {
        return (int) $this->query($filters, $authUser)->sum('balance');
    }
}
