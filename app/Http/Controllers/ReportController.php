<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesReportAccess;
use App\Http\Requests\Reports\AffiliatesSummaryReportRequest;
use App\Http\Requests\Reports\AppointmentsReportRequest;
use App\Http\Requests\Reports\BalanceReportRequest;
use App\Http\Requests\Reports\NonRenewedAffiliatesReportRequest;
use App\Http\Requests\Reports\SalesReportRequest;
use App\Http\Requests\Reports\UnsentCarnetsReportRequest;
use App\Models\Affiliate;
use App\Models\Counselor;
use App\Reports\AffiliatesSummaryReport;
use App\Reports\AppointmentsReport;
use App\Reports\BalanceReport;
use App\Reports\NonRenewedAffiliatesReport;
use App\Reports\SalesReport;
use App\Reports\UnsentCarnetsReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use AuthorizesReportAccess;

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

    /**
     * Applies pagination, or returns every row when $perPage === 'all',
     * in the {message,data,meta} shape used across the project.
     *
     * @return array{items: \Illuminate\Support\Collection, meta: array}
     */
    private function paginateOrAll(Builder $query, ?string $perPage, int $default = 25): array
    {
        if ($perPage === 'all') {
            $all = $query->get();

            return [
                'items' => $all,
                'meta' => [
                    'current_page' => 1,
                    'last_page'    => 1,
                    'per_page'     => $all->count(),
                    'total'        => $all->count(),
                ],
            ];
        }

        $paginated = $query->paginate((int) ($perPage ?? $default));

        return [
            'items' => collect($paginated->items()),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ];
    }

    public function sales(SalesReportRequest $request, SalesReport $report)
    {
        if ($denied = $this->reportAccessDenied()) {
            return $denied;
        }

        $user    = auth()->user();
        $filters = $request->validated();
        $result  = $this->paginateOrAll($report->query($filters, $user), $filters['per_page'] ?? null);

        $items = $result['items']->map(fn (Affiliate $affiliate) => $this->mapSaleRow($affiliate));

        return response()->json([
            'message' => 'Reporte de ventas obtenido correctamente',
            'data'    => $items,
            'meta'    => $result['meta'],
            'totals'  => $report->totals($filters, $user),
        ], 200);
    }

    private function mapSaleRow(Affiliate $affiliate): array
    {
        $renovation = $affiliate->latestRenovation;
        $isRenewal  = $renovation !== null;

        return [
            'id'           => $affiliate->id,
            'payment_date' => $affiliate->payment_date,
            'fecha_desde'  => $isRenewal ? $renovation->date_ini : $affiliate->validity,
            'validity_end' => $affiliate->validity_end,
            'validity'     => $affiliate->validity,
            'counselor'    => $affiliate->counselor ? trim("{$affiliate->counselor->name} {$affiliate->counselor->lastname}") : null,
            'name'         => trim("{$affiliate->name} {$affiliate->lastname}"),
            'franchise'    => $affiliate->user->name ?? null,
            'tipo_venta'   => $isRenewal ? 'Renovación' : 'Nuevo',
            'valor_venta'  => $isRenewal ? $renovation->value : $affiliate->value,
        ];
    }

    public function balance(BalanceReportRequest $request, BalanceReport $report)
    {
        if ($denied = $this->reportAccessDenied()) {
            return $denied;
        }

        $user    = auth()->user();
        $filters = $request->validated();
        $result  = $this->paginateOrAll($report->query($filters, $user), $filters['per_page'] ?? null, 15);

        $items = $result['items']->map(fn (Affiliate $a) => [
            'id'        => $a->id,
            'counselor' => $a->counselor ? trim("{$a->counselor->name} {$a->counselor->lastname}") : null,
            'name'      => trim("{$a->name} {$a->lastname}"),
            'balance'   => $a->balance,
            'validity'  => $a->validity,
        ]);

        return response()->json([
            'message'       => 'Reporte de cartera obtenido correctamente',
            'data'          => $items,
            'meta'          => $result['meta'],
            'total_balance' => $report->totalBalance($filters, $user),
        ], 200);
    }

    public function affiliatesSummary(AffiliatesSummaryReportRequest $request, AffiliatesSummaryReport $report)
    {
        if ($denied = $this->reportAccessDenied()) {
            return $denied;
        }

        $filters = $request->validated();

        return response()->json([
            'message' => 'Indicadores de afiliados obtenidos correctamente',
            'data'    => $report->indicators($filters, auth()->user()),
            'from'    => $filters['from'] ?? null,
            'to'      => $filters['to'] ?? null,
        ], 200);
    }

    public function appointments(AppointmentsReportRequest $request, AppointmentsReport $report)
    {
        if ($denied = $this->reportAccessDenied()) {
            return $denied;
        }

        $user    = auth()->user();
        $filters = $request->validated();
        $result  = $this->paginateOrAll($report->query($filters, $user), $filters['per_page'] ?? null);

        $items = $result['items']->map(fn ($appointment) => [
            'id'     => $appointment->id,
            'name'   => AppointmentsReport::patientName($appointment),
            'doctor' => $appointment->doctor ? trim("{$appointment->doctor->name} {$appointment->doctor->lastname}") : null,
            'city'   => $appointment->doctor?->city?->name,
            'date'   => $appointment->date,
        ]);

        return response()->json([
            'message' => 'Reporte de citas obtenido correctamente',
            'data'    => $items,
            'meta'    => $result['meta'],
        ], 200);
    }

    public function nonRenewedAffiliates(NonRenewedAffiliatesReportRequest $request, NonRenewedAffiliatesReport $report)
    {
        if ($denied = $this->reportAccessDenied()) {
            return $denied;
        }

        $user    = auth()->user();
        $filters = $request->validated();
        $result  = $this->paginateOrAll($report->query($filters, $user), $filters['per_page'] ?? null);

        $items = $result['items']->map(fn (Affiliate $a) => [
            'id'           => $a->id,
            'validity_end' => $a->validity_end,
            'name'         => trim("{$a->name} {$a->lastname}"),
            'phone'        => $a->phone,
            'movil'        => $a->movil,
            'franchise'    => $a->user->name ?? null,
        ]);

        return response()->json([
            'message' => 'Reporte de clientes sin renovación obtenido correctamente',
            'data'    => $items,
            'meta'    => $result['meta'],
        ], 200);
    }

    /**
     * Super-admin only. Pagination here is manual over an in-memory
     * Collection (not Eloquent's ->paginate()) because "is this a failed
     * send" requires PHP-level JSON parsing of the response column, which
     * isn't portable in raw SQL across SQLite (tests) and MySQL (prod).
     */
    public function unsentCarnets(UnsentCarnetsReportRequest $request, UnsentCarnetsReport $report)
    {
        if ($denied = $this->reportAccessDenied(franchiseAllowed: false)) {
            return $denied;
        }

        $user    = auth()->user();
        $filters = $request->validated();
        $failed  = $report->failed($filters, $user);

        $rawPerPage = $filters['per_page'] ?? '25';
        $perPage    = $rawPerPage === 'all' ? max($failed->count(), 1) : (int) $rawPerPage;
        $page       = $rawPerPage === 'all' ? 1 : max(1, (int) $request->query('page', 1));

        $items = $failed->forPage($page, $perPage)->map(fn ($m) => [
            'date'      => substr((string) $m->created_at, 0, 10),
            'name'      => trim("{$m->affiliate_name} {$m->affiliate_lastname}"),
            'phone'     => $m->affiliate_phone,
            'movil'     => $m->affiliate_movil,
            'franchise' => $m->franchise_name,
        ])->values();

        return response()->json([
            'message' => 'Reporte de carnets no enviados obtenido correctamente',
            'data'    => $items,
            'meta'    => [
                'current_page' => $page,
                'last_page'    => $rawPerPage === 'all' ? 1 : (int) max(1, ceil($failed->count() / $perPage)),
                'per_page'     => $rawPerPage === 'all' ? $failed->count() : $perPage,
                'total'        => $failed->count(),
            ],
        ], 200);
    }
}
