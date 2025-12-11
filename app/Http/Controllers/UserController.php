<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Mostrar todos los usuarios
     */
    public function index()
    {
        $users = User::with('city')->get();

        if ($users->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron usuarios',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Usuarios obtenidos correctamente',
            'data' => $users,
        ], 200);
    }

    /**
     * Crear un nuevo usuario
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nit' => 'required|string|max:100|unique:users,nit',
            'name' => 'required|string|max:100',
            'contact' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'movil' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:150',
            'date_afi' => 'nullable|date',
            'email' => 'required|email|unique:users,email',
            'user' => 'required|string|max:100|unique:users,user',
            'password' => 'required|string|min:6',
            'state' => 'nullable|boolean',
            'city_id' => 'required|exists:cities,id',
            'type' => 'required|in:1,2,3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        $user = User::create([
            'nit' => $request->nit,
            'name' => $request->name,
            'contact' => $request->contact,
            'phone' => $request->phone,
            'movil' => $request->movil,
            'address' => $request->address,
            'date_afi' => $request->date_afi,
            'email' => $request->email,
            'user' => $request->user,
            'password' => Hash::make($request->password),
            'state' => $request->state ?? true,
            'city_id' => $request->city_id,
            'type' => $request->type,
        ]);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'data' => $user,
        ], 201);
    }

    /**
     * Mostrar un usuario específico
     */
    public function show($id)
    {
        $user = User::with('city')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        return response()->json([
            'message' => 'Usuario obtenido correctamente',
            'data' => $user,
        ], 200);
    }

    /**
     * Actualizar un usuario existente
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nit' => 'nullable|string|max:100|unique:users,nit,' . $id,
            'name' => 'nullable|string|max:100',
            'contact' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'movil' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:150',
            'date_afi' => 'nullable|date',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'user' => 'nullable|string|max:100|unique:users,user,' . $id,
            'password' => 'nullable|string|min:6',
            'state' => 'nullable|boolean',
            'city_id' => 'nullable|exists:cities,id',
            'type' => 'nullable|in:1,2,3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors' => $validator->errors(),
            ], 400);
        }

        if ($request->filled('nit')) $user->nit = $request->nit;
        if ($request->filled('name')) $user->name = $request->name;
        if ($request->filled('contact')) $user->contact = $request->contact;
        if ($request->filled('phone')) $user->phone = $request->phone;
        if ($request->filled('movil')) $user->movil = $request->movil;
        if ($request->filled('address')) $user->address = $request->address;
        if ($request->filled('date_afi')) $user->date_afi = $request->date_afi;
        if ($request->filled('email')) $user->email = $request->email;
        if ($request->filled('user')) $user->user = $request->user;
        if ($request->filled('password')) $user->password = Hash::make($request->password);
        if ($request->filled('state')) $user->state = $request->state;
        if ($request->filled('city_id')) $user->city_id = $request->city_id;
        if ($request->filled('type')) $user->type = $request->type;

        $user->save();

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'data' => $user,
        ], 200);
    }

    /**
     * Eliminar un usuario
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente',
        ], 200);
    }
}
