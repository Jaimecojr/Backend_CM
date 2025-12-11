<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CityController extends Controller
{
    /**
     * Devuelve todas las ciudades de un departamento específico
     */
    public function getByDepartment(Department $department): JsonResponse
    {
        // Carga las ciudades asociadas
        $cities = $department->cities()->get(['id', 'name', 'department_id']);

        if ($cities->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron ciudades para este departamento',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Ciudades obtenidas correctamente',
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
            ],
            'data' => $cities,
        ], 200);
    }
}
