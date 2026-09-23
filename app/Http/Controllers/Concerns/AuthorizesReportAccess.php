<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait AuthorizesReportAccess
{
    /**
     * The single access rule for all 6 reports: super admin always gets
     * in; franchise gets in only when $franchiseAllowed (false for the
     * Carnets No Enviados report); anyone else — including type=3, which
     * has no real login flow today but is a valid `users.type` value —
     * is denied. Both ReportController and ReportExportController use
     * this instead of re-checking the role inline in every method, so a
     * fix here fixes all 12 endpoints at once.
     */
    private function reportAccessDenied(
        bool $franchiseAllowed = true,
        string $message = 'No tiene permisos para ver este reporte'
    ): ?JsonResponse {
        $user = auth()->user();

        if ($user->isSuperAdmin()) {
            return null;
        }
        if ($franchiseAllowed && $user->isFranchise()) {
            return null;
        }

        return response()->json(['message' => $message], 403);
    }
}
