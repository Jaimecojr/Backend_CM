<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
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
        $users = User::with(['city:id,name,department_id'])->get();

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
    public function store(StoreUserRequest $request)
    {
        $datos = $request->validated();

        $user = User::create([
            'nit' => $datos['nit'],
            'name' => $datos['name'],
            'contact' => $datos['contact'] ?? null,
            'phone' => $datos['phone'] ?? null,
            'movil' => $datos['movil'] ?? null,
            'address' => $datos['address'] ?? null,
            'date_afi' => $datos['date_afi'] ?? null,
            'email' => $datos['email'],
            'user' => $datos['user'],
            'password' => Hash::make($datos['password']),
            'state' => $datos['state'] ?? 1,
            'city_id' => $datos['city_id'],
            'type' => $datos['type'] ?? 2,
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
        $user = User::with(['city:id,name,department_id'])->find($id);

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
    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // Se usa $request->filled() sobre el propio FormRequest (extiende Request)
        // en vez de $request->validated() para preservar exactamente la semántica
        // original: solo se asigna un campo si viene "lleno" (no null, no ''), no
        // basta con que la clave exista en el array validado.
        if ($request->filled('nit'))
            $user->nit = $request->nit;
        if ($request->filled('name'))
            $user->name = $request->name;
        if ($request->filled('contact'))
            $user->contact = $request->contact;
        if ($request->filled('phone'))
            $user->phone = $request->phone;
        if ($request->filled('movil'))
            $user->movil = $request->movil;
        if ($request->filled('address'))
            $user->address = $request->address;
        if ($request->filled('date_afi'))
            $user->date_afi = $request->date_afi;
        if ($request->filled('email'))
            $user->email = $request->email;
        if ($request->filled('user'))
            $user->user = $request->user;
        if ($request->filled('password'))
            $user->password = Hash::make($request->password);
        if ($request->filled('state'))
            $user->state = $request->state;
        if ($request->filled('city_id'))
            $user->city_id = $request->city_id;
        if ($request->filled('type'))
            $user->type = $request->type;

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

    /**
     * Cambiar la contraseña del usuario autenticado
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => ['current_password' => ['La contraseña actual es incorrecta.']],
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente',
        ], 200);
    }

    public function activeFranchises()    {
        $users = User::where('state', 1)
            ->where('id', '>', 2)
            ->whereNot('type', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Franquicias activas obtenidas correctamente',
            'data' => $users,
        ], 200);
    }

    /**
     * Franquicias activas para el sitio web público (footer).
     * Solo expone nombre, dirección y ciudad; nunca datos internos (NIT, email, teléfono).
     */
    public function publicActiveFranchises()
    {
        $franchises = User::where('state', 1)
            ->where('type', 2)
            ->with('city:id,name')
            ->select('id', 'name', 'address', 'city_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Franquicias activas obtenidas correctamente',
            'data' => $franchises,
        ], 200);
    }
}
