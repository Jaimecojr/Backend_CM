<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCounselorRequest;
use App\Models\Counselor;
use App\Support\IdCardLookup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CounselorController extends Controller
{
    // Public and static so UpdateCounselorRequest can reuse the same list
    // instead of duplicating the 4 valid values for `type_contra`.
    public static function typeContraValues(): array
    {
        return [
            'Término Fijo',
            'Término Indefinido',
            'Corretaje',
            'Con Garantizado',
        ];
    }


    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $counselors = Counselor::with([
            'city:id,name,department_id',
            'user:id,name',
        ])->orderBy('name', 'asc')->orderBy('lastname', 'asc')->get();

        if ($counselors->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron vendedores',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Vendedores obtenidos correctamente',
            'data' => $counselors,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'           => 'required|string|max:255',
            'lastname'       => 'required|string|max:255',
            'id_card'        => 'required|regex:/^\d+$/|max:100|unique:counselors,id_card',
            'address'        => 'nullable|string|max:255',
            'date_admission' => 'nullable|date',

            'type_contra'    => 'required|in:' . implode(',', $this->typeContraValues()),

            'email'          => 'nullable|email|max:255|unique:counselors,email',
            'password'       => 'nullable|string|min:6',

            'rol'            => 'required|numeric',
            'phone'          => 'nullable|string|max:255',
            'movil'          => 'nullable|digits:10',

            'state'          => 'nullable|in:1,2',

            'city_id'        => 'required|exists:cities,id',
            'user_id'        => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        $counselor = Counselor::create([
            'name'           => $request->name,
            'lastname'       => $request->lastname,
            'id_card'        => $request->id_card,
            'address'        => $request->address,
            'date_admission' => $request->date_admission,
            'type_contra'    => $request->type_contra,

            'email'          => $request->email,
            'password'       => $request->password ? Hash::make($request->password) : null,

            'rol'            => $request->rol,
            'phone'          => $request->phone,
            'movil'          => $request->movil,

            'state'          => $request->state ?? 1,

            'city_id'        => $request->city_id,
            'user_id'        => $request->user_id,
        ]);

        return response()->json([
            'message' => 'Vendedor creado correctamente',
            'data' => $counselor,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $counselor = Counselor::with([
            'city:id,name,department_id',
            'user:id,name',
        ])->find($id);

        if (!$counselor) {
            return response()->json([
                'message' => 'Vendedor no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Vendedor obtenido correctamente',
            'data' => $counselor,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCounselorRequest $request, $id)
    {
        $counselor = Counselor::find($id);

        if (!$counselor) {
            return response()->json([
                'message' => 'Vendedor no encontrado',
            ], 404);
        }

        $data = $request->validated();

        // Counselor has no 'hashed' cast (unlike User), so password must be
        // hashed explicitly here — persisting validated() as-is would store
        // the plaintext value. If it wasn't sent (or was sent as null), it's
        // dropped so the existing hash on the row is left untouched.
        if (array_key_exists('password', $data) && $data['password'] !== null) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $counselor->update($data);

        return response()->json([
            'message' => 'Vendedor actualizado correctamente',
            'data' => $counselor,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $counselor = Counselor::find($id);

        if (!$counselor) {
            return response()->json([
                'message' => 'Vendedor no encontrado',
            ], 404);
        }

        $counselor->delete();

        return response()->json([
            'message' => 'Vendedor eliminado correctamente',
        ], 200);
    }

    public function checkIdCard(Request $request)
    {
        $idCard = IdCardLookup::normalize((string) $request->query('id_card', ''));
        $ignoreId = $request->query('ignore_id'); // optional (for editing)

        if ($idCard === '') {
            return response()->json([
                'exists' => false,
                'message' => 'Cédula vacía',
            ], 200);
        }

        $exists = IdCardLookup::exists(Counselor::class, $idCard, $ignoreId ? (int) $ignoreId : null);

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'La cédula ya existe' : 'Disponible',
        ], 200);
    }
    public function activeCounselors()
    {
        $counselors = Counselor::where('state', 1)
            ->select('id', 'name', 'lastname')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Vendedores activos obtenidos correctamente',
            'data' => $counselors,
        ], 200);
    }
}
