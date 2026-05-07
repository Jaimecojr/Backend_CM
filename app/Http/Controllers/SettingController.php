<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    public function index()
    {
        $setting = Setting::first();

        if (!$setting) {
            return response()->json(['message' => 'Configuración no encontrada.'], 404);
        }

        return response()->json([
            'message' => 'Configuración obtenida exitosamente.',
            'data'    => $setting,
        ]);
    }

    public function update(Request $request, Setting $setting)
    {
        $validator = Validator::make($request->all(), [
            'wa_api_version'     => 'required|string|max:255',
            'wa_phone_number_id' => 'required|string|max:255',
            'wa_bearer_token'    => 'required|string|max:255',
            'wa_template_name'   => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $setting->update($validator->validated());

        return response()->json([
            'message' => 'Configuración actualizada exitosamente.',
            'data'    => $setting,
        ]);
    }
}
