<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\Appointment;
use App\Models\User;
use App\Reports\Concerns\AppliesFranchiseScope;
use Illuminate\Database\Eloquent\Builder;

class AppointmentsReport
{
    use AppliesFranchiseScope;

    /**
     * Resolves the patient's display name in a single query via a double
     * leftJoin (affiliates for type=1, beneficiaries for type=2) instead of
     * two round trips per row — the conditional ON clauses ensure only one
     * of the two joins ever matches for a given appointment.
     *
     * The affiliate's name+lastname are selected as separate columns
     * (aff_name/aff_lastname) rather than concatenated in SQL: SQLite (used
     * in tests) has no CONCAT() function and MySQL doesn't support the `||`
     * operator by default, so composing the full name is left to PHP (see
     * self::patientName()) to stay portable across both engines.
     */
    public function query(array $filters, User $authUser): Builder
    {
        $query = Appointment::query()
            ->select('appointments.*')
            ->selectRaw('aff.name as aff_name, aff.lastname as aff_lastname, ben.name as ben_name')
            ->with(['doctor:id,name,lastname,city_id', 'doctor.city:id,name'])
            ->leftJoin('affiliates as aff', function ($join) {
                $join->on('aff.id', '=', 'appointments.afi_code')->where('appointments.type', 1);
            })
            ->leftJoin('beneficiaries as ben', function ($join) {
                $join->on('ben.id', '=', 'appointments.afi_code')->where('appointments.type', 2);
            });

        $this->scopeFranchise($query, 'appointments.user_id', $authUser);

        if ($authUser->isSuperAdmin() && !empty($filters['franchise_id'])) {
            $query->where('appointments.user_id', $filters['franchise_id']);
        }
        if (!empty($filters['from'])) {
            $query->where('appointments.date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('appointments.date', '<=', $filters['to']);
        }
        if (!empty($filters['doctor_id'])) {
            $query->where('appointments.doctor_id', $filters['doctor_id']);
        }

        return $query->orderByDesc('appointments.date');
    }

    /**
     * Composes the patient's full display name from the raw columns
     * selected by query() — shared by ReportController::appointments() and
     * AppointmentsReportExport so the "(Titular)"/"(Beneficiario)" suffix
     * logic lives in exactly one place.
     */
    public static function patientName(object $appointment): string
    {
        $isTitular = $appointment->type === 1;

        $name = $isTitular
            ? trim("{$appointment->aff_name} {$appointment->aff_lastname}")
            : (string) $appointment->ben_name;

        $suffix = $isTitular ? ' (Titular)' : ' (Beneficiario)';

        return $name . $suffix;
    }
}
