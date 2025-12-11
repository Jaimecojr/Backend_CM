<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    public function index()
    {
        $admins = Admin::with('city')->get();

        if ($admins->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron administradores',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Administradores obtenidos correctamente',
            'data' => $admins,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'login' => 'required|string|max:255|unique:admins,login',
            'pass' => 'required|string|min:6',
            'type' => 'required|string',
            'city_id' => 'required|exists:cities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        $admin = Admin::create([
            'name' => $request->name,
            'lastname' => $request->lastname,
            'phone' => $request->phone,
            'login' => $request->login,
            'pass' => Hash::make($request->pass), // ✅ Contraseña segura
            'type' => $request->type,
            'city_id' => $request->city_id,
        ]);

        return response()->json([
            'message' => 'Administrador creado correctamente',
            'data' => $admin,
        ], 201);
    }

    public function show($id)
    {
        $admin = Admin::with('city')->find($id);

        if (!$admin) {
            return response()->json([
                'message' => 'Administrador no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Administrador obtenido correctamente',
            'data' => $admin,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'message' => 'Administrador no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'login' => 'required|string|max:255|unique:admins,login,' . $id,
            'pass' => 'required|string|min:6',
            'type' => 'required|string',
            'city_id' => 'required|exists:cities,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        if ($request->filled('name')) $admin->name = $request->name;
        if ($request->filled('lastname')) $admin->lastname = $request->lastname;
        if ($request->filled('phone')) $admin->phone = $request->phone;
        if ($request->filled('login')) $admin->login = $request->login;
        if ($request->filled('pass')) $admin->pass = Hash::make($request->pass); // ✅ Reencripta
        if ($request->filled('type')) $admin->type = $request->type;
        if ($request->filled('city_id')) $admin->city_id = $request->city_id;

        $admin->save();

        return response()->json([
            'message' => 'Administrador actualizado correctamente',
            'data' => $admin,
        ], 200);
    }

    public function destroy($id)
    {
        $admin = Admin::find($id);

        if (!$admin) {
            return response()->json([
                'message' => 'Administrador no encontrado',
            ], 404);
        }

        $admin->delete();

        return response()->json([
            'message' => 'Administrador eliminado correctamente',
        ], 200);
    }
}
