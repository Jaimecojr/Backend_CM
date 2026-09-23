<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesReportAccess;
use App\Http\Requests\Reports\SalesReportRequest;
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
}
