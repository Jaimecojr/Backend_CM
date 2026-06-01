<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'movil'   => 'required|digits:10',
            'email'   => 'required|email|max:255',
            'asunto'  => 'required|string|max:255',
            'city_id' => 'required|exists:cities,id',
            'mensaje' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error en la validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $contact = Contact::create([
            'name'    => $request->name,
            'email'   => $request->email,
            'phone'   => $request->movil,
            'city_id' => $request->city_id,
            'subject' => $request->asunto,
            'comment' => $request->mensaje,
        ]);

        return response()->json([
            'message' => 'Mensaje de contacto recibido correctamente',
            'data'    => $contact,
        ], 201);
    }

    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 20);
        $search  = $request->query('search', '');

        $query = Contact::with(['city:id,name'])
            ->select('contacts.*');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name',  'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('id', 'desc');

        $paginated = $query->paginate($perPage);

        return response()->json([
            'message' => 'Mensajes de contacto obtenidos correctamente',
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ], 200);
    }

    public function show($id)
    {
        $contact = Contact::with(['city:id,name'])->find($id);

        if (!$contact) {
            return response()->json(['message' => 'Mensaje no encontrado'], 404);
        }

        return response()->json([
            'message' => 'Mensaje obtenido correctamente',
            'data'    => $contact,
        ], 200);
    }

    public function destroy($id)
    {
        $contact = Contact::find($id);

        if (!$contact) {
            return response()->json(['message' => 'Mensaje no encontrado'], 404);
        }

        $contact->delete();

        return response()->json(['message' => 'Mensaje eliminado correctamente'], 200);
    }
}
