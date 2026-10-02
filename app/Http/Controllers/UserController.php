<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\RegistActionLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(private RegistActionLogger $registActionLogger)
    {
    }

    /**
     * Display all users
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
     * Create a new user
     */
    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'nit' => $data['nit'],
            'name' => $data['name'],
            'contact' => $data['contact'] ?? null,
            'phone' => $data['phone'] ?? null,
            'movil' => $data['movil'] ?? null,
            'address' => $data['address'] ?? null,
            'date_afi' => $data['date_afi'] ?? null,
            'email' => $data['email'],
            'user' => $data['user'],
            'password' => Hash::make($data['password']),
            'state' => $data['state'] ?? 1,
            'city_id' => $data['city_id'],
            'type' => $data['type'] ?? 2,
        ]);

        $this->registActionLogger->created('users', $user->id);

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'data' => $user,
        ], 201);
    }

    /**
     * Display a specific user
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
     * Shared by the franchise admin screen (super admin) and "Mi cuenta" (any user, own record).
     *
     * SECURITY_REVIEW: non-admins can only target their own id and can never set type, state or
     * password here — otherwise any franchise could promote itself or take over the admin account.
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $authUser = $request->user();
        $isSuperAdmin = $authUser->isSuperAdmin();

        // A non-admin may only edit their own record (the "Mi cuenta" screen) — never another
        // franchise or the super admin account.
        if (!$isSuperAdmin && (int) $id !== $authUser->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // $request->filled() is used on the FormRequest itself (extends Request)
        // instead of $request->validated() to preserve exactly the original
        // semantics: a field is only assigned if it comes "filled" (not null, not ''),
        // it is not enough for the key to just exist in the validated array.
        // `password`, `state` and `type` are privileged: a non-admin sending them is ignored (same
        // pattern as `stade` on affiliates) so they can't promote themselves to super admin. Their
        // own password goes through change-password, which requires the current one.
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
        if ($request->filled('password') && $isSuperAdmin)
            $user->password = Hash::make($request->password);
        if ($request->filled('state') && $isSuperAdmin)
            $user->state = $request->state;
        if ($request->filled('city_id'))
            $user->city_id = $request->city_id;
        if ($request->filled('type') && $isSuperAdmin)
            $user->type = $request->type;

        $user->save();

        if ($user->wasChanged('state')) {
            $this->registActionLogger->statusChanged('users', $user->id);
        } else {
            $this->registActionLogger->updated('users', $user->id);
        }

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'data' => $user,
        ], 200);
    }

    /**
     * Delete a user
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

        $this->registActionLogger->deleted('users', $user->id);

        return response()->json([
            'message' => 'Usuario eliminado correctamente',
        ], 200);
    }

    /**
     * Requires the current password so a hijacked session alone can't lock the owner out.
     * Rate limited by route (throttle:5,1) to stop guessing the current password.
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => ['required', 'string', Password::min(8)->letters()->numbers()],
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
     * Active franchises for the public website (footer).
     * Only exposes name, address, and city; never internal data (NIT, email, phone).
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
