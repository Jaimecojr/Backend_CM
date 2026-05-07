<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class AffiliateController extends Controller
{
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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'counselor_id' => 'required|exists:counselors,id',
            'contract_code' => 'nullable|string|max:100',
            'name' => 'required|string|max:100',
            'lastname' => 'required|string|max:100',
            'bithdate' => 'nullable|date',
            'id_card' => 'required|string|max:50',
            'phone' => 'nullable|string|max:50',
            'movil' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:150',
            'city_id' => 'required|exists:cities,id',
            'email' => 'nullable|email|max:100',
            'validity' => 'required|date',
            'value_sale' => 'required|integer',
            'agreement_id' => 'required|exists:agreements,id',
            'balance' => 'required|integer',
            'comission' => 'required|integer',
            'payment_commission' => 'required|in:si,no',
            'company' => 'nullable|string|max:150',
            'photo' => 'nullable|string',
            'photo_rename' => 'nullable|string',
            'validity_end' => 'required|date',
            'stade' => 'nullable|integer',
            'carnet' => 'required|in:si,no',
            'state' => 'required|integer',
            'user_id' => 'required|exists:users,id',
            'sale_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        $affiliate = Affiliate::create($request->all());

        if ($request->has('beneficiaries') && is_array($request->beneficiaries)) {
            foreach ($request->beneficiaries as $ben) {
                if (!empty($ben['name'])) {
                    $affiliate->beneficiaries()->create([
                        'name' => $ben['name'],
                        'id_card' => $ben['id_card'] ?? '',
                        'bithdate' => current(array_filter([$ben['bithdate'] ?? null])) ?: null,
                    ]);
                }
            }
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
        $affiliate = Affiliate::with(['city', 'counselor', 'agreement', 'user', 'beneficiaries'])->find($id);

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
    public function update(Request $request, $id)
    {
        $affiliate = Affiliate::find($id);

        if (!$affiliate) {
            return response()->json([
                'message' => 'Afiliado no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'counselor_id' => 'nullable|exists:counselors,id',
            'contract_code' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:100',
            'lastname' => 'nullable|string|max:100',
            'bithdate' => 'nullable|date',
            'id_card' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:50',
            'movil' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:150',
            'city_id' => 'nullable|exists:cities,id',
            'email' => 'nullable|email|max:100',
            'validity' => 'nullable|date',
            'value_sale' => 'nullable|integer',
            'agreement_id' => 'nullable|exists:agreements,id',
            'balance' => 'nullable|integer',
            'comission' => 'nullable|integer',
            'payment_commission' => 'nullable|in:si,no',
            'company' => 'nullable|string|max:150',
            'photo' => 'nullable|string',
            'photo_rename' => 'nullable|string',
            'validity_end' => 'nullable|date',
            'stade' => 'nullable|integer',
            'carnet' => 'nullable|in:si,no',
            'state' => 'nullable|integer',
            'user_id' => 'nullable|exists:users,id',
            'sale_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        // Actualizamos usando el request
        $affiliate->update($request->all());

        if ($request->has('beneficiaries') && is_array($request->beneficiaries)) {
            // Eliminar los beneficiarios que ya no estén en la lista enviada
            $idsToKeep = array_filter(array_column($request->beneficiaries, 'id'));
            $affiliate->beneficiaries()->whereNotIn('id', $idsToKeep)->delete();

            foreach ($request->beneficiaries as $ben) {
                if (!empty($ben['name'])) {
                    if (!empty($ben['id'])) {
                        $affiliate->beneficiaries()->where('id', $ben['id'])->update([
                            'name' => $ben['name'],
                            'id_card' => $ben['id_card'] ?? '',
                            'bithdate' => current(array_filter([$ben['bithdate'] ?? null])) ?: null,
                        ]);
                    } else {
                        $affiliate->beneficiaries()->create([
                            'name' => $ben['name'],
                            'id_card' => $ben['id_card'] ?? '',
                            'bithdate' => current(array_filter([$ben['bithdate'] ?? null])) ?: null,
                        ]);
                    }
                }
            }
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

        $affiliates = Affiliate::select(['id', 'name', 'lastname', 'id_card', 'validity_end', 'stade'])
            ->with(['counselor:id,name,lastname', 'agreement:id,name'])
            ->where('stade', 1)
            ->where('validity_end', $hoy)
            ->orderBy('lastname')
            ->get();

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
}
