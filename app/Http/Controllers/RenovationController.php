<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\Renovation;
use Illuminate\Http\Request;

class RenovationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $renovations = Renovation::with('affiliate')->get();
        return response()->json([
            'message' => 'Renovaciones obtenidas',
            'data' => $renovations
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'affiliate_id' => 'required|exists:affiliates,id',
            'date_ini' => 'required|date',
            'date_end' => 'required|date|after_or_equal:date_ini',
            'date_payment' => 'required|date',
            'value' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        $renovation = Renovation::create($request->all());

        // Si el afiliado estaba inactivo por vencimiento, se reactiva
        Affiliate::where('id', $request->affiliate_id)
            ->where('stade', 2)
            ->update(['stade' => 1]);

        return response()->json([
            'message' => 'Renovación guardada correctamente',
            'data' => $renovation
        ], 201);
    }

    public function show($id)
    {
        $renovation = Renovation::with('affiliate')->find($id);

        if (!$renovation) {
            return response()->json(['message' => 'Renovación no encontrada'], 404);
        }

        return response()->json([
            'message' => 'Detalles de la renovación',
            'data' => $renovation
        ], 200);
    }
}
