<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BeneficiaryController extends Controller
{
    /**
     * Mostrar todos los beneficiarios
     */
    public function index()
    {
        $beneficiaries = Beneficiary::with('affiliate')->get();

        if ($beneficiaries->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron beneficiarios',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Beneficiarios obtenidos correctamente',
            'data' => $beneficiaries,
        ], 200);
    }

    /**
     * Crear un nuevo beneficiario
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'affiliate_id' => 'required|exists:affiliates,id',
            'name' => 'required|string|max:255',
            'id_card' => 'required|string|max:50',
            'bithdate' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        $beneficiary = Beneficiary::create($request->all());

        return response()->json([
            'message' => 'Beneficiario creado correctamente',
            'data' => $beneficiary,
        ], 201);
    }

    /**
     * Mostrar un beneficiario específico
     */
    public function show($id)
    {
        $beneficiary = Beneficiary::with('affiliate')->find($id);

        if (!$beneficiary) {
            return response()->json([
                'message' => 'Beneficiario no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Beneficiario obtenido correctamente',
            'data' => $beneficiary,
        ], 200);
    }

    /**
     * Actualizar un beneficiario existente
     */
    public function update(Request $request, $id)
    {
        $beneficiary = Beneficiary::find($id);

        if (!$beneficiary) {
            return response()->json([
                'message' => 'Beneficiario no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'affiliate_id' => 'nullable|exists:affiliates,id',
            'name' => 'nullable|string|max:255',
            'id_card' => 'nullable|string|max:50',
            'bithdate' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        $beneficiary->update($request->all());

        return response()->json([
            'message' => 'Beneficiario actualizado correctamente',
            'data' => $beneficiary,
        ], 200);
    }

    /**
     * Eliminar un beneficiario
     */
    public function destroy($id)
    {
        $beneficiary = Beneficiary::find($id);

        if (!$beneficiary) {
            return response()->json([
                'message' => 'Beneficiario no encontrado',
            ], 404);
        }

        $beneficiary->delete();

        return response()->json([
            'message' => 'Beneficiario eliminado correctamente',
        ], 200);
    }
}
