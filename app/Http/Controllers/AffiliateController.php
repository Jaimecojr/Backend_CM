<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAffiliateRequest;
use App\Http\Requests\UpdateAffiliateRequest;
use App\Models\Affiliate;
use App\Services\BeneficiarySyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class AffiliateController extends Controller
{
    public function __construct(private BeneficiarySyncService $beneficiarySync)
    {
    }

    /**
     * Mostrar todos los afiliados
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 20), 100);
        $stade   = $request->query('stade');
        $search  = trim((string) $request->query('search', ''));

        $query = Affiliate::select([
                'id', 'name', 'lastname', 'id_card',
                'phone', 'movil', 'email',
                'state', 'stade', 'carnet',
                'city_id', 'counselor_id', 'agreement_id', 'user_id',
                'created_at',
            ])
            ->with([
                'city:id,name,department_id',
                'counselor:id,name,lastname',
                'agreement:id,name',
                'user:id,name',
            ])
            ->orderByDesc('id');

        if ($stade !== null && $stade !== 'all') {
            $query->where('stade', (int) $stade);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name',     'like', "%{$search}%")
                  ->orWhere('lastname', 'like', "%{$search}%")
                  ->orWhere('id_card',  'like', "%{$search}%");
            });
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'message' => 'Afiliados obtenidos correctamente',
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ], 200);
    }

    /**
     * Crear un nuevo afiliado
     */
    public function store(StoreAffiliateRequest $request)
    {
        $affiliate = Affiliate::create($request->validated());

        if ($request->has('beneficiaries') && is_array($request->beneficiaries)) {
            $this->beneficiarySync->sync($affiliate, $request->beneficiaries);
        }

        return response()->json([
            'message' => 'Afiliado creado correctamente',
            'data' => $affiliate,
        ], 201);
    }

    /**
     * Mostrar un afiliado específico
     */
    public function show($id)
    {
        $affiliate = Affiliate::with([
            'city', 'counselor', 'agreement', 'user', 'beneficiaries'
        ])->find($id);

        if (!$affiliate) {
            return response()->json([
                'message' => 'Afiliado no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Afiliado obtenido correctamente',
            'data' => $affiliate,
        ], 200);
    }

    /**
     * Actualizar un afiliado existente
     */
    public function update(UpdateAffiliateRequest $request, $id)
    {
        $affiliate = Affiliate::find($id);

        if (!$affiliate) {
            return response()->json([
                'message' => 'Afiliado no encontrado',
            ], 404);
        }

        // `validity` es inmutable (ver CLAUDE.md): se valida su formato si se
        // envía, pero nunca se persiste en una actualización.
        $camposExcluidos = ['validity'];

        // Solo el super admin puede cambiar `stade` manualmente — el flujo normal
        // es que el cron lo inactive al vencer y la renovación lo reactive. Ver
        // regla de negocio en CLAUDE.md ("Regla de acceso para cambio manual de stade").
        // El flujo de renovación (usado también por franquicias, type=2) envía
        // `stade = 1` junto con otros campos como parte de la misma petición: en
        // vez de rechazar toda la actualización con 403, se ignora silenciosamente
        // el campo `stade` para quien no es super admin y se persiste el resto.
        if (!$request->user()->esSuperAdmin()) {
            $camposExcluidos[] = 'stade';
        }

        $affiliate->update(Arr::except($request->validated(), $camposExcluidos));

        if ($request->has('beneficiaries') && is_array($request->beneficiaries)) {
            $this->beneficiarySync->sync($affiliate, $request->beneficiaries);
        }

        return response()->json([
            'message' => 'Afiliado actualizado correctamente',
            'data' => $affiliate,
        ], 200);
    }

    /**
     * Eliminar un afiliado
     */
    public function destroy($id)
    {
        $affiliate = Affiliate::find($id);

        if (!$affiliate) {
            return response()->json([
                'message' => 'Afiliado no encontrado',
            ], 404);
        }

        $affiliate->delete();

        return response()->json([
            'message' => 'Afiliado eliminado correctamente',
        ], 200);
    }
    /**
     * Afiliados cuya vigencia vence hoy
     */
    public function expiringToday()
    {
        $hoy = Carbon::today()->toDateString();

        $query = Affiliate::select(['id', 'name', 'lastname', 'id_card', 'movil', 'phone', 'validity_end', 'stade'])
            ->with(['counselor:id,name,lastname', 'agreement:id,name'])
            ->activosVencenHoy();

        if (!auth()->user()->esSuperAdmin()) {
            $query->where('user_id', auth()->id());
        }

        $affiliates = $query->orderBy('lastname')->get();

        return response()->json([
            'message' => 'Afiliados que vencen hoy',
            'data'    => $affiliates,
            'date'    => $hoy,
        ], 200);
    }

    public function checkIdCard(Request $request)
    {
        $idCard = preg_replace('/\D/', '', (string) $request->query('id_card', ''));
        $ignoreId = $request->query('ignore_id');

        if ($idCard === '') {
            return response()->json([
                'exists' => false,
                'message' => 'Documento de identidad vacío',
            ], 200);
        }

        $q = Affiliate::query()->where('id_card', $idCard);

        if ($ignoreId) {
            $q->where('id', '!=', (int) $ignoreId);
        }

        $exists = $q->exists();

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'El documento de identidad ya existe' : 'Disponible',
        ], 200);
    }

    /**
     * Busca un afiliado por cédula y valida que esté vigente para crear una cita.
     * Retorna el afiliado con sus beneficiarios si está activo y no vencido.
     */
    public function byIdCard(Request $request)
    {
        $idCard = trim((string) $request->query('id_card', ''));

        if ($idCard === '') {
            return response()->json(['message' => 'Ingresa un número de documento.'], 422);
        }

        $affiliate = Affiliate::select([
                'id', 'name', 'lastname', 'id_card',
                'movil', 'phone', 'stade', 'validity_end',
            ])
            ->with(['beneficiaries:id,affiliate_id,name,id_card'])
            ->where('id_card', $idCard)
            ->first();

        if (!$affiliate) {
            return response()->json(['message' => 'No se encontró un afiliado con ese documento.'], 404);
        }

        if ((int) $affiliate->stade !== 1) {
            return response()->json(['message' => 'El afiliado se encuentra inactivo. Debe estar activo para crear una cita.'], 422);
        }

        if ($affiliate->validity_end && Carbon::parse($affiliate->validity_end)->lt(Carbon::today())) {
            $fecha = Carbon::parse($affiliate->validity_end)->format('d/m/Y');
            return response()->json([
                'message' => "La vigencia del afiliado venció el {$fecha}. Debe renovar antes de crear una cita.",
            ], 422);
        }

        return response()->json([
            'message' => 'Afiliado encontrado.',
            'data'    => $affiliate,
        ], 200);
    }

    /**
     * Consulta pública de estado de un afiliado y su grupo familiar por cédula.
     * A diferencia de byIdCard() (uso interno para crear citas), no bloquea
     * afiliados inactivos o vencidos: siempre retorna los datos si el
     * registro existe, para que el sitio público muestre el aviso de estado.
     */
    public function publicStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => 'required|string|regex:/^[0-9]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ingresa un número de documento válido.',
            ], 422);
        }

        $affiliate = Affiliate::select(['id', 'name', 'lastname', 'id_card', 'stade', 'validity_end'])
            ->with(['beneficiaries:id,affiliate_id,name'])
            ->where('id_card', $request->input('document_number'))
            ->orderByDesc('validity_end')
            ->orderByDesc('id')
            ->first();

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'No encontramos un grupo familiar con esa cédula.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Afiliado encontrado.',
            'data'    => $affiliate,
        ], 200);
    }
}
