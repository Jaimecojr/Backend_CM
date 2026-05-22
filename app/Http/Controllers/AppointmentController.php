<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Setting;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
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
        if (auth()->user()->type !== 1) {
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

        // Normalizar owner: devuelve el afiliado o beneficiario según type
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

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'afi_code'  => 'required|integer',
            'doctor_id' => 'required|exists:doctors,id',
            'date'      => 'required|date',
            'hour'      => 'required|string|max:10',
            'address'   => 'required|string|max:255',
            'city_id'   => 'required|exists:cities,id',
            'phone'     => 'nullable|string|max:255',
            'value'     => 'required|numeric|min:10000',
            'type'      => 'required|in:1,2',
            'name'      => 'required|string|max:255',
            'user_id'   => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $appointment = Appointment::create($validator->validated());

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

    public function update(Request $request, Appointment $appointment)
    {
        $validator = Validator::make($request->all(), [
            'afi_code'  => 'required|integer',
            'doctor_id' => 'required|exists:doctors,id',
            'date'      => 'required|date',
            'hour'      => 'required|string|max:10',
            'address'   => 'required|string|max:255',
            'city_id'   => 'required|exists:cities,id',
            'phone'     => 'nullable|string|max:255',
            'value'     => 'required|numeric|min:10000',
            'type'      => 'required|in:1,2',
            'name'      => 'required|string|max:255',
            'user_id'   => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $appointment->update($validator->validated());

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

        if (auth()->user()->type !== 1) {
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
        $settings = Setting::first();
        if (
            !$settings ||
            empty($settings->wa_api_version) ||
            empty($settings->wa_phone_number_id) ||
            empty($settings->wa_bearer_token) ||
            empty($settings->wa_appointment_template_name)
        ) {
            return ['enviado' => false, 'detalle' => 'Configuración de WhatsApp incompleta'];
        }

        // Cargar relaciones para la plantilla
        $appointment->loadMissing('doctor.specialty');

        $doctor         = $appointment->doctor;
        $especialidad   = $doctor?->specialty?->name ?? 'No especificada';
        $nombreDoctor   = $doctor ? trim($doctor->name . ' ' . $doctor->lastname) : 'No asignado';
        $fecha          = \Carbon\Carbon::parse($appointment->date)->format('d/m/Y');
        $valor          = '$ ' . number_format($appointment->value, 0, ',', '.');

        $recipient = '57' . $phone;

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $recipient,
            'type'              => 'template',
            'template'          => [
                'name'       => $settings->wa_appointment_template_name,
                'language'   => ['code' => 'es_CO'],
                'components' => [
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
                ],
            ],
        ];

        $apiUrl = "https://graph.facebook.com/{$settings->wa_api_version}/{$settings->wa_phone_number_id}/messages";

        try {
            $http = Http::withToken($settings->wa_bearer_token);
            if (app()->environment('local')) {
                $http = $http->withoutVerifying();
            }
            $response     = $http->post($apiUrl, $payload);
            $responseData = $response->json();
        } catch (\Throwable $e) {
            return ['enviado' => false, 'detalle' => 'Error al contactar la API de WhatsApp'];
        }

        WhatsappMessage::create([
            'response'     => json_encode($responseData),
            'recipient_id' => $recipient,
            'deleted'      => 0,
            'type'         => 'cita',
        ]);

        if (!empty($responseData['messages'][0]['id'])) {
            return ['enviado' => true];
        }

        return ['enviado' => false, 'detalle' => 'Error en la API de WhatsApp'];
    }
}
