<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DoctorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        $search = $request->query('search', '');
        $state = $request->query('state', '');

        $query = Doctor::with([
            'specialty:id,name',
            'city:id,name,department_id'
        ]);

        if ($request->has('state') && in_array($request->state, [1, 2])) {
            $query->where('state', $request->state);
        }

        if ($request->filled('specialty_id')) {
            $query->where('specialty_id', $request->specialty_id);
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        } elseif ($request->filled('department_id')) {
            $query->whereHas('city', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('lastname', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('name', 'asc')->orderBy('lastname', 'asc');

        $paginated = $query->paginate($perPage);

        return response()->json([
            'message' => 'Médicos obtenidos correctamente',
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]
        ], 200);
    }

    /**
     * Listado público para el sitio web — solo médicos activos, sin datos internos.
     */
    public function publicIndex(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 12), 50);

        $query = Doctor::with([
            'specialty:id,name',
            'city:id,name',
        ])->where('state', 1);

        if ($request->filled('specialty_id')) {
            $query->where('specialty_id', $request->specialty_id);
        }

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        } elseif ($request->filled('department_id')) {
            $query->whereHas('city', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('lastname', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('name')->orderBy('lastname');

        $paginated = $query->paginate($perPage);

        $data = collect($paginated->items())->map(fn ($d) => [
            'id'        => $d->id,
            'name'      => $d->name,
            'lastname'  => $d->lastname,
            'phone'     => $d->phone,
            'movil'     => $d->movil,
            'address'   => $d->address,
            'specialty' => $d->specialty ? ['id' => $d->specialty->id, 'name' => $d->specialty->name] : null,
            'city'      => $d->city      ? ['id' => $d->city->id,      'name' => $d->city->name]      : null,
        ]);

        return response()->json([
            'message' => 'Médicos obtenidos correctamente',
            'data'    => $data,
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'lastname'        => 'required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'specialty_id'    => 'required|exists:specialties,id',
            'city_id'         => 'required|exists:cities,id',
            'phone'           => 'required|string|max:255',
            'movil'           => 'required|digits:10',
            'address'         => 'required|string|max:255',
            'secretary_name'  => 'required|string|max:255',
            'value_agreement' => 'required|numeric|min:10000',
            'state'           => 'nullable|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        $doctor = Doctor::create([
            'name'            => $request->name,
            'lastname'        => $request->lastname,
            'email'           => $request->email,
            'specialty_id'    => $request->specialty_id,
            'city_id'         => $request->city_id,
            'phone'           => $request->phone,
            'movil'           => $request->movil,
            'address'         => $request->address,
            'secretary_name'  => $request->secretary_name,
            'value_agreement' => $request->value_agreement ?? 0,
            'state'           => $request->state ?? 1,
        ]);

        return response()->json([
            'message' => 'Médico creado correctamente',
            'data' => $doctor,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $doctor = Doctor::with([
            'specialty:id,name',
            'city:id,name,department_id'
        ])->find($id);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Médico obtenido correctamente',
            'data' => $doctor,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'            => 'nullable|string|max:255',
            'lastname'        => 'nullable|string|max:255',
            'email'           => 'nullable|email|max:255',
            'specialty_id'    => 'nullable|exists:specialties,id',
            'city_id'         => 'nullable|exists:cities,id',
            'phone'           => 'nullable|string|max:255',
            'movil'           => 'nullable|digits:10',
            'address'         => 'nullable|string|max:255',
            'secretary_name'  => 'nullable|string|max:255',
            'value_agreement' => 'nullable|numeric|min:10000',
            'state'           => 'nullable|in:1,2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        if ($request->filled('name')) $doctor->name = $request->name;
        if ($request->filled('lastname')) $doctor->lastname = $request->lastname;
        if ($request->has('email')) $doctor->email = $request->email;
        if ($request->filled('specialty_id')) $doctor->specialty_id = $request->specialty_id;
        if ($request->filled('city_id')) $doctor->city_id = $request->city_id;
        
        if ($request->has('phone')) $doctor->phone = $request->phone;
        if ($request->has('movil')) $doctor->movil = $request->movil;
        if ($request->has('address')) $doctor->address = $request->address;
        if ($request->has('secretary_name')) $doctor->secretary_name = $request->secretary_name;
        
        if ($request->filled('value_agreement')) $doctor->value_agreement = $request->value_agreement;
        if ($request->filled('state')) $doctor->state = $request->state;

        $doctor->save();

        return response()->json([
            'message' => 'Médico actualizado correctamente',
            'data' => $doctor,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $doctor = Doctor::find($id);

        if (!$doctor) {
            return response()->json([
                'message' => 'Médico no encontrado',
            ], 404);
        }

        $doctor->delete();

        return response()->json([
            'message' => 'Médico eliminado correctamente',
        ], 200);
    }
}
