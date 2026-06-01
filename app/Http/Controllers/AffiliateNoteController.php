<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\AffiliateNote;
use Illuminate\Http\Request;

class AffiliateNoteController extends Controller
{
    /**
     * Listar notas de un afiliado (más recientes primero)
     */
    public function index(Affiliate $affiliate)
    {
        $notes = $affiliate->notes()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get(['id', 'affiliate_id', 'user_id', 'body', 'created_at']);

        return response()->json([
            'message' => 'Notas obtenidas correctamente',
            'data'    => $notes,
        ]);
    }

    /**
     * Crear una nota nueva
     */
    public function store(Request $request, Affiliate $affiliate)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $note = $affiliate->notes()->create([
            'user_id' => $request->user()->id,
            'body'    => $validated['body'],
        ]);

        $note->load('user:id,name');

        return response()->json([
            'message' => 'Nota creada correctamente',
            'data'    => $note,
        ], 201);
    }

    /**
     * Eliminar una nota (solo usuarios tipo 1)
     */
    public function destroy(Request $request, Affiliate $affiliate, AffiliateNote $note)
    {
        // Solo super admin (type == 1) puede eliminar
        if ($request->user()->type !== 1) {
            return response()->json([
                'message' => 'No tienes permisos para eliminar notas.',
            ], 403);
        }

        // Verificar que la nota pertenece al afiliado
        if ($note->affiliate_id !== $affiliate->id) {
            return response()->json(['message' => 'Nota no encontrada.'], 404);
        }

        $note->delete();

        return response()->json(['message' => 'Nota eliminada correctamente.']);
    }
}
