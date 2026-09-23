<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Counselor;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Scoped, active-only counselor catalog for the report filters
     * (select in report 2, autocomplete in report 1).
     */
    public function counselorsCatalog(Request $request)
    {
        $user = auth()->user();

        if (!$user->isSuperAdmin() && !$user->isFranchise()) {
            return response()->json([
                'message' => 'No tiene permisos para ver este catálogo',
                'data' => [],
            ], 403);
        }

        $search = trim((string) $request->query('search', ''));

        $query = Counselor::query()->where('state', 1);

        if (!$user->isSuperAdmin()) {
            $query->where('user_id', $user->id);
        }

        if (mb_strlen($search) >= 2) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%");
            });
        }

        $counselors = $query->orderBy('name')->orderBy('lastname')
            ->limit(20)
            ->get(['id', 'name', 'lastname']);

        return response()->json([
            'message' => 'Asesores obtenidos correctamente',
            'data' => $counselors,
        ], 200);
    }
}
