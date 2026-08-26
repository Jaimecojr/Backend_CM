<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Services\WhatsAppClient;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(private WhatsAppClient $whatsapp)
    {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);
        $search  = trim($request->get('search', ''));
        $date    = trim($request->get('date', ''));
        $period  = trim($request->get('period', 'pending'));

        $query = Appointment::with([
            'doctor:id,name,lastname',
            'city:id,name',
            'affiliate:id,name,lastname',
            'beneficiary:id,name',
        ])->select('appointments.*');

        // Solo el super admin (type = 1) ve todas las citas; los demás solo las suyas
        if (!auth()->user()->esSuperAdmin()) {
            $query->where('appointments.user_id', auth()->id());
        }

        // Filtro por fecha exacta o por período (pendientes / pasadas)
        if ($date) {
            $query->whereDate('appointments.date', $date);
            $query->orderBy('appointments.date', 'asc')->orderBy('appointments.hour', 'asc');
        } elseif ($period === 'pending') {
            $query->whereDate('appointments.date', '>=', now()->toDateString());
            $query->orderBy('appointments.date', 'asc')->orderBy('appointments.hour', 'asc');
        } elseif ($period === 'past') {
            $query->whereDate('appointments.date', '<', now()->toDateString());
            $query->orderBy('appointments.date', 'desc')->orderBy('appointments.hour', 'desc');
        } else {
            $query->orderBy('appointments.date', 'desc')->orderBy('appointments.hour', 'desc');
        }

        if ($search) {
            $query->leftJoin('doctors as srch_doc', 'srch_doc.id', '=', 'appointments.doctor_id')
                  ->where(function ($q) use ($search) {
                      $q->where('appointments.name', 'like', "%{$search}%")
                        ->orWhere('srch_doc.name', 'like', "%{$search}%")
                        ->orWhere('srch_doc.lastname', 'like', "%{$search}%");
                  });
        }

        $paginated = $query->paginate($perPage);

        // `owner` es un campo calculado, nunca persistido: `affiliate` si type=1,
        // `beneficiary` si type=2. Se recalcula en cada index()/show() porque el
        // significado de `afi_code` cambia según `type` (ver CLAUDE.md).
        $items = collect($paginated->items())->map(function ($appt) {
            $arr          = $appt->toArray();
            $arr['owner'] = $appt->type === 1 ? $appt->affiliate : $appt->beneficiary;
            unset($arr['affiliate'], $arr['beneficiary']);
            return $arr;
        })->values()->toArray();

        return response()->json([
            'message' => 'Citas obtenidas correctamente.',
            'data'    => $items,
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function store(StoreAppointmentRequest $request)
    {
        $appointment = Appointment::create($request->validated());

        $whatsapp = $this->enviarNotificacionWA($appointment);

        return response()->json([
            'message'   => 'Cita creada correctamente.',
            'data'      => $appointment,
            'whatsapp'  => $whatsapp,
        ], 201);
    }

    public function show(Appointment $appointment)
    {
        $appointment->load([
            'doctor:id,name,lastname,specialty_id',
            'city:id,name',
            'user:id,name',
            'affiliate:id,name,lastname,id_card',
            'beneficiary:id,name,id_card,affiliate_id',
        ]);

        $data             = $appointment->toArray();
        $data['owner']    = $appointment->type === 1 ? $appointment->affiliate : $appointment->beneficiary;
        $data['affiliate_id'] = $appointment->type === 1
            ? $appointment->afi_code
            : $appointment->beneficiary?->affiliate_id;
        unset($data['affiliate'], $data['beneficiary']);

        return response()->json([
            'message' => 'Cita obtenida correctamente.',
            'data'    => $data,
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        $appointment->update($request->validated());

        $whatsapp = $this->enviarNotificacionWA($appointment);

        return response()->json([
            'message'  => 'Cita actualizada correctamente.',
            'data'     => $appointment,
            'whatsapp' => $whatsapp,
        ]);
    }

    public function destroy(Appointment $appointment)
    {
        $appointment->delete();

        return response()->json([
            'message' => 'Cita eliminada correctamente.',
        ]);
    }

    public function today()
    {
        $hoy = Carbon::today()->toDateString();

        $query = Appointment::select(['id', 'name', 'hour', 'doctor_id'])
            ->with(['doctor:id,name,lastname'])
            ->where('date', $hoy)
            ->orderBy('hour');

        if (!auth()->user()->esSuperAdmin()) {
            $query->where('user_id', auth()->id());
        }

        $appointments = $query->get();

        return response()->json([
            'message' => 'Citas del día',
            'data'    => $appointments,
            'date'    => $hoy,
        ], 200);
    }

    private function enviarNotificacionWA(Appointment $appointment): array
    {
        // Validar teléfono
        $phone = preg_replace('/\D/', '', (string) $appointment->phone);
        if (strlen($phone) !== 10) {
            return ['enviado' => false, 'detalle' => 'El teléfono de la cita no es válido'];
        }

        // Validar configuración de WhatsApp
        $settings = $this->whatsapp->configuracionParaPlantilla('wa_appointment_template_name');
        if (!$settings) {
            return ['enviado' => false, 'detalle' => 'Configuración de WhatsApp incompleta'];
        }

        // Cargar relaciones para la plantilla
        $appointment->loadMissing('doctor.specialty');

        $doctor         = $appointment->doctor;
        $especialidad   = $doctor?->specialty?->name ?? 'No especificada';
        $nombreDoctor   = $doctor ? trim($doctor->name . ' ' . $doctor->lastname) : 'No asignado';
        $fecha          = \Carbon\Carbon::parse($appointment->date)->format('d/m/Y');
        $valor          = '$ ' . number_format($appointment->value, 0, ',', '.');

        $components = [
            [
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $appointment->name],
                    ['type' => 'text', 'text' => $fecha],
                    ['type' => 'text', 'text' => $appointment->hour],
                    ['type' => 'text', 'text' => $appointment->address],
                    ['type' => 'text', 'text' => $especialidad],
                    ['type' => 'text', 'text' => $nombreDoctor],
                    ['type' => 'text', 'text' => $valor],
                ],
            ],
        ];

        $resultado = $this->whatsapp->enviarPlantilla(
            $phone,
            $settings->wa_appointment_template_name,
            $components,
            'cita',
        );

        return $resultado['enviado']
            ? ['enviado' => true]
            : ['enviado' => false, 'detalle' => 'Error en la API de WhatsApp'];
    }
}
