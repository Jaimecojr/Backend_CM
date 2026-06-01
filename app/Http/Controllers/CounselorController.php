<?php

namespace App\Http\Controllers;

use App\Models\Counselor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CounselorController extends Controller
{
    private function typeContraValues(): array
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
    public function update(Request $request, $id)
    {
        $counselor = Counselor::find($id);

        if (!$counselor) {
            return response()->json([
                'message' => 'Vendedor no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'           => 'nullable|string|max:255',
            'lastname'       => 'nullable|string|max:255',
            'id_card'        => 'nullable|regex:/^\d+$/|max:100|unique:counselors,id_card,' . $id,
            'address'        => 'nullable|string|max:255',
            'date_admission' => 'nullable|date',

            'type_contra'    => 'nullable|in:' . implode(',', $this->typeContraValues()),

            // nullable + unique: solo valida unique si envían un valor
            'email'          => 'nullable|email|max:255|unique:counselors,email,' . $id,
            'password'       => 'nullable|string|min:6',

            'rol'            => 'nullable|numeric',
            'phone'          => 'nullable|string|max:255',
            'movil'          => 'nullable|digits:10',

            'state'          => 'nullable|in:1,2',

            'city_id'        => 'nullable|exists:cities,id',
            'user_id'        => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        if ($request->filled('name')) $counselor->name = $request->name;
        if ($request->filled('lastname')) $counselor->lastname = $request->lastname;
        if ($request->filled('id_card')) $counselor->id_card = $request->id_card;
        if ($request->filled('address')) $counselor->address = $request->address;
        if ($request->filled('date_admission')) $counselor->date_admission = $request->date_admission;

        if ($request->filled('type_contra')) $counselor->type_contra = $request->type_contra;

        // OJO: si quieres permitir borrar email (ponerlo null), debes manejarlo con has()
        if ($request->has('email')) {
            $counselor->email = $request->email; // puede ser null
        }

        if ($request->filled('password')) {
            $counselor->password = Hash::make($request->password);
        }

        if ($request->filled('rol')) $counselor->rol = $request->rol;
        if ($request->filled('phone')) $counselor->phone = $request->phone;
        if ($request->filled('movil')) $counselor->movil = $request->movil;

        if ($request->filled('state')) $counselor->state = $request->state;

        if ($request->filled('city_id')) $counselor->city_id = $request->city_id;
        if ($request->filled('user_id')) $counselor->user_id = $request->user_id;

        $counselor->save();

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
        $idCard = preg_replace('/\D/', '', (string) $request->query('id_card', ''));
        $ignoreId = $request->query('ignore_id'); // opcional (para editar)

        if ($idCard === '') {
            return response()->json([
                'exists' => false,
                'message' => 'Cédula vacía',
            ], 200);
        }

        $q = Counselor::query()->where('id_card', $idCard);

        if ($ignoreId) {
            $q->where('id', '!=', (int) $ignoreId);
        }

        $exists = $q->exists();

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
