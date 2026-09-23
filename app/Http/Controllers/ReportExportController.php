<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesReportAccess;
use App\Http\Requests\Reports\AffiliatesSummaryReportRequest;
use App\Http\Requests\Reports\AppointmentsReportRequest;
use App\Http\Requests\Reports\BalanceReportRequest;
use App\Http\Requests\Reports\NonRenewedAffiliatesReportRequest;
use App\Http\Requests\Reports\SalesReportRequest;
use App\Reports\Exports\AffiliatesSummaryReportExport;
use App\Reports\Exports\AppointmentsReportExport;
use App\Reports\Exports\BalanceReportExport;
use App\Reports\Exports\NonRenewedAffiliatesReportExport;
use App\Reports\Exports\SalesReportExport;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class ReportExportController extends Controller
{
    use AuthorizesReportAccess;

    public function sales(SalesReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Ventas_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new SalesReportExport($request->validated(), auth()->user()), $filename);
    }

    public function balance(BalanceReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Cartera_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new BalanceReportExport($request->validated(), auth()->user()), $filename);
    }

    public function affiliatesSummary(AffiliatesSummaryReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Resumen_Afiliados_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new AffiliatesSummaryReportExport($request->validated(), auth()->user()), $filename);
    }

    public function appointments(AppointmentsReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Citas_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new AppointmentsReportExport($request->validated(), auth()->user()), $filename);
    }

    public function nonRenewedAffiliates(NonRenewedAffiliatesReportRequest $request)
    {
        if ($denied = $this->reportAccessDenied(message: 'No tiene permisos para exportar este reporte')) {
            return $denied;
        }

        $filename = 'Reporte_Sin_Renovacion_' . Carbon::now()->format('d-m-Y') . '.xlsx';

        return Excel::download(new NonRenewedAffiliatesReportExport($request->validated(), auth()->user()), $filename);
    }
}
