<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $departments = Department::all();

        if ($departments->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron departamentos',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Departamentos obtenidos correctamente',
            'data' => $departments,
        ], 200);
    }
}
