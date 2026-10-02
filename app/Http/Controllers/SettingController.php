<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $setting = Setting::first();

        if (!$setting) {
            return response()->json(['message' => 'Configuración no encontrada.'], 404);
        }

        return response()->json([
            'message' => 'Configuración obtenida exitosamente.',
            'data'    => $setting,
        ]);
    }

    /**
     * SECURITY_REVIEW: the Meta token is write-only — never returned (Setting::$hidden), stored
     * encrypted, and an empty value keeps the current one so the panel never has to hold it.
     */
    public function update(Request $request, Setting $setting)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'wa_api_version'               => 'required|string|max:255',
            'wa_phone_number_id'           => 'required|string|max:255',
            // Write-only: the panel never receives the current token, so an empty value means
            // "keep the one already stored". It's only required when none is stored yet.
            'wa_bearer_token'              => [filled($setting->getRawOriginal('wa_bearer_token')) ? 'nullable' : 'required', 'string'],
            'wa_template_name'             => 'required|string|max:255',
            'wa_appointment_template_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (blank($data['wa_bearer_token'] ?? null)) {
            unset($data['wa_bearer_token']);
        }

        $setting->update($data);

        return response()->json([
            'message' => 'Configuración actualizada exitosamente.',
            'data'    => $setting,
        ]);
    }
}
