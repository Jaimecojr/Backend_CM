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
        if (!auth()->user()->esSuperAdmin()) {
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

        $db = config('database.default');
        $apptMonthFunc = $db === 'sqlite' ? "CAST(strftime('%m', date) as INTEGER)" : "MONTH(date)";
        $pmtMonthFunc  = $db === 'sqlite' ? "CAST(strftime('%m', payment_date) as INTEGER)" : "MONTH(payment_date)";

        $apptQuery = Appointment::selectRaw("{$apptMonthFunc} as mes, COUNT(*) as total")
            ->whereYear('date', $year)
            ->groupBy('mes');

        $affilQuery = Affiliate::selectRaw("{$pmtMonthFunc} as mes, COUNT(*) as total")
            ->whereYear('payment_date', $year)
            ->groupBy('mes');

        if (!$user->esSuperAdmin()) {
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
            $franchises   = User::where('type', 2)->where('state', 1)->get(['id', 'name']);
            $franchiseIds = $franchises->pluck('id');

            // Una sola query agrupada por franquicia + mes en vez de 2 queries
            // por cada franquicia (evita N+1 cuando hay muchas franquicias).
            $apptTotals = Appointment::selectRaw("user_id, {$apptMonthFunc} as mes, COUNT(*) as total")
                ->whereYear('date', $year)
                ->whereIn('user_id', $franchiseIds)
                ->groupBy('user_id', 'mes')
                ->get()
                ->groupBy('user_id');

            $affilTotals = Affiliate::selectRaw("user_id, {$pmtMonthFunc} as mes, COUNT(*) as total")
                ->whereYear('payment_date', $year)
                ->whereIn('user_id', $franchiseIds)
                ->groupBy('user_id', 'mes')
                ->get()
                ->groupBy('user_id');

            $mesesPorFranquicia = function ($totalsPorUsuario, $franchiseId) {
                $months = array_fill(0, 12, 0);
                foreach ($totalsPorUsuario->get($franchiseId, []) as $row) {
                    $months[$row->mes - 1] = (int) $row->total;
                }
                return $months;
            };

            $data['by_franchise'] = [
                'users' => $franchises->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
                'appointments_by_franchise' => $franchises
                    ->map(fn ($f) => $mesesPorFranquicia($apptTotals, $f->id))
                    ->values(),
                'affiliates_by_franchise' => $franchises
                    ->map(fn ($f) => $mesesPorFranquicia($affilTotals, $f->id))
                    ->values(),
            ];
        }

        return response()->json([
            'message' => 'Datos de gráficas',
            'data'    => $data,
        ], 200);
    }
}
