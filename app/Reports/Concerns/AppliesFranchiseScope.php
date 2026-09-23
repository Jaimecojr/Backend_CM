<?php

declare(strict_types=1);

namespace App\Reports\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait AppliesFranchiseScope
{
    /**
     * Restricts $query to the authenticated user's own franchise unless
     * they are super admin. Any franchise id the client sends is applied
     * separately by the caller and only honored for super admin — see
     * each *Report::query() method.
     */
    protected function scopeFranchise(Builder $query, string $column, User $authUser): Builder
    {
        if (!$authUser->isSuperAdmin()) {
            $query->where($column, $authUser->id);
        }

        return $query;
    }
}
