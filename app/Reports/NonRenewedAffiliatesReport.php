<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Affiliate;
use App\Models\User;
use App\Reports\Concerns\AppliesFranchiseScope;
use Illuminate\Database\Eloquent\Builder;

class NonRenewedAffiliatesReport
{
    use AppliesFranchiseScope;

    /**
     * No `stade` filter on purpose: the daily affiliates:update-expired cron
     * already flips expired affiliates to stade=2, so filtering by stade=1
     * (as every other report in this module does) would leave this report
     * almost empty — a non-renewed affiliate is identified purely by
     * validity_end being in the past, regardless of what stade the cron
     * already moved it to.
     */
    public function query(array $filters, User $authUser): Builder
    {
        $query = Affiliate::query()
            ->with(['user:id,name'])
            ->where('validity_end', '<=', now()->toDateString());

        $this->scopeFranchise($query, 'user_id', $authUser);

        if ($authUser->isSuperAdmin() && !empty($filters['franchise_id'])) {
            $query->where('user_id', $filters['franchise_id']);
        }
        if (!empty($filters['from'])) {
            $query->where('validity_end', '>=', $filters['from']);
        }

        return $query->orderByDesc('validity_end');
    }
}
