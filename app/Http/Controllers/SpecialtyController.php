<?php

namespace App\Http\Controllers;

use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SpecialtyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $specialties = Specialty::orderBy('name', 'asc')->get();

        return response()->json([
            'message' => 'Especialidades obtenidas correctamente',
            'data' => $specialties,
        ], 200);
    }

    /**
     * Listado público para el sitio web — solo especialidades activas.
     */
    public function publicIndex()
    {
        $specialties = Specialty::where('state', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'message' => 'Especialidades obtenidas correctamente',
            'data'    => $specialties,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255|unique:specialties,name',
            'state' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        $specialty = Specialty::create([
            'name'  => $request->name,
            'state' => $request->state ?? 1,
        ]);

        return response()->json([
            'message' => 'Especialidad creada correctamente',
            'data' => $specialty,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $specialty = Specialty::find($id);

        if (!$specialty) {
            return response()->json([
                'message' => 'Especialidad no encontrada',
            ], 404);
        }

        return response()->json([
            'message' => 'Especialidad obtenida correctamente',
            'data' => $specialty,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $specialty = Specialty::find($id);

        if (!$specialty) {
            return response()->json([
                'message' => 'Especialidad no encontrada',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'  => 'nullable|string|max:255|unique:specialties,name,' . $id,
            'state' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        if ($request->filled('name')) $specialty->name = $request->name;
        if ($request->filled('state') || $request->has('state')) $specialty->state = $request->state;

        $specialty->save();

        return response()->json([
            'message' => 'Especialidad actualizada correctamente',
            'data' => $specialty,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $specialty = Specialty::find($id);

        if (!$specialty) {
            return response()->json([
                'message' => 'Especialidad no encontrada',
            ], 404);
        }

        $specialty->delete();

        return response()->json([
            'message' => 'Especialidad eliminada correctamente',
        ], 200);
    }
}
