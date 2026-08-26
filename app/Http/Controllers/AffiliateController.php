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
     * List all affiliates
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
     * Create a new affiliate
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
     * Show a specific affiliate
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
     * Update an existing affiliate
     */
    public function update(UpdateAffiliateRequest $request, $id)
    {
        $affiliate = Affiliate::find($id);

        if (!$affiliate) {
            return response()->json([
                'message' => 'Afiliado no encontrado',
            ], 404);
        }

        // `validity` is immutable: its format is validated if sent, but it's
        // never persisted on an update.
        $excludedFields = ['validity'];

        // Only a super admin can change `stade` manually — the normal flow is
        // that a cron deactivates it on expiry and a renewal reactivates it.
        // The renewal flow (also used by franchises, type=2) sends `stade = 1`
        // together with other fields as part of the same request: instead of
        // rejecting the whole update with 403, `stade` is silently dropped for
        // non-super-admins and the rest of the fields are still persisted.
        if (!$request->user()->isSuperAdmin()) {
            $excludedFields[] = 'stade';
        }

        $affiliate->update(Arr::except($request->validated(), $excludedFields));

        if ($request->has('beneficiaries') && is_array($request->beneficiaries)) {
            $this->beneficiarySync->sync($affiliate, $request->beneficiaries);
        }

        return response()->json([
            'message' => 'Afiliado actualizado correctamente',
            'data' => $affiliate,
        ], 200);
    }

    /**
     * Delete an affiliate
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
     * Affiliates whose validity expires today
     */
    public function expiringToday()
    {
        $today = Carbon::today()->toDateString();

        $query = Affiliate::select(['id', 'name', 'lastname', 'id_card', 'movil', 'phone', 'validity_end', 'stade'])
            ->with(['counselor:id,name,lastname', 'agreement:id,name'])
            ->activeExpiringToday();

        if (!auth()->user()->isSuperAdmin()) {
            $query->where('user_id', auth()->id());
        }

        $affiliates = $query->orderBy('lastname')->get();

        return response()->json([
            'message' => 'Afiliados que vencen hoy',
            'data'    => $affiliates,
            'date'    => $today,
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
     * Looks up an affiliate by ID card and validates that it's within its
     * validity period before allowing an appointment to be created.
     * Returns the affiliate with its beneficiaries if it's active and not expired.
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
            $expiredOn = Carbon::parse($affiliate->validity_end)->format('d/m/Y');
            return response()->json([
                'message' => "La vigencia del afiliado venció el {$expiredOn}. Debe renovar antes de crear una cita.",
            ], 422);
        }

        return response()->json([
            'message' => 'Afiliado encontrado.',
            'data'    => $affiliate,
        ], 200);
    }

    /**
     * Public lookup of an affiliate's status and family group by ID card.
     * Unlike byIdCard() (internal use for creating appointments), it doesn't
     * block inactive or expired affiliates: it always returns the data if the
     * record exists, so the public site can show the status notice.
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
