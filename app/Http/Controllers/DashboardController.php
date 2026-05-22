<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats()
    {
        if (auth()->user()->type !== 1) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $hoy       = Carbon::today()->toDateString();
        $inicioMes = Carbon::now()->startOfMonth()->toDateString();
        $finMes    = Carbon::now()->endOfMonth()->toDateString();

        $active           = Affiliate::where('stade', 1)->count();
        $inactive         = Affiliate::where('stade', 2)->count();
        $inactiveByExpiry = Affiliate::where('stade', 2)
                                ->where('validity_end', '<', $hoy)
                                ->count();
        $thisMonth = Appointment::whereBetween('date', [$inicioMes, $finMes])->count();

        return response()->json([
            'message' => 'Estadísticas del dashboard',
            'data'    => [
                'affiliates' => [
                    'active'             => $active,
                    'inactive'           => $inactive,
                    'inactive_by_expiry' => $inactiveByExpiry,
                ],
                'appointments' => [
                    'this_month' => $thisMonth,
                ],
            ],
        ], 200);
    }

    public function charts(Request $request)
    {
        $year = (int) $request->get('year', now()->year);
        $user = auth()->user();

        // SQLite (testing) no soporta MONTH(); usar strftime en su lugar
        $db = config('database.default');
        $monthFunc = $db === 'sqlite' ? "CAST(strftime('%m', date) as INTEGER)" : "MONTH(date)";
        $saleMonthFunc = $db === 'sqlite' ? "CAST(strftime('%m', sale_date) as INTEGER)" : "MONTH(sale_date)";

        $apptQuery = Appointment::selectRaw("{$monthFunc} as mes, COUNT(*) as total")
            ->whereYear('date', $year)
            ->groupBy('mes');

        $affilQuery = Affiliate::selectRaw("{$saleMonthFunc} as mes, COUNT(*) as total")
            ->whereYear('sale_date', $year)
            ->groupBy('mes');

        if ($user->type !== 1) {
            $apptQuery->where('user_id', $user->id);
            $affilQuery->where('user_id', $user->id);
        }

        $appointmentsByMonth = array_fill(0, 12, 0);
        foreach ($apptQuery->get() as $row) {
            $appointmentsByMonth[$row->mes - 1] = (int) $row->total;
        }

        $affiliatesByMonth = array_fill(0, 12, 0);
        foreach ($affilQuery->get() as $row) {
            $affiliatesByMonth[$row->mes - 1] = (int) $row->total;
        }

        $data = [
            'appointments_by_month' => $appointmentsByMonth,
            'affiliates_by_month'   => $affiliatesByMonth,
        ];

        if ($user->type === 1) {
            $franchises = User::where('type', 2)->where('state', 1)->get(['id', 'name']);
            $appointmentsByFranchise = [];
            $affiliatesByFranchise   = [];

            foreach ($franchises as $franchise) {
                $months = array_fill(0, 12, 0);
                foreach (
                    Appointment::selectRaw("{$monthFunc} as mes, COUNT(*) as total")
                        ->whereYear('date', $year)
                        ->where('user_id', $franchise->id)
                        ->groupBy('mes')
                        ->get() as $row
                ) {
                    $months[$row->mes - 1] = (int) $row->total;
                }
                $appointmentsByFranchise[] = $months;

                $months = array_fill(0, 12, 0);
                foreach (
                    Affiliate::selectRaw("{$saleMonthFunc} as mes, COUNT(*) as total")
                        ->whereYear('sale_date', $year)
                        ->where('user_id', $franchise->id)
                        ->groupBy('mes')
                        ->get() as $row
                ) {
                    $months[$row->mes - 1] = (int) $row->total;
                }
                $affiliatesByFranchise[] = $months;
            }

            $data['by_franchise'] = [
                'users'                     => $franchises->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
                'appointments_by_franchise' => $appointmentsByFranchise,
                'affiliates_by_franchise'   => $affiliatesByFranchise,
            ];
        }

        return response()->json([
            'message' => 'Datos de gráficas',
            'data'    => $data,
        ], 200);
    }
}
