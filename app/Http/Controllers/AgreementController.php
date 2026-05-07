<?php

namespace App\Http\Controllers;

use App\Models\Agreement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AgreementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $agreements = Agreement::with([
            'city:id,name,department_id',
        ])->orderBy('name', 'asc')->get();

        if ($agreements->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron convenios',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Convenios obtenidos correctamente',
            'data' => $agreements,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($request->user()->type !== 1) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'amount'  => 'required|integer|min:10000',
            'state'   => 'required|in:1,0',
            'city_id' => 'required|exists:cities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        $agreement = Agreement::create([
            'name'    => $request->name,
            'amount'  => $request->amount,
            'state'   => $request->state,
            'city_id' => $request->city_id,
        ]);

        return response()->json([
            'message' => 'Convenio creado correctamente',
            'data' => $agreement,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $agreement = Agreement::with([
            'city:id,name,department_id',
        ])->find($id);

        if (!$agreement) {
            return response()->json([
                'message' => 'Convenio no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Convenio obtenido correctamente',
            'data' => $agreement,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if ($request->user()->type !== 1) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $agreement = Agreement::find($id);

        if (!$agreement) {
            return response()->json([
                'message' => 'Convenio no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'amount'  => 'required|integer|min:10000',
            'state'   => 'required|in:1,0',
            'city_id' => 'required|exists:cities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        $agreement->name = $request->name;
        $agreement->amount = $request->amount;
        $agreement->state = $request->state;
        $agreement->city_id = $request->city_id;

        $agreement->save();

        return response()->json([
            'message' => 'Convenio actualizado correctamente',
            'data' => $agreement,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $agreement = Agreement::find($id);

        if (!$agreement) {
            return response()->json([
                'message' => 'Convenio no encontrado',
            ], 404);
        }

        $agreement->delete();

        return response()->json([
            'message' => 'Convenio eliminado correctamente',
        ], 200);
    }
    public function activeAgreements()
    {
        $agreements = Agreement::with('city.department')->where('state', 1)
            ->get()
            ->sortBy('name')
            ->values()
            ->map(function ($a) {
                $cityName = $a->city->name ?? 'Sin Ciudad';
                $deptName = $a->city->department->name ?? 'Sin Depto';
                return [
                    'id' => $a->id,
                    'amount' => $a->amount,
                    'name' => "{$a->name} - $" . number_format($a->amount, 0, ',', '.') . " - {$cityName} - {$deptName}"
                ];
            });

        return response()->json([
            'message' => 'Convenios activos obtenidos correctamente',
            'data' => $agreements,
        ], 200);
    }
}
